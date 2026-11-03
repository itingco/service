<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SalesKpiTarget;
use App\Services\SalesKpi\SalesKpiTargetService;
use Tests\TestCase;

final class SalesKpiTargetServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_it_returns_one_combined_target_by_normalized_line(): void
    {
        SalesKpiTarget::query()->create([
            'sales_line' => 'GERAI KATAMSO',
            'monthly_target' => 300_000_000,
        ]);

        $service = app(SalesKpiTargetService::class);

        self::assertSame(300_000_000.0, $service->targetForLine('  gerai   katamso '));
        self::assertNull($service->targetForLine('JAKARTA'));
    }

    public function test_it_collapses_legacy_other_line_variants_to_lainnya(): void
    {
        SalesKpiTarget::query()->create([
            'sales_line' => 'LAIN - LAIN',
            'monthly_target' => 900_000_000,
        ]);
        SalesKpiTarget::query()->create([
            'sales_line' => 'LAINNYA',
            'monthly_target' => 950_000_000,
        ]);

        $service = app(SalesKpiTargetService::class);

        self::assertSame(['LAINNYA' => 950_000_000.0], $service->all());
        self::assertSame(950_000_000.0, $service->targetForLine('LAIN - LAIN'));
    }

    public function test_save_batch_converts_zero_to_null_and_is_atomic(): void
    {
        $service = app(SalesKpiTargetService::class);

        $service->saveBatch([
            'JAKARTA' => 500_000_000.0,
            'GERAI KATAMSO' => null,
            'GERAI BOGOR' => 0.0,
        ]);

        self::assertSame(500_000_000.0, $service->targetForLine('JAKARTA'));
        self::assertNull($service->targetForLine('GERAI KATAMSO'));
        self::assertNull($service->targetForLine('GERAI BOGOR'));
    }
}
