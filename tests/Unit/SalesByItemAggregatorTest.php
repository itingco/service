<?php

namespace Tests\Unit;

use App\Data\SalesByItemFilter;
use App\Services\SalesByItemAggregator;
use PHPUnit\Framework\TestCase;

class SalesByItemAggregatorTest extends TestCase
{
    public function test_it_combines_sources_and_calculates_totals(): void
    {
        $report = (new SalesByItemAggregator())->aggregate(
            SalesByItemFilter::from('all', '2026-07-22'),
            [
                ['item_code' => 'A', 'item_name' => 'Bor', 'quantity' => 2.0, 'sales' => 100.0, 'source' => 'INGCO'],
                ['item_code' => 'A', 'item_name' => 'Bor', 'quantity' => 1.0, 'sales' => 50.0, 'source' => 'SMI'],
                ['item_code' => 'B', 'item_name' => 'Gerinda', 'quantity' => 5.0, 'sales' => 50.0, 'source' => 'INGCO'],
            ]
        );

        self::assertSame(2, $report->totalItems);
        self::assertSame(200.0, $report->totalSales);
        self::assertSame('INGCO & SMI', $report->rows[0]->source);
        self::assertSame(75.0, $report->rows[0]->contribution);
    }
}
