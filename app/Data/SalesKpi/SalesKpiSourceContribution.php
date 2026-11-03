<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesKpiSourceContribution
{
    public function __construct(
        public readonly string $database,
        public readonly ?float $target,
        public readonly bool $targetConfigured,
        public readonly float $currentOmzet,
        public readonly float $growthCurrentOmzet,
        public readonly float $previousOmzet,
        public readonly float $discontinuedOmzet,
        public readonly float $clearanceOmzet,
        public readonly float $gradeAOmzet,
        public readonly float $gradeBOmzet,
    ) {
    }

    public static function empty(string $database): self
    {
        return new self(
            database: $database,
            target: null,
            targetConfigured: false,
            currentOmzet: 0.0,
            growthCurrentOmzet: 0.0,
            previousOmzet: 0.0,
            discontinuedOmzet: 0.0,
            clearanceOmzet: 0.0,
            gradeAOmzet: 0.0,
            gradeBOmzet: 0.0,
        );
    }
}
