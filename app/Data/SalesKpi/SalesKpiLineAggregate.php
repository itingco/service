<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesKpiLineAggregate
{
    /** @param array<string,SalesKpiSourceContribution> $sources */
    public function __construct(
        public readonly string $line,
        public readonly string $lineKey,
        public readonly array $sources,
    ) {
    }

    public function source(string $database): SalesKpiSourceContribution
    {
        return $this->sources[$database] ?? SalesKpiSourceContribution::empty($database);
    }

    public function currentOmzet(): float
    {
        return $this->sum(static fn (SalesKpiSourceContribution $source): float => $source->currentOmzet);
    }

    public function growthCurrentOmzet(): float
    {
        return $this->sum(static fn (SalesKpiSourceContribution $source): float => $source->growthCurrentOmzet);
    }

    public function previousOmzet(): float
    {
        return $this->sum(static fn (SalesKpiSourceContribution $source): float => $source->previousOmzet);
    }

    public function discontinuedOmzet(): float
    {
        return $this->sum(static fn (SalesKpiSourceContribution $source): float => $source->discontinuedOmzet);
    }

    public function clearanceOmzet(): float
    {
        return $this->sum(static fn (SalesKpiSourceContribution $source): float => $source->clearanceOmzet);
    }

    public function gradeAOmzet(): float
    {
        return $this->sum(static fn (SalesKpiSourceContribution $source): float => $source->gradeAOmzet);
    }

    public function gradeBOmzet(): float
    {
        return $this->sum(static fn (SalesKpiSourceContribution $source): float => $source->gradeBOmzet);
    }

    private function sum(callable $accessor): float
    {
        $total = 0.0;
        foreach ($this->sources as $source) {
            $total += (float) $accessor($source);
        }

        return $total;
    }
}
