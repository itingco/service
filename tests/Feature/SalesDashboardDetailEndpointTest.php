<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesDashboardDetailEndpointTest extends TestCase
{
    public function test_raw_detail_endpoint_searches_sorts_and_returns_contribution(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => [
                $this->row('INV-001', 'JAKARTA', 'HAMMER', 300),
                $this->row('INV-002', 'GERAI BOGOR', 'DRILL', 100),
            ],
            'SMI' => [],
        ]));

        $response = $this->actingAsDashboardAdmin()->getJson('/sales-dashboard/details?'.http_build_query([
            'database' => 'all',
            'end_date' => '2026-07-22',
            'item_status' => 'all',
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'order_column' => 'line_total',
            'order_dir' => 'desc',
            'search' => 'HAMMER',
        ]));

        $response->assertOk()
            ->assertJsonPath('draw', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-001')
            ->assertJsonPath('data.0.database', 'AS_INGCO')
            ->assertJsonPath('data.0.line', 'JAKARTA')
            ->assertJsonPath('data.0.line_contribution', 0.75);
    }

    private function row(string $invoice, string $line, string $item, int $total): object
    {
        return (object) [
            'InvoiceDate' => '2026-07-22',
            'InvoiceNumber' => $invoice,
            'LiniPenjualan' => $line,
            'CustomerCode' => 'C001',
            'CustomerName' => 'Customer A',
            'SalesPersonName' => 'Sales A',
            'ItemCode' => $item,
            'ItemName' => $item,
            'Grade' => 'A',
            'Quantity' => 1,
            'LineTotal' => $total,
            'Discontinue' => 0,
            'StatusItem' => 'ACTIVE',
            'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
            'ClearanceSaleDiscount' => 0,
        ];
    }
}
