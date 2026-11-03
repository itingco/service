<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SalesKpiTargetLookup;
use App\Data\SalesDashboardFilter;
use App\Data\SalesDashboardLineRow;
use App\Data\SalesDashboardReport;
use App\Support\SalesLineName;

final class SalesDashboardAggregator
{
    /**
     * @param array<string,float|int> $fallbackTargets
     * @param array<string,string> $aliases
     */
    public function __construct(
        private readonly SalesKpiTargetLookup $targetLookup,
        private readonly array $fallbackTargets = [],
        private readonly array $aliases = [],
    ) {
    }

    /**
     * @param array{
     *   dtd:list<array<string,mixed>>,
     *   ldtd:list<array<string,mixed>>,
     *   mtd:list<array<string,mixed>>,
     *   lmtd:list<array<string,mixed>>
     * } $periodRows
     */
    public function aggregate(SalesDashboardFilter $filter, array $periodRows): SalesDashboardReport
    {
        $targets = $this->targets();

        /** @var array<string,array{dtd:float,ldtd:float,mtd:float,lmtd:float}> $values */
        $values = [];
        foreach ($targets as $line => $target) {
            $values[$line] = ['dtd' => 0.0, 'ldtd' => 0.0, 'mtd' => 0.0, 'lmtd' => 0.0];
        }

        foreach (['dtd', 'ldtd', 'mtd', 'lmtd'] as $period) {
            foreach ($periodRows[$period] ?? [] as $transaction) {
                $line = $this->canonicalLine((string) ($transaction['line'] ?? ''), $targets);
                $values[$line] ??= ['dtd' => 0.0, 'ldtd' => 0.0, 'mtd' => 0.0, 'lmtd' => 0.0];
                $values[$line][$period] += (float) ($transaction['sales'] ?? 0.0);
            }
        }

        $totalMtd = array_sum(array_map(
            static fn (array $metrics): float => $metrics['mtd'],
            $values,
        ));

        $rows = [];
        foreach ($targets as $line => $monthlyTarget) {
            $metrics = $values[$line];
            $rows[] = $this->makeRow(
                line: $line,
                monthlyTarget: (float) $monthlyTarget,
                dtd: $metrics['dtd'],
                ldtd: $metrics['ldtd'],
                mtd: $metrics['mtd'],
                lmtd: $metrics['lmtd'],
                filter: $filter,
                contributionPercentage: $totalMtd !== 0.0 ? $metrics['mtd'] / $totalMtd : null,
            );
        }

        $detailRows = $this->prepareDetailRows($periodRows['mtd'] ?? [], $targets);

        return new SalesDashboardReport(
            filter: $filter,
            rows: $rows,
            totalRow: $this->makeTotalRow($rows, $filter),
            detailRows: $detailRows,
        );
    }

    /**
     * @param list<array<string,mixed>> $transactions
     * @param array<string,float> $targets
     * @return list<array<string,mixed>>
     */
    public function prepareDetailRows(array $transactions, ?array $targets = null): array
    {
        $targets ??= $this->targets();
        $salesByLine = array_fill_keys(array_keys($targets), 0.0);
        foreach ($transactions as $transaction) {
            $line = $this->canonicalLine((string) ($transaction['line'] ?? ''), $targets);
            $salesByLine[$line] ??= 0.0;
            $salesByLine[$line] += (float) ($transaction['sales'] ?? 0.0);
        }

        $total = array_sum($salesByLine);

        return array_map(function (array $transaction) use ($salesByLine, $total, $targets): array {
            $line = $this->canonicalLine((string) ($transaction['line'] ?? ''), $targets);
            $transaction['line'] = $line;
            $transaction['line_contribution'] = $total !== 0.0
                ? ($salesByLine[$line] ?? 0.0) / $total
                : null;

            return $transaction;
        }, $transactions);
    }

    /** @param array<string,float> $targets */
    private function canonicalLine(string $line, array $targets): string
    {
        $normalized = SalesLineName::normalize($line);
        $normalized = SalesLineName::normalize($this->aliases[$normalized] ?? $normalized);

        return array_key_exists($normalized, $targets) ? $normalized : SalesLineName::OTHERS;
    }

