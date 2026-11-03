<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesKpiTargetCatalogRow
{
    public function __construct(
        public readonly string $line,
        public readonly string $lineKey,
        public readonly ?float $monthlyTarget,
        public readonly float $currentOmzet,
        public readonly bool $hasCurrentTransactions,
        public readonly bool $hasTarget,
    ) {
    }
}
