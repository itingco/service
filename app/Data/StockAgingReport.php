<?php

declare(strict_types=1);

namespace App\Data;

final class StockAgingReport
{
    /**
     * @param array<string,array<string,mixed>> $sources
     * @param list<array<string,mixed>> $detailRows
     * @param array<string,mixed> $grandTotal
     */
    public function __construct(
        public readonly StockAgingFilter $filter,
        public readonly array $sources,
        public readonly array $detailRows,
        public readonly array $grandTotal,
        public readonly float $over120Stock,
        public readonly int $totalItems,
        public readonly int $totalWarehouses,
    ) {
    }

    public static function empty(StockAgingFilter $filter): self
    {
        return new self(
            filter: $filter,
            sources: [],
            detailRows: [],
            grandTotal: [
                'item_count' => 0,
                'qty' => 0.0,
                'sisa_stock' => 0.0,
                'sales_mtd' => 0.0,
                'ratio' => null,
            ],
            over120Stock: 0.0,
            totalItems: 0,
            totalWarehouses: 0,
        );
    }
}
