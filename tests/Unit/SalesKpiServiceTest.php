<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesKpi\SalesKpiFilter;
use App\Models\SalesKpiTarget;
use App\Services\SalesKpi\SalesKpiAggregator;
use App\Services\SalesKpi\SalesKpiCalculator;
use App\Services\SalesKpi\SalesKpiCombinedDataService;
use App\Services\SalesKpi\SalesKpiDataService;
use App\Services\SalesKpi\SalesKpiPeriodService;
use App\Services\SalesKpi\SalesKpiService;
use App\Services\SalesKpi\SalesKpiTargetService;
use App\Support\SalesKpiRowNormalizer;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesKpiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_it_orchestrates_two_sources_into_one_line(): void
    {
        $row = (object) [
            'InvoiceDate' => '2026-07-20', 'InvoiceNumber' => 'INV-001',
            'LiniPenjualan' => 'JAKARTA', 'ItemCode' => 'ABC01', 'ItemName' => 'Hammer',
            'Quantity' => 1, 'LineTotal' => 350_000_000, 'Discontinue' => 0,
            'StatusItem' => 'ACTIVE', 'Grade' => 'A',
            'StatusClearanceSale' => 'BUKAN CLEARANCE SALE', 'ClearanceSaleDiscount' => 0,
        ];
        $fake = new FakeSalesDataSource(['INGCO' => [$row], 'SMI' => [$row]]);
        SalesKpiTarget::query()->create(['sales_line' => 'JAKARTA', 'monthly_target' => 1_000_000_000]);

        $service = new SalesKpiService(
            new SalesKpiPeriodService(),
            new SalesKpiCombinedDataService(new SalesKpiDataService($fake, new SalesKpiRowNormalizer())),
            new SalesKpiAggregator(),
            new SalesKpiCalculator(app(SalesKpiTargetService::class)),
        );

        $report = $service->generate(SalesKpiFilter::from('2026-07-20'));

        self::assertCount(1, $report->rows);
        self::assertSame(700_000_000.0, $report->rows[0]->currentOmzet);
        self::assertSame(1_000_000_000.0, $report->rows[0]->target);
    }
}
