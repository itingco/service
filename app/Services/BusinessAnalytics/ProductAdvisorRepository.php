<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ProductAdvisorRepository
{
    /** @return list<array<string,mixed>> */
    public function searchItems(string $search, string $source = 'ALL', int $limit = 30): array
    {
        $query = DB::connection('sqlite_targets')->table('business_analytics_monthly_sales');
        if (strtoupper($source) !== 'ALL') {
            $query->where('source', strtoupper($source));
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%'.strtoupper($search).'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->whereRaw('UPPER(item_code) LIKE ?', [$like])->orWhereRaw('UPPER(item_name) LIKE ?', [$like]);
            });
        }
        return $query->where(function (Builder $q): void {
                $q->where('item_code', '<>', '')->orWhere('item_name', '<>', '');
            })
            ->selectRaw("CASE WHEN item_code = '' THEN item_name ELSE item_code END AS item_code, MAX(item_name) AS item_name, SUM(sales_amount) AS historical_sales")
            ->groupByRaw("CASE WHEN item_code = '' THEN item_name ELSE item_code END")
            ->orderByDesc('historical_sales')
            ->limit(max(1, min(100, $limit)))
            ->get()->map(static fn(object $r): array => [
                'item_code' => (string) $r->item_code,
                'item_name' => trim((string) $r->item_name) ?: (string) $r->item_code,
                'historical_sales' => (float) $r->historical_sales,
            ])->all();
    }

    /** @return array<string,mixed>|null */
    public function profile(string $itemCode, string $source = 'ALL'): ?array
    {
        $row = DB::connection('sqlite_targets')->table('business_analytics_product_profiles')
            ->where('source', strtoupper($source))->where('item_code', $itemCode)->first();
        return $row ? (array) $row : null;
    }

    /** @param array<string,mixed> $data */
    public function saveProfile(string $itemCode, string $source, array $data, ?int $userId = null): void
    {
        $source = strtoupper($source);
        $now = now();
        DB::connection('sqlite_targets')->table('business_analytics_product_profiles')->updateOrInsert(
            ['source' => $source, 'item_code' => $itemCode],
            [
                'item_name' => (string) ($data['item_name'] ?? ''),
                'specifications' => (string) ($data['specifications'] ?? ''),
                'use_cases' => $this->nullableText($data['use_cases'] ?? null),
                'advantages' => $this->nullableText($data['advantages'] ?? null),
                'reference_price' => $data['reference_price'] ?? null,
                'current_stock' => $data['current_stock'] ?? null,
                'target_margin_percent' => $data['target_margin_percent'] ?? null,
                'competitor_notes' => $this->nullableText($data['competitor_notes'] ?? null),
                'constraints' => $this->nullableText($data['constraints'] ?? null),
                'updated_by_user_id' => $userId,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /** @return array<string,float> */
    public function productMonthlyTrend(string $itemCode, string $source, string $endMonth, int $months = 36): array
    {
        $start = (new DateTimeImmutable($endMonth.'-01'))->modify('-'.($months - 1).' months')->format('Y-m');
        $rows = $this->productBase($itemCode, $source)
            ->whereBetween('period', [$start, $endMonth])
            ->selectRaw('period, SUM(sales_amount) AS sales')
            ->groupBy('period')->orderBy('period')->get();
        $found = [];
        foreach ($rows as $row) $found[(string) $row->period] = (float) $row->sales;
        if ($found === []) return [];
        $series = [];
        $cursor = new DateTimeImmutable(array_key_first($found).'-01');
        $end = new DateTimeImmutable($endMonth.'-01');
        for (; $cursor <= $end; $cursor = $cursor->modify('+1 month')) {
            $period = $cursor->format('Y-m');
            $series[$period] = $found[$period] ?? 0.0;
        }
        return $series;
    }

    /** @return array<string,mixed> */
    public function channelFacts(string $itemCode, string $source, string $endMonth): array
    {
        $currentStart = (new DateTimeImmutable($endMonth.'-01'))->modify('-2 months')->format('Y-m');
        $previousStart = (new DateTimeImmutable($endMonth.'-01'))->modify('-5 months')->format('Y-m');
        $previousEnd = (new DateTimeImmutable($endMonth.'-01'))->modify('-3 months')->format('Y-m');
        $priorYearStart = (new DateTimeImmutable($endMonth.'-01'))->modify('-14 months')->format('Y-m');
        $priorYearEnd = (new DateTimeImmutable($endMonth.'-01'))->modify('-12 months')->format('Y-m');
        $total3 = (float) $this->productBase($itemCode, $source)->whereBetween('period', [$currentStart, $endMonth])->sum('sales_amount');
        $out = [];
        foreach (['ONLINE', 'OFFLINE'] as $channel) {
            $current = $this->channelSum($itemCode, $source, $currentStart, $endMonth, $channel);
            $previous = $this->channelSum($itemCode, $source, $previousStart, $previousEnd, $channel);
            $priorYear = $this->channelSum($itemCode, $source, $priorYearStart, $priorYearEnd, $channel);
            $out[strtolower($channel).'_sales_3m'] = $current;
            $out[strtolower($channel).'_share'] = $total3 > 0 ? $current / $total3 : null;
            $out[strtolower($channel).'_momentum_3m'] = $previous > 0 ? ($current / $previous) - 1.0 : null;
            $out[strtolower($channel).'_yoy_growth'] = $priorYear > 0 ? ($current / $priorYear) - 1.0 : null;
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function customerFacts(string $itemCode, string $source, string $endMonth): array
    {
        $end = new DateTimeImmutable($endMonth.'-01');
        $currentStart = $end->modify('-2 months')->format('Y-m');
        $previousStart = $end->modify('-5 months')->format('Y-m');
        $previousEnd = $end->modify('-3 months')->format('Y-m');
        $current = $this->customerSalesMap($itemCode, $source, $currentStart, $endMonth);
        $previous = $this->customerSalesMap($itemCode, $source, $previousStart, $previousEnd);
        $activeSales = array_values($current);
        rsort($activeSales);
        $total = array_sum($activeSales);
        $lostRevenue = 0.0; $lostCount = 0; $decliningCount = 0;
        foreach ($previous as $key => $sales) {
            $cur = $current[$key] ?? 0.0;
            if ($cur <= 0.0 && $sales > 0.0) { $lostRevenue += $sales; $lostCount++; }
            elseif ($sales > 0.0 && $cur / $sales < 0.80) $decliningCount++;
        }
        $topRows = $this->productBase($itemCode, $source)->whereBetween('period', [$currentStart, $endMonth])
            ->selectRaw("customer_code, MAX(customer_name) AS customer_name, SUM(sales_amount) AS sales")
            ->where(function(Builder $q): void { $q->where('customer_code','<>','')->orWhere('customer_name','<>',''); })
            ->groupBy('customer_code')->orderByDesc('sales')->limit(10)->get()
            ->map(static fn(object $r): array => ['customer_code'=>(string)$r->customer_code,'customer_name'=>(string)$r->customer_name,'sales'=>(float)$r->sales])->all();
        return [
            'active_buyers' => count(array_filter($current, static fn(float $v): bool => $v > 0)),
            'previous_active_buyers' => count(array_filter($previous, static fn(float $v): bool => $v > 0)),
            'lost_buyers' => $lostCount,
            'lost_revenue' => $lostRevenue,
            'declining_buyers' => $decliningCount,
            'top5_share' => $total > 0 ? array_sum(array_slice($activeSales,0,5))/$total : null,
            'top_customers' => $topRows,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function breakdown(string $itemCode, string $source, string $endMonth, string $dimension, int $months = 3): array
    {
        $start = (new DateTimeImmutable($endMonth.'-01'))->modify('-'.($months-1).' months')->format('Y-m');
        [$key,$label] = match($dimension) {
            'line' => ['sales_line','sales_line'],
            'salesperson' => ["CASE WHEN salesperson_name='' THEN '(TANPA SALES)' ELSE salesperson_name END", "CASE WHEN salesperson_name='' THEN '(Tanpa Sales)' ELSE salesperson_name END"],
            default => ['source','source'],
        };
        return $this->productBase($itemCode,$source)->whereBetween('period',[$start,$endMonth])
            ->selectRaw("{$key} AS dimension_key, MAX({$label}) AS dimension_label, SUM(sales_amount) AS sales, SUM(quantity) AS quantity")
            ->groupByRaw($key)->orderByDesc('sales')->limit(15)->get()
            ->map(static fn(object $r): array => ['key'=>(string)$r->dimension_key,'label'=>(string)$r->dimension_label,'sales'=>(float)$r->sales,'quantity'=>(float)$r->quantity])->all();
    }

    /** @return list<array<string,mixed>> */
    public function relatedItems(string $itemCode, string $source, string $endMonth, int $limit = 12): array
    {
        $start = (new DateTimeImmutable($endMonth.'-01'))->modify('-11 months')->format('Y-m');
        $buyers = $this->productBase($itemCode, $source)->whereBetween('period',[$start,$endMonth])
            ->where('customer_code','<>','')->distinct()->pluck('customer_code')->all();
        if ($buyers === []) return [];
        $q = DB::connection('sqlite_targets')->table('business_analytics_monthly_sales')
            ->whereBetween('period',[$start,$endMonth])->whereIn('customer_code',$buyers)->where('item_code','<>',$itemCode)->where('item_code','<>','');
        if (strtoupper($source)!=='ALL') $q->where('source',strtoupper($source));
        return $q->selectRaw('item_code, MAX(item_name) AS item_name, COUNT(DISTINCT customer_code) AS buyer_overlap, SUM(sales_amount) AS sales')
            ->groupBy('item_code')->orderByDesc('buyer_overlap')->orderByDesc('sales')->limit($limit)->get()
            ->map(static fn(object $r): array => ['item_code'=>(string)$r->item_code,'item_name'=>(string)$r->item_name,'buyer_overlap'=>(int)$r->buyer_overlap,'sales'=>(float)$r->sales])->all();
    }

    /** @return list<array<string,mixed>> */
    public function crossSellCandidates(string $itemCode, string $source, string $endMonth, int $limit = 30): array
    {
        $related = $this->relatedItems($itemCode,$source,$endMonth,8);
        $relatedCodes = array_values(array_filter(array_column($related,'item_code')));
        if ($relatedCodes === []) return [];
        $end = new DateTimeImmutable($endMonth.'-01');
        $start = $end->modify('-5 months')->format('Y-m');
        $boughtSelected = $this->productBase($itemCode,$source)->whereBetween('period',[$end->modify('-11 months')->format('Y-m'),$endMonth])
            ->where('customer_code','<>','')->distinct()->pluck('customer_code')->all();
        $q = DB::connection('sqlite_targets')->table('business_analytics_monthly_sales')
            ->whereBetween('period',[$start,$endMonth])->whereIn('item_code',$relatedCodes)->where('customer_code','<>','');
        if (strtoupper($source)!=='ALL') $q->where('source',strtoupper($source));
        if ($boughtSelected!==[]) $q->whereNotIn('customer_code',$boughtSelected);
        $rows=$q->selectRaw('customer_code, MAX(customer_name) AS customer_name, MAX(salesperson_name) AS salesperson_name, COUNT(DISTINCT item_code) AS related_overlap, SUM(sales_amount) AS related_sales, MAX(period) AS last_period')
            ->groupBy('customer_code')->orderByDesc('related_overlap')->orderByDesc('related_sales')->limit($limit)->get();
        $maxOverlap=max(1,count($relatedCodes));
        return $rows->map(static function(object $r) use($maxOverlap): array {
            $overlap=(int)$r->related_overlap; $sales=(float)$r->related_sales;
            $score=(int)round(min(100,45+($overlap/$maxOverlap)*35+min(20,log10(max(1,$sales))*2)));
            return ['customer_code'=>(string)$r->customer_code,'customer_name'=>(string)$r->customer_name,'salesperson_name'=>(string)$r->salesperson_name,'related_overlap'=>$overlap,'related_sales'=>$sales,'last_period'=>(string)$r->last_period,'opportunity_score'=>$score,'reason'=>"Membeli {$overlap} item terkait tetapi belum membeli item terpilih dalam 12 bulan."];
        })->all();
    }

    /** @param array<string,mixed> $data */
    public function saveReview(array $data): int
    {
        $now=now();
        return (int) DB::connection('sqlite_targets')->table('business_analytics_product_reviews')->insertGetId([
            'source'=>$data['source'],'item_code'=>$data['item_code'],'review_period'=>$data['review_period'],
            'internal_facts_json'=>json_encode($data['internal_facts'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'profile_snapshot_json'=>json_encode($data['profile'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'diagnosis_json'=>json_encode($data['diagnosis'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'external_research_json'=>isset($data['external_research'])?json_encode($data['external_research'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,
            'strategy_json'=>isset($data['strategy'])?json_encode($data['strategy'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,
            'research_status'=>$data['research_status']??'NOT_REQUESTED','created_by_user_id'=>$data['user_id']??null,'created_at'=>$now,'updated_at'=>$now,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function review(int $id): ?array
    {
        $row=DB::connection('sqlite_targets')->table('business_analytics_product_reviews')->where('id',$id)->first();
        if(!$row)return null; $a=(array)$row;
        foreach(['internal_facts_json'=>'internal_facts','profile_snapshot_json'=>'profile','diagnosis_json'=>'diagnosis','external_research_json'=>'external_research','strategy_json'=>'strategy'] as $from=>$to){$a[$to]=$a[$from]?json_decode((string)$a[$from],true):null;}
        return $a;
    }

    /** @param array<string,mixed> $research */
    public function updateReviewResearch(int $id,array $research,string $status='SUCCESS'): void
    {
        DB::connection('sqlite_targets')->table('business_analytics_product_reviews')->where('id',$id)->update(['external_research_json'=>json_encode($research,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'research_status'=>$status,'updated_at'=>now()]);
    }

    /** @param array<string,mixed> $strategy */
    public function updateReviewStrategy(int $id, array $strategy): void
    {
        DB::connection('sqlite_targets')->table('business_analytics_product_reviews')->where('id',$id)->update(['strategy_json'=>json_encode($strategy,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'updated_at'=>now()]);
    }

    /** @param array<string,mixed> $response */
    public function saveAdvisorMessage(int $reviewId,string $role,string $message,?array $response=null,?string $provider=null,?string $model=null,?int $userId=null): int
    {
        $now=now(); return (int)DB::connection('sqlite_targets')->table('business_analytics_advisor_messages')->insertGetId(['review_id'=>$reviewId,'role'=>$role,'message_text'=>$message,'response_json'=>$response?json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,'provider'=>$provider,'model'=>$model,'created_by_user_id'=>$userId,'created_at'=>$now,'updated_at'=>$now]);
    }

    /** @return list<array<string,mixed>> */
    public function advisorMessages(int $reviewId,int $limit=20): array
    {
        return DB::connection('sqlite_targets')->table('business_analytics_advisor_messages')->where('review_id',$reviewId)->orderBy('id')->limit($limit)->get()->map(static function(object $r):array{$a=(array)$r;$a['response']=$a['response_json']?json_decode((string)$a['response_json'],true):null;return $a;})->all();
    }

    /** @param array<string,mixed> $data */
    public function createExperiment(array $data): int
    {
        $now=now(); return (int)DB::connection('sqlite_targets')->table('business_analytics_experiments')->insertGetId([...$data,'created_at'=>$now,'updated_at'=>$now]);
    }

    /** @return array<string,mixed>|null */
    public function experiment(int $id): ?array
    {
        $row=DB::connection('sqlite_targets')->table('business_analytics_experiments')->where('id',$id)->first(); return $row?(array)$row:null;
    }

    /** @return list<array<string,mixed>> */
    public function experiments(string $itemCode,string $source): array
    {
        $q=DB::connection('sqlite_targets')->table('business_analytics_experiments')->where('item_code',$itemCode);
        if(strtoupper($source)!=='ALL')$q->whereIn('source',['ALL',strtoupper($source)]);
        return $q->orderByDesc('id')->limit(50)->get()->map(static fn(object $r):array=>(array)$r)->all();
    }

    /** @param array<string,mixed> $updates */
    public function updateExperiment(int $id,array $updates): void
    {
        $updates['updated_at']=now(); DB::connection('sqlite_targets')->table('business_analytics_experiments')->where('id',$id)->update($updates);
    }

    /** @return array{revenue:float,quantity:float,days:int,invoices:int} */
    public function dailyWindow(string $itemCode,string $source,string $start,string $end,string $channel='OMNICHANNEL'): array
    {
        $q=DB::connection('sqlite_targets')->table('business_analytics_daily_sales')->whereBetween('sales_date',[$start,$end]);
        if($itemCode!=='')$q->where(function(Builder $x)use($itemCode):void{$x->where('item_code',$itemCode)->orWhere(function(Builder $y)use($itemCode):void{$y->where('item_code','')->where('item_name',$itemCode);});});
        if(strtoupper($source)!=='ALL')$q->where('source',strtoupper($source));
        if(strtoupper($channel)!=='OMNICHANNEL')$this->applyChannel($q,$channel);
        $row=$q->selectRaw('COALESCE(SUM(sales_amount),0) AS revenue, COALESCE(SUM(quantity),0) AS quantity, COALESCE(SUM(invoice_count),0) AS invoices')->first();
        $days=(new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days+1;
        return ['revenue'=>(float)($row->revenue??0),'quantity'=>(float)($row->quantity??0),'invoices'=>(int)($row->invoices??0),'days'=>max(1,(int)$days)];
    }

    /** @return list<array<string,mixed>> */
    public function strategyMemory(string $itemCode): array
    {
        return DB::connection('sqlite_targets')->table('business_analytics_experiments')->where('item_code',$itemCode)->whereIn('status',['COMPLETED','SCALED','MODIFIED','STOPPED'])->whereNotNull('result_json')->orderByDesc('id')->limit(20)->get()->map(static function(object $r):array{$result=json_decode((string)$r->result_json,true)?:[];return ['channel'=>(string)$r->channel,'decision'=>(string)($r->decision??''),'observed_revenue_uplift'=>$result['observed_revenue_uplift']??null,'observed_quantity_uplift'=>$result['observed_quantity_uplift']??null];})->all();
    }

    private function productBase(string $itemCode,string $source): Builder
    {
        $q=DB::connection('sqlite_targets')->table('business_analytics_monthly_sales')->where(function(Builder $x)use($itemCode):void{$x->where('item_code',$itemCode)->orWhere(function(Builder $y)use($itemCode):void{$y->where('item_code','')->where('item_name',$itemCode);});});
        if(strtoupper($source)!=='ALL')$q->where('source',strtoupper($source)); return $q;
    }

    /** @return array<string,float> */
    private function customerSalesMap(string $itemCode,string $source,string $start,string $end): array
    {
        $rows=$this->productBase($itemCode,$source)->whereBetween('period',[$start,$end])->selectRaw("CASE WHEN customer_code='' THEN customer_name ELSE customer_code END AS customer_key, SUM(sales_amount) AS sales")->groupByRaw("CASE WHEN customer_code='' THEN customer_name ELSE customer_code END")->get();
        $out=[]; foreach($rows as $r){$k=trim((string)$r->customer_key);if($k!=='')$out[$k]=(float)$r->sales;} return $out;
    }

    private function channelSum(string $itemCode,string $source,string $start,string $end,string $channel): float
    {
        $q=$this->productBase($itemCode,$source)->whereBetween('period',[$start,$end]);$this->applyChannel($q,$channel);return(float)$q->sum('sales_amount');
    }

    private function applyChannel(Builder $q,string $channel): void
    {
        $patterns=['%ONLINE%','%E-COMMERCE%','%ECOMMERCE%','%MARKETPLACE%','%SHOPEE%','%TOKOPEDIA%','%TIKTOK%'];
        if(strtoupper($channel)==='ONLINE'){$q->where(function(Builder $x)use($patterns):void{foreach($patterns as$p)$x->orWhereRaw('UPPER(sales_line) LIKE ?',[$p]);});}
        else{$q->where(function(Builder $x)use($patterns):void{foreach($patterns as$p)$x->whereRaw('UPPER(sales_line) NOT LIKE ?',[$p]);});}
    }

    private function nullableText(mixed $value): ?string { $s=trim((string)($value??'')); return $s===''?null:$s; }
}
