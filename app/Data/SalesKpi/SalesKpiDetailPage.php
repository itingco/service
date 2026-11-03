<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesKpiDetailPage
{
    /** @param list<array<string,string|float|bool>> $rows */
    public function __construct(
        public readonly int $recordsTotal,
        public readonly int $recordsFiltered,
        public readonly array $rows,
    ) {
    }
}
