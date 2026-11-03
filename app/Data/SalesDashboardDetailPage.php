<?php

declare(strict_types=1);

namespace App\Data;

final class SalesDashboardDetailPage
{
    /** @param list<array<string,mixed>> $rows */
    public function __construct(
        public readonly int $recordsTotal,
        public readonly int $recordsFiltered,
        public readonly array $rows,
    ) {
    }
}
