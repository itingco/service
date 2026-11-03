<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesKpiDataset
{
    /**
     * @param list<SalesKpiTransaction> $current
     * @param list<SalesKpiTransaction> $growthCurrent
     * @param list<SalesKpiTransaction> $growthPrevious
     */
    public function __construct(
        public readonly array $current,
        public readonly array $growthCurrent,
        public readonly array $growthPrevious,
    ) {
    }

    /** @param list<self> $datasets */
    public static function merge(array $datasets): self
    {
        $current = [];
        $growthCurrent = [];
        $growthPrevious = [];

        foreach ($datasets as $dataset) {
            $current = array_merge($current, $dataset->current);
            $growthCurrent = array_merge($growthCurrent, $dataset->growthCurrent);
            $growthPrevious = array_merge($growthPrevious, $dataset->growthPrevious);
        }

        return new self($current, $growthCurrent, $growthPrevious);
    }
}
