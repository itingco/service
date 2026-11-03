<?php

declare(strict_types=1);

namespace App\Data;

final class SalesDashboardReport
{
    /**
     * @param list<SalesDashboardLineRow> $rows
     * @param list<array<string,mixed>> $detailRows
     */
    public function __construct(
        public readonly SalesDashboardFilter $filter,
        public readonly array $rows,
        public readonly SalesDashboardLineRow $totalRow,
        public readonly array $detailRows = [],
    ) {
    }

    public static function empty(SalesDashboardFilter $filter): self
    {
        $total = new SalesDashboardLineRow(
            line: 'TOTAL',
            monthlyTarget: 0.0,
            dailyTarget: 0.0,
            requiredDailySales: 0.0,
            dtd: 0.0,
            ldtd: 0.0,
            dtdGrowth: null,
            mtd: 0.0,
            lmtd: 0.0,
            mtdGrowth: null,
            averageDailySales: 0.0,
            estimatedEom: 0.0,
            estimatedEomPercentage: null,
            achievementPercentage: null,
            gap: 0.0,
            contributionPercentage: null,
        );

        return new self($filter, [], $total, []);
    }
}
