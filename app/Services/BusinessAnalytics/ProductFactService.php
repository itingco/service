<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class ProductFactService
{
    public function __construct(
        private readonly ProductAdvisorRepository $repository,
        private readonly ForecastEngine $forecast,
        private readonly ProductDiagnosisService $diagnosis,
    ) {}

    /** @return array<string,mixed> */
    public function review(string $itemCode, string $source, string $endMonth): array
    {
        $source = strtoupper($source);
        $trend = $this->repository->productMonthlyTrend($itemCode, $source, $endMonth, 36);
        $profile = $this->repository->profile($itemCode, $source)
            ?? $this->repository->profile($itemCode, 'ALL')
            ?? ['source' => $source, 'item_code' => $itemCode, 'item_name' => $itemCode, 'specifications' => ''];

        $sales1m = $this->sumTail($trend, 1);
        $sales3m = $this->sumTail($trend, 3);
        $sales6m = $this->sumTail($trend, 6);
        $sales12m = $this->sumTail($trend, 12);
        $sales36m = array_sum($trend);
        $previous3m = $this->sumSliceFromEnd($trend, 6, 3);
        $priorYear3m = $this->sumSliceFromEnd($trend, 15, 3);
        $previousMonth = $this->sumSliceFromEnd($trend, 2, 1);
        $priorYearMonth = $this->sumSliceFromEnd($trend, 13, 1);
        $momentum3m = $previous3m > 0 ? ($sales3m / $previous3m) - 1.0 : null;
        $yoyGrowth = $priorYear3m > 0 ? ($sales3m / $priorYear3m) - 1.0 : null;
        $momGrowth = $previousMonth > 0 ? ($sales1m / $previousMonth) - 1.0 : null;
        $monthYoy = $priorYearMonth > 0 ? ($sales1m / $priorYearMonth) - 1.0 : null;
        $channel = $this->repository->channelFacts($itemCode, $source, $endMonth);
        $customers = $this->repository->customerFacts($itemCode, $source, $endMonth);
        $volatility = $this->coefficientOfVariation(array_slice(array_values($trend), -12));

        $facts = [
            'item_code' => $itemCode,
            'item_name' => (string) ($profile['item_name'] ?? $itemCode),
            'source' => $source,
            'review_period' => $endMonth,
            'sales_1m' => $sales1m,
            'sales_3m' => $sales3m,
            'sales_6m' => $sales6m,
            'sales_12m' => $sales12m,
            'sales_36m' => $sales36m,
            'previous_3m_sales' => $previous3m,
            'prior_year_3m_sales' => $priorYear3m,
            'mom_growth' => $momGrowth,
            'month_yoy_growth' => $monthYoy,
            'momentum_3m' => $momentum3m,
            'yoy_growth' => $yoyGrowth,
            'volatility_cv' => $volatility,
            ...$channel,
            ...$customers,
            'top_lines' => $this->repository->breakdown($itemCode, $source, $endMonth, 'line'),
            'top_salespersons' => $this->repository->breakdown($itemCode, $source, $endMonth, 'salesperson'),
            'related_items' => $this->repository->relatedItems($itemCode, $source, $endMonth),
            'cross_sell_candidates' => $this->repository->crossSellCandidates($itemCode, $source, $endMonth),
            'monthly_trend' => $trend,
            'forecast' => $this->forecast->forecast($trend, 3),
            'forecast_backtest' => $this->forecast->backtest($trend, 6),
        ];
        $facts['diagnosis'] = $this->diagnosis->diagnose($facts);
        $facts['profile'] = $profile;
        return $facts;
    }

    /** @param array<string,float> $trend */
    private function sumTail(array $trend, int $count): float
    {
        return array_sum(array_slice(array_values($trend), -$count));
    }

    /** @param array<string,float> $trend */
    private function sumSliceFromEnd(array $trend, int $offsetFromEnd, int $length): float
    {
        $values = array_values($trend);
        $start = count($values) - $offsetFromEnd;
        if ($start < 0) return 0.0;
        return array_sum(array_slice($values, $start, $length));
    }

    /** @param list<float|int> $values */
    private function coefficientOfVariation(array $values): ?float
    {
        $values = array_values(array_filter(array_map('floatval', $values), static fn(float $v): bool => $v >= 0.0));
        if (count($values) < 4) return null;
        $mean = array_sum($values) / count($values);
        if ($mean <= 0.0) return null;
        $variance = array_sum(array_map(static fn(float $v): float => ($v - $mean) ** 2, $values)) / max(1, count($values) - 1);
        return sqrt($variance) / $mean;
    }
}
