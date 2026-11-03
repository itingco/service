<?php

declare(strict_types=1);

namespace App\Contracts;

use DateTimeImmutable;

interface SalesDataSource
{
    /** @return list<object|array<string,mixed>> */
    public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array;
}
