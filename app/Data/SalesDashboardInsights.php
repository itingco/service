<?php

declare(strict_types=1);

namespace App\Data;

final class SalesDashboardInsights
{
    /**
     * @param list<array<string,mixed>> $linePaces
     * @param list<array<string,mixed>> $actions
     * @param list<array<string,mixed>> $topAchievement
     * @param list<array<string,mixed>> $bottomAchievement
     * @param list<array<string,mixed>> $topGrowth
     * @param list<array<string,mixed>> $bottomGrowth
     * @param list<array<string,mixed>> $topCustomers
     * @param list<array<string,mixed>> $topSalespersons
     * @param list<array<string,mixed>> $topItems
     * @param array<string,array{label:string,sales:float,percentage:?float}> $gradeMix
     * @param array<string,array{label:string,sales:float,percentage:?float}> $statusMix
     * @param array{labels:list<string>,actual_daily:list<float>,actual_cumulative:list<float>,target_cumulative:list<float>} $dailyTrend
     */
    public function __construct(
        public readonly float $totalTarget,
        public readonly float $totalMtd,
        public readonly float $remainingTarget,
        public readonly float $forecastEom,
        public readonly ?float $forecastPercentage,
        public readonly int $onTrackCount,
        public readonly int $atRiskCount,
        public readonly int $targetMissingCount,
        public readonly array $linePaces,
        public readonly array $actions,
        public readonly array $topAchievement,
        public readonly array $bottomAchievement,
        public readonly array $topGrowth,
        public readonly array $bottomGrowth,
        public readonly array $topCustomers,
        public readonly array $topSalespersons,
        public readonly array $topItems,
        public readonly ?float $topFiveCustomerConcentration,
        public readonly array $gradeMix,
        public readonly array $statusMix,
        public readonly array $dailyTrend,
    ) {
    }

    /** @return array<string,mixed> */
    public function chartData(): array
    {
        return [
            'line_labels' => array_column($this->linePaces, 'line'),
            'achievement' => array_map(
                static fn (array $row): ?float => $row['achievement_percentage'] === null
                    ? null
                    : (float) $row['achievement_percentage'] * 100,
                $this->linePaces,
            ),
            'gaps' => array_map(
                static fn (array $row): float => (float) $row['remaining_target'],
                $this->linePaces,
            ),
            'daily_trend' => $this->dailyTrend,
        ];
    }
}
