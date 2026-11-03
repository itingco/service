<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use App\Models\SalesKpiTarget;
use DateTimeImmutable;
use Tests\TestCase;

final class SalesKpiTargetSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);

        $this->app->bind(SalesDataSource::class, fn (): SalesDataSource => new class implements SalesDataSource {
            public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
            {
                return $source === 'INGCO'
                    ? [$this->row('JAKARTA', 100_000_000)]
                    : [$this->row('GERAI BOGOR', 50_000_000)];
            }

            /** @return array<string,mixed> */
            private function row(string $line, float $lineTotal): array
            {
                return [
                    'LiniPenjualan' => $line,
                    'LineTotal' => $lineTotal,
                    'Discontinue' => 0,
                    'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
                    'Grade' => 'A',
                    'Quantity' => 1,
                    'InvoiceDate' => '2026-07-10',
                    'InvoiceNumber' => 'INV-1',
                    'ItemCode' => 'ITEM-1',
                    'ItemName' => 'Item',
                    'StatusItem' => 'ACTIVE',
                    'ClearanceSaleDiscount' => null,
                ];
            }
        });

        SalesKpiTarget::query()->create([
            'sales_line' => 'GERAI KATAMSO',
            'monthly_target' => 300_000_000,
        ]);
    }

    public function test_page_shows_current_and_inactive_lines(): void
    {
        $this->actingAsDashboardAdmin()
            ->get('/sales-kpi/targets')
            ->assertOk()
            ->assertSee('JAKARTA')
            ->assertSee('GERAI KATAMSO')
            ->assertSee('Tidak ada transaksi bulan ini')
            ->assertSee('Simpan Semua Perubahan');
    }

    public function test_blank_and_zero_targets_are_saved_as_null(): void
    {
        $this->actingAsDashboardAdmin()
            ->put('/sales-kpi/targets', [
                'targets' => [
                    'JAKARTA' => '',
                    'GERAI KATAMSO' => '0',
                ],
            ])->assertRedirect('/sales-kpi/targets');

        self::assertNull(SalesKpiTarget::query()->where('sales_line', 'JAKARTA')->value('monthly_target'));
        self::assertNull(SalesKpiTarget::query()->where('sales_line', 'GERAI KATAMSO')->value('monthly_target'));
    }

    public function test_unknown_line_rejects_entire_batch(): void
    {
        SalesKpiTarget::query()->updateOrCreate(['sales_line' => 'JAKARTA'], ['monthly_target' => 100]);

        $this->actingAsDashboardAdmin()
            ->from('/sales-kpi/targets')
            ->put('/sales-kpi/targets', [
                'targets' => [
                    'JAKARTA' => '500.000.000',
                    'BARIS PALSU' => '900.000.000',
                ],
            ])->assertRedirect('/sales-kpi/targets')
              ->assertSessionHasErrors('targets');

        self::assertSame(100.0, (float) SalesKpiTarget::query()->where('sales_line', 'JAKARTA')->value('monthly_target'));
    }
}
