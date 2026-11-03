<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesKpiErrorHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_kpi_connection_failure_names_database_without_exposing_driver_details(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource(
            ['INGCO' => [$this->row()]],
            ['SMI' => 'SQLSTATE password=secret server=10.9.2.18'],
        ));

        $response = $this->actingAsDashboardAdmin()->get('/sales-kpi?end_date=2026-07-28');

        $response->assertOk();
        $response->assertSee('Koneksi ke database AS_SMI gagal.');
        $response->assertSee('Data database lainnya tidak ditampilkan agar hasil gabungan tidak menyesatkan.');
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('password=secret');
        $response->assertDontSee('10.9.2.18');
    }

    public function test_target_settings_show_safe_message_when_sqlite_is_unavailable(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => [],
            'SMI' => [],
        ]));

        DB::purge('sqlite_targets');
        config()->set('database.connections.sqlite_targets.database', base_path('storage/framework'));

        $response = $this->actingAsDashboardAdmin()->get('/sales-kpi/targets');

        $response->assertOk();
        $response->assertSee('Pengaturan target belum dapat dibuka.');
        $response->assertSee('Database konfigurasi lokal tidak tersedia.');
        $response->assertDontSee('SQLSTATE');
    }

    private function row(): object
    {
        return (object) [
            'InvoiceDate' => '2026-07-20',
            'InvoiceNumber' => 'INV-001',
            'LiniPenjualan' => 'JAKARTA',
            'ItemCode' => 'ITEM-1',
            'ItemName' => 'Item',
            'Quantity' => 1,
            'LineTotal' => 100,
            'Discontinue' => 0,
            'StatusItem' => 'ACTIVE',
            'Grade' => 'A',
            'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
            'ClearanceSaleDiscount' => 0,
        ];
    }
}
