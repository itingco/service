<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use InvalidArgumentException;

final class ExperimentEvaluationService
{
    /**
     * @param array{revenue:float|int,quantity:float|int,days:int} $baseline
     * @param array{revenue:float|int,quantity:float|int,days:int} $result
     * @param array<string,float|int> $threshold
     * @return array<string,mixed>
     */
    public function evaluate(array $baseline, array $result, array $threshold): array
    {
        $baselineDays = max(1, (int) ($baseline['days'] ?? 1));
        $resultDays = max(1, (int) ($result['days'] ?? 1));
        $baseRevenueDaily = (float) ($baseline['revenue'] ?? 0.0) / $baselineDays;
        $resultRevenueDaily = (float) ($result['revenue'] ?? 0.0) / $resultDays;
        $baseQtyDaily = (float) ($baseline['quantity'] ?? 0.0) / $baselineDays;
        $resultQtyDaily = (float) ($result['quantity'] ?? 0.0) / $resultDays;

        if ($baseRevenueDaily <= 0.0 && $baseQtyDaily <= 0.0) {
            throw new InvalidArgumentException('Baseline experiment tidak memiliki data penjualan. Sync daily mart atau ubah baseline sebelum evaluasi.');
        }

        $revenueUplift = $baseRevenueDaily > 0.0 ? ($resultRevenueDaily / $baseRevenueDaily) - 1.0 : null;
        $qtyUplift = $baseQtyDaily > 0.0 ? ($resultQtyDaily / $baseQtyDaily) - 1.0 : null;
        $revenueMin = (float) ($threshold['revenue_uplift_min'] ?? 0.10);
        $qtyMin = (float) ($threshold['quantity_uplift_min'] ?? 0.05);

        if (($revenueUplift ?? -1.0) >= $revenueMin && ($qtyUplift ?? -1.0) >= $qtyMin) {
            $decision = 'SCALE';
        } elseif (($revenueUplift ?? -1.0) <= 0.0 && ($qtyUplift ?? -1.0) <= 0.0) {
            $decision = 'STOP';
        } else {
            $decision = 'MODIFY';
        }

        return [
            'baseline_revenue_per_day' => $baseRevenueDaily,
            'result_revenue_per_day' => $resultRevenueDaily,
            'baseline_quantity_per_day' => $baseQtyDaily,
            'result_quantity_per_day' => $resultQtyDaily,
            'observed_revenue_uplift' => $revenueUplift,
            'observed_quantity_uplift' => $qtyUplift,
            'decision' => $decision,
            'causal_claim_allowed' => false,
            'note' => 'Uplift adalah perbandingan observasional terhadap baseline; bukan bukti kausal kecuali experiment memiliki control yang valid.',
        ];
    }
}
