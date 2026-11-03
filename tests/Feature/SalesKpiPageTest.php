<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use App\Exceptions\SalesDataException;
use App\Models\SalesKpiTarget;
use DateTimeImmutable;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesKpiPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->actingAsDashboardAdmin()
            ->get('/sales-kpi?end_date=20-07-2026')
            ->assertSessionHasErrors('end_date');
    }

    public function test_database_parameter_is_ignored_and_not_rendered(): void
    {
        $this->bindRows([], []);

        $response = $this->actingAsDashboardAdmin()
            ->get('/sales-kpi?database=ingco&end_date=2026-07-20');

        $response->assertOk();
        $response->assertDontSee('name="database"', false);
        $response->assertSee('AS_INGCO + AS_SMI');
    }

    public function test_valid_request_renders_combined_hybrid_tables_and_detail_charts(): void
    {
        SalesKpiTarget::query()->create(['sales_line' => 'JAKARTA', 'monthly_target' => 500_000_000]);
        $this->bindRows($this->validRows(), [$this->row('JAKARTA', 150_000_000, 'SMI-001')]);

        $response = $this->actingAsDashboardAdmin()->get('/sales-kpi?end_date=2026-07-20');

        $response->assertOk();
        $response->assertSee('Ringkasan KPI');
        $response->assertSee('Target Omzet');
        $response->assertSee('Growth vs Last Month');
        $response->assertSee('Discontinued &amp; Clearance', false);
        $response->assertSee('Grade A &amp; Grade B', false);
        $response->assertSee('JAKARTA');
        $response->assertSee('NEW LINE');
        $response->assertSee('Target belum diatur untuk LiniPenjualan: NEW LINE');
        $response->assertSee('Maksimum skor sistem: 90 poin');
        $response->assertSee('Skor KPI Keseluruhan');
        $response->assertDontSee('Target AS_INGCO');
        $response->assertDontSee('Target AS_SMI');
        $response->assertSee('Target Gabungan');
        $response->assertSee('Omzet AS_INGCO');
        $response->assertSee('Omzet AS_SMI');
        $response->assertSee('Omzet Gabungan');
        $response->assertSee('id="kpiContributionPieChart"', false);
        $response->assertSee('id="kpiSummaryBarChart"', false);
        $response->assertDontSee('id="overallKpiChart"', false);
        $response->assertDontSee('id="componentKpiChart"', false);
        $response->assertSee('id="targetKpiChart"', false);
        $response->assertSee('id="growthKpiChart"', false);
        $response->assertSee('id="riskKpiChart"', false);
        $response->assertSee('id="gradeKpiChart"', false);
        $response->assertSee('data-kpi-recap-table', false);
        $response->assertSee('data-kpi-detail-table', false);
        $response->assertSee('window.salesKpiData =', false);
        $response->assertSee('sales-kpi-datatable.js', false);
        $response->assertSee('Export Semua Hasil ke Excel');
        $response->assertSee('Filter LiniPenjualan dan Detail');
        $response->assertSee('Pengaturan Target');
    }

    public function test_line_filter_shows_only_selected_line_in_kpi_tables_and_chart_data(): void
    {
        SalesKpiTarget::query()->create(['sales_line' => 'JAKARTA', 'monthly_target' => 500_000_000]);
        $this->bindRows($this->validRows(), []);

        $response = $this->actingAsDashboardAdmin()->get(
            '/sales-kpi?end_date=2026-07-20&line=JAKARTA&discontinue=all&clearance=all&grade=ALL',
        );

        $response->assertOk();
        $response->assertSee('<td class="sticky-line">JAKARTA</td>', false);
        $response->assertDontSee('<td class="sticky-line">NEW LINE</td>', false);
        $response->assertSee('<option value="NEW LINE">NEW LINE</option>', false);
    }

    public function test_combined_failure_names_smi_and_hides_partial_ingco_data(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource(
            ['INGCO' => [$this->row('JAKARTA', 300_000_000)]],
            ['SMI' => 'password=secret SQLSTATE server=10.9.2.18'],
        ));

        $response = $this->actingAsDashboardAdmin()->get('/sales-kpi?end_date=2026-07-20');

        $response->assertOk();
        $response->assertSee('Koneksi ke database AS_SMI gagal.');
        $response->assertSee('Data database lainnya tidak ditampilkan agar hasil gabungan tidak menyesatkan.');
        $response->assertDontSee('Rp 300.000.000');
        $response->assertDontSee('password=secret');
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('10.9.2.18');
    }

    public function test_combined_failure_names_both_failed_databases(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource(
            [],
            ['INGCO' => 'password=ingco', 'SMI' => 'password=smi'],
        ));

        $response = $this->actingAsDashboardAdmin()->get('/sales-kpi?end_date=2026-07-20');

        $response->assertOk();
        $response->assertSee('Koneksi ke database AS_INGCO dan AS_SMI gagal.');
        $response->assertDontSee('password=');
    }

    public function test_empty_data_renders_an_empty_state(): void
    {
        $this->bindRows([], []);

        $this->actingAsDashboardAdmin()
            ->get('/sales-kpi?end_date=2026-07-20')
            ->assertOk()
            ->assertSee('Tidak ada data penjualan pada periode yang dipilih.');
    }

    public function test_missing_column_is_reported_without_a_stack_trace(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => [(object) [
                'LiniPenjualan' => 'JAKARTA',
                'LineTotal' => 100,
                'Discontinue' => 0,
                'Grade' => 'A',
            ]],
            'SMI' => [],
        ]));

        $response = $this->actingAsDashboardAdmin()->get('/sales-kpi?end_date=2026-07-20');

        $response->assertOk();
        $response->assertSee('Kolom StatusClearanceSale tidak ditemukan pada hasil fn_PenjualanLini.');
        $response->assertDontSee('Stack trace');
    }

    /** @param list<object> $ingco @param list<object> $smi */
    private function bindRows(array $ingco, array $smi): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => $ingco,
            'SMI' => $smi,
        ]));
    }

    /** @return list<object> */
    private function validRows(): array
    {
        return [
            $this->row('JAKARTA', 350_000_000, 'INV-001', 'ABC01', 'Hammer', 1, 'A', true, true),
            $this->row('NEW LINE', 100_000, 'INV-002', 'XYZ01', 'Drill', 1, 'B'),
        ];
    }

    private function row(
        string $line,
        int $lineTotal,
        string $invoice = 'INV-001',
        string $itemCode = 'ABC01',
        string $itemName = 'Hammer',
        int $quantity = 1,
        string $grade = 'A',
        bool $discontinued = false,
        bool $clearance = false,
    ): object {
        return (object) [
            'InvoiceDate' => '2026-07-20',
            'InvoiceNumber' => $invoice,
            'LiniPenjualan' => $line,
            'ItemCode' => $itemCode,
            'ItemName' => $itemName,
            'Quantity' => $quantity,
            'LineTotal' => $lineTotal,
            'Discontinue' => $discontinued ? 1 : 0,
            'StatusItem' => $discontinued ? 'DISCONTINUE' : 'ACTIVE',
            'Grade' => $grade,
            'StatusClearanceSale' => $clearance ? 'CLEARANCE SALE' : 'BUKAN CLEARANCE SALE',
            'ClearanceSaleDiscount' => $clearance ? 0.2 : 0,
        ];
    }
}
