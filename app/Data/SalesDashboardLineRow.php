<?php

declare(strict_types=1);

namespace App\Data;

final class SalesDashboardLineRow
{
    public function __construct(
        public readonly string $line,
        public readonly float $monthlyTarget,
        public readonly float $dailyTarget,
        public readonly float $requiredDailySales,
        public readonly float $dtd,
        public readonly float $ldtd,
        public readonly ?float $dtdGrowth,
        public readonly float $mtd,
        public readonly float $lmtd,
        public readonly ?float $mtdGrowth,
        public readonly float $averageDailySales,
        public readonly float $estimatedEom,
        public readonly ?float $estimatedEomPercentage,
        public readonly ?float $achievementPercentage,
        public readonly float $gap,
        public readonly ?float $contributionPercentage,
    ) {
    }
}
