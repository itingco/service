<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesDashboardPageTest extends TestCase
{
    public function test_operational_dashboard_remains_familiar_and_links_to_advanced_insights(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => [(object) [
                'InvoiceDate'=>'2026-07-22','InvoiceNumber'=>'INV-001','LiniPenjualan'=>'JAKARTA',
                'CustomerCode'=>'C001','CustomerName'=>'Customer A','SalesPersonName'=>'Sales A',
                'ItemCode'=>'ITEM-01','ItemName'=>'Hammer','Grade'=>'A','Quantity'=>1,
                'LineTotal'=>1_000_000,'Discontinue'=>0,'StatusItem'=>'ACTIVE',
                'StatusClearanceSale'=>'BUKAN CLEARANCE SALE','ClearanceSaleDiscount'=>0,
            ]],
            'SMI' => [],
        ]));

        $response = $this->actingAsDashboardAdmin()->get('/sales-dashboard?database=all&end_date=2026-07-22&item_status=all');

        $response->assertOk();
        $response->assertSee('Monthly Sales');
        $response->assertSee('JAKARTA');
        $response->assertSee('Data Mentah Penjualan');
        $response->assertSee('Dashboard Analisis Lanjutan');
        $response->assertSee('/sales-insights?database=all', false);
        $response->assertDontSee('Management Control &amp; Action Center', false);
        $response->assertDontSee('R&amp;D Insight', false);
        $response->assertDontSee('window.salesDashboardInsightData', false);
    }
}