    /** @return array<string,float> */
    private function targets(): array
    {
        $targets = [];

        foreach ($this->fallbackTargets as $line => $target) {
            $normalized = SalesLineName::normalize((string) $line);
            $normalized = SalesLineName::normalize($this->aliases[$normalized] ?? $normalized);
            $targets[$normalized] = max(0.0, (float) $target);
        }

        foreach ($this->targetLookup->all() as $line => $target) {
            $normalized = SalesLineName::normalize((string) $line);
            $normalized = SalesLineName::normalize($this->aliases[$normalized] ?? $normalized);
            $targets[$normalized] = $target === null ? 0.0 : max(0.0, (float) $target);
        }

        return $targets;
    }

    private function makeRow(
        string $line,
        float $monthlyTarget,
        float $dtd,
        float $ldtd,
        float $mtd,
        float $lmtd,
        SalesDashboardFilter $filter,
        ?float $contributionPercentage,
    ): SalesDashboardLineRow {
        $gap = $monthlyTarget - $mtd;
        $average = $mtd / max(1, $filter->elapsedDays);
        $estimated = $average * $filter->totalSalesDays;

        return new SalesDashboardLineRow(
            line: $line,
            monthlyTarget: $monthlyTarget,
            dailyTarget: $monthlyTarget / max(1, $filter->totalSalesDays),
            requiredDailySales: max(0.0, $gap) / max(1, $filter->remainingSalesDays),
            dtd: $dtd,
            ldtd: $ldtd,
            dtdGrowth: $this->growth($dtd, $ldtd),
            mtd: $mtd,
            lmtd: $lmtd,
            mtdGrowth: $this->growth($mtd, $lmtd),
            averageDailySales: $average,
            estimatedEom: $estimated,
            estimatedEomPercentage: $monthlyTarget !== 0.0 ? $estimated / $monthlyTarget : null,
            achievementPercentage: $monthlyTarget !== 0.0 ? $mtd / $monthlyTarget : null,
            gap: $gap,
            contributionPercentage: $contributionPercentage,
        );
    }

    /** @param list<SalesDashboardLineRow> $rows */
    private function makeTotalRow(array $rows, SalesDashboardFilter $filter): SalesDashboardLineRow
    {
        $target = array_sum(array_map(static fn (SalesDashboardLineRow $row): float => $row->monthlyTarget, $rows));
        $dailyTarget = $target / max(1, $filter->totalSalesDays);
        $requiredDailySales = array_sum(array_map(static fn (SalesDashboardLineRow $row): float => $row->requiredDailySales, $rows));
        $dtd = array_sum(array_map(static fn (SalesDashboardLineRow $row): float => $row->dtd, $rows));
        $ldtd = array_sum(array_map(static fn (SalesDashboardLineRow $row): float => $row->ldtd, $rows));
        $mtd = array_sum(array_map(static fn (SalesDashboardLineRow $row): float => $row->mtd, $rows));
        $lmtd = array_sum(array_map(static fn (SalesDashboardLineRow $row): float => $row->lmtd, $rows));
        $average = $mtd / max(1, $filter->elapsedDays);
        $estimated = $average * $filter->totalSalesDays;

        return new SalesDashboardLineRow(
            line: 'TOTAL',
            monthlyTarget: $target,
            dailyTarget: $dailyTarget,
            requiredDailySales: $requiredDailySales,
            dtd: $dtd,
            ldtd: $ldtd,
            dtdGrowth: $this->growth($dtd, $ldtd),
            mtd: $mtd,
            lmtd: $lmtd,
            mtdGrowth: $this->growth($mtd, $lmtd),
            averageDailySales: $average,
            estimatedEom: $estimated,
            estimatedEomPercentage: $target !== 0.0 ? $estimated / $target : null,
            achievementPercentage: $target !== 0.0 ? $mtd / $target : null,
            gap: $target - $mtd,
            contributionPercentage: $mtd !== 0.0 ? 1.0 : null,
        );
    }

    private function growth(float $current, float $previous): ?float
    {
        return $previous !== 0.0 ? ($current / $previous) - 1.0 : null;
    }
}
