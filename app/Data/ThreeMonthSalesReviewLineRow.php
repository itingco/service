<?php

declare(strict_types=1);

namespace App\Data;

final class ThreeMonthSalesReviewLineRow
{
    public function __construct(
        public readonly string $line,
        public readonly float $firstMonthSales,
        public readonly float $secondMonthSales,
        public readonly ?float $secondMonthGrowth,
        public readonly float $currentMonthSales,
        public readonly ?float $currentMonthGrowth,
        public readonly float $totalSales,
        public readonly float $averageMonthlySales,
        public readonly ?float $threeMonthGrowth,
        public readonly ?float $contributionPercentage,
        public readonly string $trend,
    ) {
    }
}
