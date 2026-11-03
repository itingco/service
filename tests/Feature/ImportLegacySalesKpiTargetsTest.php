<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ImportLegacySalesKpiTargetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_it_combines_legacy_source_targets_by_normalized_line(): void
    {
        config()->set('sales_kpi_targets', [
            'AS_INGCO' => ['JAKARTA' => 300_000_000, 'GERAI KATAMSO' => 100_000_000],
            'AS_SMI' => [' jakarta ' => 200_000_000, 'GERAI KATAMSO' => 200_000_000],
        ]);

        $this->artisan('sales-kpi:import-legacy-targets')
            ->expectsOutput('2 target gabungan berhasil diimpor.')
            ->assertExitCode(0);

        self::assertDatabaseHas('sales_kpi_targets', [
            'sales_line' => 'JAKARTA',
            'monthly_target' => 500_000_000,
        ], 'sqlite_targets');
    }
}
