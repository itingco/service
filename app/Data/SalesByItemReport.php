<?php

declare(strict_types=1);

namespace App\Data;

final class SalesByItemReport
{
    /**
     * @param list<SalesByItemRow> $rows
     */
    public function __construct(
        public readonly SalesByItemFilter $filter,
        public readonly array $rows,
        public readonly float $totalQuantity,
        public readonly float $totalSales,
        public readonly int $totalItems,
        public readonly float $averageSalesPerItem,
    ) {
    }

    public static function empty(SalesByItemFilter $filter): self
    {
        return new self($filter, [], 0.0, 0.0, 0, 0.0);
    }
}
