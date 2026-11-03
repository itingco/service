<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use App\Data\BusinessAnalytics\BusinessAnalyticsFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AnalyticsRepository
{
    /** @return array<string,float> */
    public function monthlyTrend(BusinessAnalyticsFilter $filter, int $months = 36): array
    {
        $start = date('Y-m', strtotime($filter->endMonth.'-01 -'.($months - 1).' months'));
        $rows = $this->base($filter)
            ->whereBetween('period', [$start, $filter->endMonth])
            ->selectRaw('period, SUM(sales_amount) AS sales')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $found = [];
        foreach ($rows as $row) {
            $found[(string) $row->period] = (float) $row->sales;
        }

        if ($found === []) {
            return [];
        }

        $firstFound = array_key_first($found);
        $series = [];
        for ($cursor = new \DateTimeImmutable($firstFound.'-01'); $cursor <= new \DateTimeImmutable($filter->endMonth.'-01'); $cursor = $cursor->modify('+1 month')) {
            $period = $cursor->format('Y-m');
            $series[$period] = $found[$period] ?? 0.0;
        }

        return $series;
    }

    /** @return list<array{year:string,sales:float,growth:?float}> */
    public function yearlySummary(BusinessAnalyticsFilter $filter): array
    {
        $cutoffMonth = (int) substr($filter->endMonth, 5, 2);
        $rows = $this->base($filter)
            ->whereRaw('CAST(substr(period, 6, 2) AS INTEGER) <= ?', [$cutoffMonth])
            ->selectRaw("substr(period, 1, 4) AS year, SUM(sales_amount) AS sales")
            ->groupByRaw("substr(period, 1, 4)")
            ->orderBy('year')
            ->get();

        $result = [];
        $previous = null;
        foreach ($rows as $row) {
            $sales = (float) $row->sales;
            $result[] = [
                'year' => (string) $row->year,
                'sales' => $sales,
                'growth' => $previous !== null && $previous != 0.0 ? ($sales / $previous) - 1.0 : null,
            ];
            $previous = $sales;
        }
        return $result;
    }

    /** @return list<array<string,mixed>> */
    public function breakdown(BusinessAnalyticsFilter $filter): array
    {
        [$keyExpression, $labelExpression] = match ($filter->groupBy) {
            'company' => ['source', 'source'],
            'line' => ['sales_line', 'sales_line'],
            'customer' => ["CASE WHEN customer_code = '' THEN customer_name ELSE customer_code END", "CASE WHEN customer_name = '' THEN customer_code ELSE customer_name END"],
            'salesperson' => ["CASE WHEN salesperson_name = '' THEN '(TANPA SALES)' ELSE salesperson_name END", "CASE WHEN salesperson_name = '' THEN '(Tanpa Sales)' ELSE salesperson_name END"],
            'item' => ["CASE WHEN item_code = '' THEN item_name ELSE item_code END", "CASE WHEN item_name = '' THEN item_code ELSE item_name END"],
            default => ['source', 'source'],
        };

        $currentStart = date('Y-m', strtotime($filter->endMonth.'-01 -2 months'));
        $priorStart = date('Y-m', strtotime($filter->endMonth.'-01 -14 months'));
        $priorEnd = date('Y-m', strtotime($filter->endMonth.'-01 -12 months'));

        $currentRows = $this->base($filter)
            ->whereBetween('period', [$currentStart, $filter->endMonth])
            ->selectRaw("{$keyExpression} AS dimension_key, {$labelExpression} AS dimension_label, SUM(sales_amount) AS sales, SUM(quantity) AS quantity, SUM(invoice_count) AS invoices")
            ->groupByRaw($keyExpression.', '.$labelExpression)
            ->get();

        $priorRows = $this->base($filter)
            ->whereBetween('period', [$priorStart, $priorEnd])
            ->selectRaw("{$keyExpression} AS dimension_key, SUM(sales_amount) AS sales")
            ->groupByRaw($keyExpression)
            ->pluck('sales', 'dimension_key');

        $total = (float) $currentRows->sum('sales');
        $result = [];
        foreach ($currentRows as $row) {
            $key = trim((string) $row->dimension_key);
            if ($key === '') {
                $key = '(KOSONG)';
            }
            $sales = (float) $row->sales;
            $prior = isset($priorRows[$row->dimension_key]) ? (float) $priorRows[$row->dimension_key] : 0.0;
            $result[] = [
                'key' => $key,
                'label' => trim((string) $row->dimension_label) ?: $key,
                'sales' => $sales,
                'quantity' => (float) $row->quantity,
                'invoices' => (int) $row->invoices,
                'contribution' => $total > 0.0 ? $sales / $total : null,
                'yoy_growth' => $prior > 0.0 ? ($sales / $prior) - 1.0 : null,
            ];
        }

        usort($result, static fn (array $a, array $b): int => $b['sales'] <=> $a['sales']);
        return array_slice($result, 0, 100);
    }


    /** @return array<string,array<string,mixed>> */
    public function channelSummary(BusinessAnalyticsFilter $filter): array
    {
        $current = $filter->endMonth;
        $previous = date('Y-m', strtotime($current.'-01 -1 month'));
        $priorYear = date('Y-m', strtotime($current.'-01 -1 year'));
        $current3Start = date('Y-m', strtotime($current.'-01 -2 months'));
        $previous3Start = date('Y-m', strtotime($current.'-01 -5 months'));
        $previous3End = date('Y-m', strtotime($current.'-01 -3 months'));

        $result = [];
        $totalCurrent = $this->totalForPeriod($filter, $current);
        foreach (['ONLINE', 'OFFLINE'] as $channel) {
            $sales = $this->channelTotal($filter, $channel, $current, $current);
            $previousSales = $this->channelTotal($filter, $channel, $previous, $previous);
            $priorYearSales = $this->channelTotal($filter, $channel, $priorYear, $priorYear);
            $current3 = $this->channelTotal($filter, $channel, $current3Start, $current);
            $previous3 = $this->channelTotal($filter, $channel, $previous3Start, $previous3End);

            $result[strtolower($channel)] = [
                'channel' => $channel,
                'sales' => $sales,
                'share' => $totalCurrent > 0.0 ? $sales / $totalCurrent : null,
                'mom_growth' => $previousSales > 0.0 ? ($sales / $previousSales) - 1.0 : null,
                'yoy_growth' => $priorYearSales > 0.0 ? ($sales / $priorYearSales) - 1.0 : null,
                'prior_3m_growth' => $previous3 > 0.0 ? ($current3 / $previous3) - 1.0 : null,
                'sales_3m' => $current3,
            ];
        }

        return $result;
    }

    /** @return array{summary:array<string,mixed>,rows:list<array<string,mixed>>} */
    public function customerMovement(BusinessAnalyticsFilter $filter): array
    {
        return $this->movement($filter, 'customer');
    }

    /** @return array{summary:array<string,mixed>,rows:list<array<string,mixed>>} */
    public function productMovement(BusinessAnalyticsFilter $filter): array
    {
        return $this->movement($filter, 'item');
    }

    /** @return list<array<string,mixed>> */
    public function lineDrivers(BusinessAnalyticsFilter $filter): array
    {
        $current = $filter->endMonth;
        $priorYear = date('Y-m', strtotime($current.'-01 -1 year'));
        $previous = date('Y-m', strtotime($current.'-01 -1 month'));

        $currentRows = $this->base($filter)->where('period', $current)
            ->selectRaw("sales_line AS dimension_key, SUM(sales_amount) AS sales")
            ->groupBy('sales_line')->pluck('sales', 'dimension_key');
        $priorRows = $this->base($filter)->where('period', $priorYear)
            ->selectRaw("sales_line AS dimension_key, SUM(sales_amount) AS sales")
            ->groupBy('sales_line')->pluck('sales', 'dimension_key');
        $previousRows = $this->base($filter)->where('period', $previous)
            ->selectRaw("sales_line AS dimension_key, SUM(sales_amount) AS sales")
            ->groupBy('sales_line')->pluck('sales', 'dimension_key');

        $keys = array_unique([...$currentRows->keys()->all(), ...$priorRows->keys()->all(), ...$previousRows->keys()->all()]);
        $rows = [];
        foreach ($keys as $key) {
            $sales = (float) ($currentRows[$key] ?? 0.0);
            $prior = (float) ($priorRows[$key] ?? 0.0);
            $prev = (float) ($previousRows[$key] ?? 0.0);
            $rows[] = [
                'key' => (string) $key,
                'label' => (string) $key,
                'sales' => $sales,
                'yoy_delta' => $sales - $prior,
                'yoy_growth' => $prior > 0.0 ? ($sales / $prior) - 1.0 : null,
                'mom_delta' => $sales - $prev,
                'mom_growth' => $prev > 0.0 ? ($sales / $prev) - 1.0 : null,
                'channel' => $this->isOnlineLine((string) $key) ? 'ONLINE' : 'OFFLINE',
            ];
        }
        usort($rows, static fn(array $a, array $b): int => abs($b['yoy_delta']) <=> abs($a['yoy_delta']));
        return array_slice($rows, 0, 20);
    }

    /** @return list<array<string,mixed>> */
    public function channelItemCandidates(BusinessAnalyticsFilter $filter, string $channel): array
    {
        $end = $filter->endMonth;
        $start = date('Y-m', strtotime($end.'-01 -2 months'));
        $priorStart = date('Y-m', strtotime($end.'-01 -14 months'));
        $priorEnd = date('Y-m', strtotime($end.'-01 -12 months'));

        $currentQuery = $this->base($filter)->whereBetween('period', [$start, $end]);
        $this->applyChannel($currentQuery, $channel);
        $current = $currentQuery
            ->selectRaw("CASE WHEN item_code = '' THEN item_name ELSE item_code END AS item_key, MAX(item_name) AS item_name, SUM(sales_amount) AS sales, SUM(quantity) AS quantity")
            ->groupByRaw("CASE WHEN item_code = '' THEN item_name ELSE item_code END")
            ->get();

        $priorQuery = $this->base($filter)->whereBetween('period', [$priorStart, $priorEnd]);
        $this->applyChannel($priorQuery, $channel);
        $prior = $priorQuery
            ->selectRaw("CASE WHEN item_code = '' THEN item_name ELSE item_code END AS item_key, SUM(sales_amount) AS sales")
            ->groupByRaw("CASE WHEN item_code = '' THEN item_name ELSE item_code END")
            ->pluck('sales', 'item_key');

        $rows = [];
        foreach ($current as $row) {
            $sales = (float) $row->sales;
            if ($sales <= 0) continue;
            $priorSales = (float) ($prior[$row->item_key] ?? 0.0);
            $rows[] = [
                'key' => (string) $row->item_key,
                'label' => trim((string) $row->item_name) ?: (string) $row->item_key,
                'sales' => $sales,
                'quantity' => (float) $row->quantity,
                'yoy_growth' => $priorSales > 0.0 ? ($sales / $priorSales) - 1.0 : null,
                'new_demand' => $priorSales <= 0.0,
            ];
        }
        usort($rows, static function(array $a, array $b): int {
            $ag = $a['yoy_growth'] ?? ($a['new_demand'] ? 9.0 : -9.0);
            $bg = $b['yoy_growth'] ?? ($b['new_demand'] ? 9.0 : -9.0);
            return [$bg, $b['sales']] <=> [$ag, $a['sales']];
        });
        return array_slice($rows, 0, 12);
    }

    /** @param array<string,float> $trend @return list<array<string,mixed>> */
    public function seasonality(array $trend): array
    {
        if ($trend === []) return [];
        $byMonth = array_fill(1, 12, []);
        foreach ($trend as $period => $sales) {
            $month = (int) substr($period, 5, 2);
            $byMonth[$month][] = (float) $sales;
        }
        $all = array_values(array_filter(array_map('floatval', array_values($trend)), static fn(float $v): bool => $v > 0));
        $overall = $all !== [] ? array_sum($all) / count($all) : 0.0;
        $result = [];
        foreach ($byMonth as $month => $values) {
            $avg = $values !== [] ? array_sum($values) / count($values) : 0.0;
            $index = $overall > 0.0 ? $avg / $overall : null;
            $result[] = [
                'month' => $month,
                'label' => date('M', mktime(0, 0, 0, $month, 1)),
                'average' => $avg,
                'index' => $index,
                'signal' => $index === null ? 'N/A' : ($index >= 1.15 ? 'HIGH' : ($index <= .85 ? 'LOW' : 'NORMAL')),
            ];
        }
        return $result;
    }

    /** @param array<string,float> $trend @return list<array<string,mixed>> */
    public function anomalies(array $trend): array
    {
        $values = array_values($trend);
        if (count($values) < 8) return [];
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(static fn(float $v): float => ($v - $mean) ** 2, $values)) / max(1, count($values) - 1);
        $sd = sqrt($variance);
        if ($sd <= 0.0) return [];
        $rows = [];
        foreach ($trend as $period => $sales) {
            $z = ($sales - $mean) / $sd;
            if (abs($z) < 1.65) continue;
            $rows[] = [
                'period' => $period,
                'sales' => (float) $sales,
                'z_score' => $z,
                'type' => $z > 0 ? 'SPIKE' : 'DROP',
                'deviation' => $mean > 0.0 ? ($sales / $mean) - 1.0 : null,
            ];
        }
        usort($rows, static fn(array $a, array $b): int => abs($b['z_score']) <=> abs($a['z_score']));
        return array_slice($rows, 0, 8);
    }

    /** @return array{summary:array<string,mixed>,rows:list<array<string,mixed>>} */
    private function movement(BusinessAnalyticsFilter $filter, string $dimension): array
    {
        [$keyExpr, $labelExpr] = $dimension === 'customer'
            ? ["CASE WHEN customer_code = '' THEN customer_name ELSE customer_code END", "CASE WHEN customer_name = '' THEN customer_code ELSE customer_name END"]
            : ["CASE WHEN item_code = '' THEN item_name ELSE item_code END", "CASE WHEN item_name = '' THEN item_code ELSE item_name END"];

        $end = $filter->endMonth;
        $currentStart = date('Y-m', strtotime($end.'-01 -2 months'));
        $previousStart = date('Y-m', strtotime($end.'-01 -5 months'));
        $previousEnd = date('Y-m', strtotime($end.'-01 -3 months'));
        $priorStart = date('Y-m', strtotime($end.'-01 -14 months'));
        $priorEnd = date('Y-m', strtotime($end.'-01 -12 months'));

        $fetch = function(string $start, string $finish) use ($filter, $keyExpr, $labelExpr) {
            return $this->base($filter)->whereBetween('period', [$start, $finish])
                ->selectRaw("{$keyExpr} AS dimension_key, MAX({$labelExpr}) AS dimension_label, SUM(sales_amount) AS sales")
                ->groupByRaw($keyExpr)->get();
        };
        $currentRows = $fetch($currentStart, $end);
        $previousRows = $fetch($previousStart, $previousEnd);
        $priorRows = $fetch($priorStart, $priorEnd);

        $current = []; $labels = [];
        foreach ($currentRows as $row) { $k=(string)$row->dimension_key; if($k==='') continue; $current[$k]=(float)$row->sales; $labels[$k]=trim((string)$row->dimension_label) ?: $k; }
        $previous = []; foreach ($previousRows as $row) { $k=(string)$row->dimension_key; if($k==='') continue; $previous[$k]=(float)$row->sales; $labels[$k] ??= trim((string)$row->dimension_label) ?: $k; }
        $prior = []; foreach ($priorRows as $row) { $k=(string)$row->dimension_key; if($k==='') continue; $prior[$k]=(float)$row->sales; $labels[$k] ??= trim((string)$row->dimension_label) ?: $k; }

        $keys = array_unique([...array_keys($current), ...array_keys($previous), ...array_keys($prior)]);
        $summary = [
            'new_count'=>0,'new_sales'=>0.0,'reactivated_count'=>0,'reactivated_sales'=>0.0,
            'growing_count'=>0,'growing_sales'=>0.0,'stable_count'=>0,'stable_sales'=>0.0,
            'declining_count'=>0,'declining_sales'=>0.0,'lost_count'=>0,'lost_sales'=>0.0,
            'top5_share'=>null,
        ];
        $rows = [];
        foreach ($keys as $key) {
            $cur = $current[$key] ?? 0.0; $prev = $previous[$key] ?? 0.0; $py = $prior[$key] ?? 0.0;
            $base = max($prev, $py);
            if ($cur > 0 && $prev <= 0 && $py <= 0) $status='NEW';
            elseif ($cur > 0 && $prev <= 0 && $py > 0) $status='REACTIVATED';
            elseif ($cur <= 0 && $base > 0) $status='LOST';
            elseif ($base > 0 && (($cur/$base)-1) >= .15) $status='GROWING';
            elseif ($base > 0 && (($cur/$base)-1) <= -.15) $status='DECLINING';
            else $status='STABLE';
            $impact = $status === 'LOST' ? $base : $cur;
            $summaryKey = strtolower($status);
            $summary[$summaryKey.'_count']++;
            $summary[$summaryKey.'_sales'] += $impact;
            $rows[] = [
                'key'=>$key,'label'=>$labels[$key] ?? $key,'status'=>$status,'sales'=>$cur,
                'previous_3m'=>$prev,'prior_year_3m'=>$py,'delta_vs_base'=>$cur-$base,
                'growth_vs_base'=>$base>0 ? ($cur/$base)-1.0 : null,'impact'=>$impact,
            ];
        }
        $activeSales = array_sum($current);
        rsort($current);
        $summary['top5_share'] = $activeSales > 0.0 ? array_sum(array_slice($current,0,5))/$activeSales : null;
        usort($rows, static fn(array $a,array $b): int => abs($b['delta_vs_base']) <=> abs($a['delta_vs_base']));
        return ['summary'=>$summary,'rows'=>array_slice($rows,0,30)];
    }

    private function channelTotal(BusinessAnalyticsFilter $filter, string $channel, string $start, string $end): float
    {
        $query = $this->base($filter)->whereBetween('period', [$start, $end]);
        $this->applyChannel($query, $channel);
        return (float) $query->sum('sales_amount');
    }

    private function applyChannel(Builder $query, string $channel): void
    {
        $onlinePatterns = ['%ONLINE%', '%E-COMMERCE%', '%ECOMMERCE%', '%MARKETPLACE%', '%SHOPEE%', '%TOKOPEDIA%', '%TIKTOK%'];
        if (strtoupper($channel) === 'ONLINE') {
            $query->where(function(Builder $q) use ($onlinePatterns): void {
                foreach ($onlinePatterns as $pattern) $q->orWhereRaw('UPPER(sales_line) LIKE ?', [$pattern]);
            });
            return;
        }
        $query->where(function(Builder $q) use ($onlinePatterns): void {
            foreach ($onlinePatterns as $pattern) $q->whereRaw('UPPER(sales_line) NOT LIKE ?', [$pattern]);
        });
    }

    private function isOnlineLine(string $line): bool
    {
        $line = strtoupper($line);
        foreach (['ONLINE','E-COMMERCE','ECOMMERCE','MARKETPLACE','SHOPEE','TOKOPEDIA','TIKTOK'] as $needle) {
            if (str_contains($line, $needle)) return true;
        }
        return false;
    }

    public function latestPeriod(): ?string
    {
        $period = DB::connection('sqlite_targets')->table('business_analytics_monthly_sales')->max('period');
        return $period ? (string) $period : null;
    }

    public function totalForPeriod(BusinessAnalyticsFilter $filter, string $period): float
    {
        return (float) $this->base($filter)->where('period', $period)->sum('sales_amount');
    }

    private function base(BusinessAnalyticsFilter $filter): Builder
    {
        $query = DB::connection('sqlite_targets')->table('business_analytics_monthly_sales');
        $query->whereIn('source', $filter->sources());

        if ($filter->line !== null) {
            $query->where('sales_line', $filter->line);
        }
        if ($filter->customerCode !== null) {
            $customer = $filter->customerCode;
            $query->where(function (Builder $q) use ($customer): void {
                $q->where('customer_code', $customer)->orWhere('customer_name', $customer);
            });
        }
        if ($filter->salesperson !== null) {
            $query->where('salesperson_name', $filter->salesperson);
        }

        return $query;
    }
}
