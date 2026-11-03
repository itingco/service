<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

use DateTimeImmutable;

final class SalesKpiPeriod
{
    public function __construct(
        public readonly DateTimeImmutable $currentStart,
        public readonly DateTimeImmutable $currentEnd,
        public readonly DateTimeImmutable $growthCurrentStart,
        public readonly DateTimeImmutable $growthCurrentEnd,
        public readonly DateTimeImmutable $growthPreviousStart,
        public readonly DateTimeImmutable $growthPreviousEnd,
    ) {
    }

    public function requiresSeparateGrowthCurrentQuery(): bool
    {
        return $this->growthCurrentEnd != $this->currentEnd;
    }
}
