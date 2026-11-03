<?php

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use App\Data\SalesByItemFilter;
use App\Services\SalesByItemAggregator;
use App\Services\SalesByItemService;
use App\Support\SalesRowNormalizer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SalesByItemServiceTest extends TestCase
{
    public function test_all_database_requests_both_sources(): void
    {
        $source = new class implements SalesDataSource {
            public array $called = [];

            public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
            {
                $this->called[] = $source;
                return [(object) ['ItemCode' => 'A', 'ItemName' => 'Bor', 'Quantity' => 1, 'LineTotal' => 10]];
            }
        };

        $service = new SalesByItemService($source, new SalesRowNormalizer(), new SalesByItemAggregator());
        $report = $service->generate(SalesByItemFilter::from('all', '2026-07-22'));

        self::assertSame(['INGCO', 'SMI'], $source->called);
        self::assertSame(20.0, $report->totalSales);
    }
}
