<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SalesDataSource;
use App\Data\SalesDashboardFilter;
use App\Services\SalesDashboardService;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesDashboardServicePeriodDetailsTest extends TestCase
{
    public function test_it_fetches_normalized_details_for_an_arbitrary_period(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => [(object) [
                'InvoiceDate'=>'2026-06-22','InvoiceNumber'=>'INV-OLD','LiniPenjualan'=>'JAKARTA',
                'CustomerCode'=>'C001','CustomerName'=>'Customer A','SalesPersonName'=>'Sales A',
                'ItemCode'=>'ITEM-01','ItemName'=>'Hammer','Grade'=>'A','Quantity'=>2,
                'Discount'=>0,'Discount2'=>0,'LineTotal'=>500,'Discontinue'=>0,
                'StatusItem'=>'ACTIVE','StatusClearanceSale'=>'BUKAN CLEARANCE SALE','ClearanceSaleDiscount'=>0,
            ]],
            'SMI' => [],
        ]));
        $filter = SalesDashboardFilter::from('all', '2026-07-22', 'all');

        $rows = $this->app->make(SalesDashboardService::class)->detailsForPeriod(
            $filter,
            $filter->previousStartDate,
            $filter->previousEndDate,
        );

        self::assertCount(1, $rows);
        self::assertSame('INV-OLD', $rows[0]['invoice_number']);
        self::assertSame('JAKARTA', $rows[0]['line']);
        self::assertSame(500.0, $rows[0]['line_total']);
    }
}
