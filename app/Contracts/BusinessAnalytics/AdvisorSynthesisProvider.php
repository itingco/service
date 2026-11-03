<?php

declare(strict_types=1);

namespace App\Contracts\BusinessAnalytics;

interface AdvisorSynthesisProvider
{
    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function synthesize(array $context, string $question): array;
    public function name(): string;
    public function model(): ?string;
}
