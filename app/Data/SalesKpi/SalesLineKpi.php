<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

final class SalesLineKpi
{
    /** @param array<string,SalesKpiSourceContribution> $sources */
    public function __construct(
        public readonly string $line,
        public readonly string $lineKey,
        public readonly array $sources,
        public readonly bool $targetConfigured,
        public readonly ?float $target,
        public readonly float $currentOmzet,
        public readonly float $growthCurrentOmzet,
        public readonly float $previousOmzet,
        public readonly ?float $growthRate,
        public readonly float $discontinuedOmzet,
        public readonly float $clearanceOmzet,
        public readonly float $riskRealization,
        public readonly ?float $riskTarget,
        public readonly float $gradeAOmzet,
        public readonly float $gradeBOmzet,
        public readonly ?float $gradeAContribution,
        public readonly ?float $gradeBContribution,
        public readonly ?float $targetScore,
        public readonly ?float $growthScore,
        public readonly ?float $riskScore,
        public readonly ?float $gradeAScore,
        public readonly ?float $gradeBScore,
        public readonly float $earnedPoints,
        public readonly float $availableMaximum,
        public readonly ?float $normalizedScore,
        public readonly string $status,
    ) {
    }

    public function source(string $database): SalesKpiSourceContribution
    {
        return $this->sources[$database] ?? SalesKpiSourceContribution::empty($database);
    }

    public function gradeScore(): ?float
    {
        return $this->gradeAScore === null || $this->gradeBScore === null
            ? null
            : $this->gradeAScore + $this->gradeBScore;
    }

    public function hasTarget(): bool
    {
        return $this->targetConfigured && $this->target !== null && $this->target > 0.0;
    }

    public function targetThreshold(): ?float
    {
        return $this->hasTarget() ? $this->target * 0.70 : null;
    }

    public function targetAchievement(): ?float
    {
        return $this->hasTarget() ? $this->currentOmzet / $this->target : null;
    }

    public function gapToThreshold(): ?float
    {
        $threshold = $this->targetThreshold();

        return $threshold === null ? null : max($threshold - $this->currentOmzet, 0.0);
    }

    public function gapToTarget(): ?float
    {
        return $this->hasTarget() ? max($this->target - $this->currentOmzet, 0.0) : null;
    }

    public function growthDifference(): float
    {
        return $this->growthCurrentOmzet - $this->previousOmzet;
    }

    public function riskAchievement(): ?float
    {
        return $this->riskTarget !== null && $this->riskTarget > 0.0
            ? $this->riskRealization / $this->riskTarget
            : null;
    }
}
