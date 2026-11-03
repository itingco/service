<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesKpiTransaction
{
    public function __construct(
        public readonly string $database,
        public readonly string $line,
        public readonly string $lineKey,
        public readonly float $lineTotal,
        public readonly bool $discontinued,
        public readonly bool $clearance,
        public readonly string $grade,
        public readonly float $quantity,
        public readonly string $invoiceDate,
        public readonly string $invoiceNumber,
        public readonly string $itemCode,
        public readonly string $itemName,
        public readonly string $itemStatus,
        public readonly string $clearanceStatus,
        public readonly string $clearanceDiscount,
    ) {
    }
}
