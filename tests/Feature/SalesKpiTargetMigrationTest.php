<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SalesKpiTarget;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SalesKpiTargetMigrationTest extends TestCase
{
    public function test_target_table_exists_on_dedicated_sqlite_connection(): void
    {
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);

        self::assertTrue(
            Schema::connection('sqlite_targets')->hasColumns('sales_kpi_targets', [
                'id', 'sales_line', 'monthly_target', 'created_at', 'updated_at',
            ]),
        );

        $target = SalesKpiTarget::query()->create([
            'sales_line' => 'JAKARTA',
            'monthly_target' => 500_000_000,
        ]);

        self::assertSame('sqlite_targets', $target->getConnectionName());
        self::assertSame('JAKARTA', $target->sales_line);
        self::assertSame(500_000_000.0, (float) $target->monthly_target);
    }
}
