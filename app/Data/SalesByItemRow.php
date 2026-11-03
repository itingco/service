<?php

declare(strict_types=1);

namespace App\Data;

final class SalesByItemRow
{
    public function __construct(
        public readonly string $itemCode,
        public readonly string $itemName,
        public readonly float $totalQuantity,
        public readonly float $totalSales,
        public readonly float $averagePrice,
        public readonly float $contribution,
        public readonly string $source,
    ) {
    }
}
