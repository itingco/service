<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesInsightsPageTest extends TestCase
{
    public function test_advanced_page_contains_management_and_rd_analytics(): void
    {
        $rows = [(object) [
            'InvoiceDate'=>'2026-07-22','InvoiceNumber'=>'INV-001','LiniPenjualan'=>'ONLINE',
            'CustomerCode'=>'C001','CustomerName'=>'Customer A','SalesPersonName'=>'Sales A',
            'ItemCode'=>'ITEM-01','ItemName'=>'Hammer','Grade'=>'A','Quantity'=>3,
            'Discount'=>5,'Discount2'=>0,'LineTotal'=>1_000_000,'Discontinue'=>0,
            'StatusItem'=>'ACTIVE','StatusClearanceSale'=>'BUKAN CLEARANCE SALE','ClearanceSaleDiscount'=>0,
        ]];
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource(['INGCO'=>$rows,'SMI'=>[]]));

        $response = $this->actingAsDashboardAdmin()->get('/sales-insights?database=all&end_date=2026-07-22&item_status=all');

        $response->assertOk();
        $response->assertSee('Sales Insights &amp; R&amp;D Dashboard', false);
        $response->assertSee('Management Control &amp; Action Center', false);
        $response->assertSee('R&amp;D Insight — Product Demand &amp; Portfolio Direction', false);
        $response->assertSee('Product Momentum &amp; Rekomendasi R&amp;D', false);
        $response->assertSee('Demand Baru');
        $response->assertSee('Demand Hilang');
        $response->assertSee('Discount Effectiveness Watch');
        $response->assertSee('Channel–Product Fit');
        $response->assertSee('Kembali ke Sales Dashboard');
        $response->assertSee('sales-dashboard-insights.js', false);
    }
}
