<?php

declare(strict_types=1);

namespace App\Data;

final class ThreeMonthSalesReviewReport
{
    /** @param list<ThreeMonthSalesReviewLineRow> $rows */
    public function __construct(
        public readonly ThreeMonthSalesReviewFilter $filter,
        public readonly array $rows,
        public readonly ThreeMonthSalesReviewLineRow $totalRow,
        public readonly ?ThreeMonthSalesReviewLineRow $topContributor,
    ) {
    }

    public static function empty(ThreeMonthSalesReviewFilter $filter): self
    {
        $total = new ThreeMonthSalesReviewLineRow(
            line: 'TOTAL',
            firstMonthSales: 0.0,
            secondMonthSales: 0.0,
            secondMonthGrowth: null,
            currentMonthSales: 0.0,
            currentMonthGrowth: null,
            totalSales: 0.0,
            averageMonthlySales: 0.0,
            threeMonthGrowth: null,
            contributionPercentage: null,
            trend: 'stabil',
        );

        return new self($filter, [], $total, null);
    }
}
