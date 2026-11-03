<?php

declare(strict_types=1);

namespace App\Contracts;

interface SalesKpiTargetLookup
{
    public function targetForLine(string $line): ?float;

    /** @return array<string,?float> */
    public function all(): array;
}
