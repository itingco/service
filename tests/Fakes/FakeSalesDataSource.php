<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\SalesDataSource;
use App\Exceptions\SalesDataException;
use DateTimeImmutable;

final class FakeSalesDataSource implements SalesDataSource
{
    /**
     * @param array<string,list<object|array<string,mixed>>> $rows
     * @param array<string,string> $failures
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $failures = [],
    ) {
    }

    public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        if (isset($this->failures[$source])) {
            throw new SalesDataException($source, $this->failures[$source]);
        }

        return $this->rows[$source] ?? [];
    }
}
