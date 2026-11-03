<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesKpiDetailEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_combined_detail_endpoint_returns_source_and_datatable_shape(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => [$this->row('ING-001', 'Hammer INGCO', 100)],
            'SMI' => [$this->row('SMI-001', 'Hammer SMI', 200)],
        ]));

        $response = $this->actingAsDashboardAdmin()->getJson(
            '/sales-kpi/details?end_date=2026-07-20&discontinue=all&clearance=all&grade=ALL&detail_type=all&draw=3&start=0&length=10&order_column=database&order_dir=asc',
        );

        $response->assertOk()->assertJsonPath('draw', 3);
        $response->assertJsonPath('data.0.database', 'AS_INGCO');
        $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_combined_detail_failure_returns_safe_503_with_failed_database(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource(
            ['INGCO' => [$this->row('ING-001', 'Hammer INGCO', 100)]],
            ['SMI' => 'password=secret SQLSTATE server=10.9.2.18'],
        ));

        $response = $this->actingAsDashboardAdmin()->getJson(
            '/sales-kpi/details?end_date=2026-07-20&discontinue=all&clearance=all&grade=ALL&detail_type=all&draw=3&start=0&length=10&order_column=database&order_dir=asc',
        );

        $response->assertStatus(503);
        $response->assertJsonPath('failed_databases.0', 'AS_SMI');
        $response->assertJsonPath(
            'message',
            "Laporan KPI gabungan tidak dapat dimuat.\n\nKoneksi ke database AS_SMI gagal.\nData database lainnya tidak ditampilkan agar hasil gabungan tidak menyesatkan.",
        );
        $response->assertDontSee('password=secret');
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('10.9.2.18');
    }

    public function test_invalid_sort_column_is_rejected(): void
    {
        $this->actingAsDashboardAdmin()->get(
            '/sales-kpi/details?end_date=2026-07-20&discontinue=all&clearance=all&grade=ALL&detail_type=all&draw=1&start=0&length=10&order_column=password&order_dir=asc',
        )->assertSessionHasErrors('order_column');
    }

    private function row(string $invoice, string $itemName, float $total): object
    {
        return (object) [
            'InvoiceDate' => '2026-07-20',
            'InvoiceNumber' => $invoice,
            'LiniPenjualan' => 'JAKARTA',
            'ItemCode' => $invoice,
            'ItemName' => $itemName,
            'Quantity' => 1,
            'LineTotal' => $total,
            'Discontinue' => 0,
            'StatusItem' => 'ACTIVE',
            'Grade' => 'A',
            'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
            'ClearanceSaleDiscount' => 0,
        ];
    }
}
