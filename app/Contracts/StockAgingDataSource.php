<?php

declare(strict_types=1);

namespace App\Contracts;

use DateTimeImmutable;

interface StockAgingDataSource
{
    /** @return list<object|array<string,mixed>> */
    public function fetch(string $source, DateTimeImmutable $date): array;
}
