<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SalesDataSource;
use App\Exceptions\SalesKpiDataException;
use App\Models\SalesKpiTarget;
use App\Services\SalesKpi\SalesKpiDataService;
use App\Services\SalesKpi\SalesKpiTargetCatalogService;
use App\Services\SalesKpi\SalesKpiTargetService;
use App\Support\SalesKpiRowNormalizer;
use DateTimeImmutable;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesKpiTargetCatalogServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_it_merges_current_month_lines_and_keeps_inactive_saved_targets(): void
    {
        $fake = new FakeSalesDataSource([
            'INGCO' => [
                $this->salesRow('JAKARTA', 100_000_000),
                $this->salesRow('GERAI BOGOR', 50_000_000),
            ],
            'SMI' => [
                $this->salesRow(' jakarta ', 75_000_000),
            ],
        ]);

        SalesKpiTarget::query()->create([
            'sales_line' => 'GERAI KATAMSO',
            'monthly_target' => 300_000_000,
        ]);

        $service = $this->catalog($fake);
        $rows = $service->currentMonth(new DateTimeImmutable('2026-07-28'));
        $byLine = [];
        foreach ($rows as $row) {
            $byLine[$row->lineKey] = $row;
        }

        self::assertSame(['GERAI BOGOR', 'GERAI KATAMSO', 'JAKARTA'], array_column($rows, 'line'));
        self::assertSame(175_000_000.0, $byLine['JAKARTA']->currentOmzet);
        self::assertTrue($byLine['JAKARTA']->hasCurrentTransactions);
        self::assertFalse($byLine['JAKARTA']->hasTarget);
        self::assertFalse($byLine['GERAI KATAMSO']->hasCurrentTransactions);
        self::assertTrue($byLine['GERAI KATAMSO']->hasTarget);
    }


    public function test_zero_value_transaction_still_marks_line_active_this_month(): void
    {
        $fake = new FakeSalesDataSource([
            'INGCO' => [$this->salesRow('JAKARTA', 0)],
            'SMI' => [],
        ]);

        $rows = $this->catalog($fake)->currentMonth(new DateTimeImmutable('2026-07-28'));

        self::assertCount(1, $rows);
        self::assertSame(0.0, $rows[0]->currentOmzet);
        self::assertTrue($rows[0]->hasCurrentTransactions);
    }

    public function test_it_never_returns_partial_catalog_when_one_database_fails(): void
    {
        $fake = new FakeSalesDataSource(
            ['INGCO' => [$this->salesRow('JAKARTA', 100_000_000)]],
            ['SMI' => 'connection failed'],
        );

        $this->expectException(SalesKpiDataException::class);
        $this->catalog($fake)->currentMonth(new DateTimeImmutable('2026-07-28'));
    }


    public function test_it_reports_both_databases_when_both_catalog_sources_fail(): void
    {
        $fake = new FakeSalesDataSource(
            [],
            ['INGCO' => 'connection failed', 'SMI' => 'connection failed'],
        );

        try {
            $this->catalog($fake)->currentMonth(new DateTimeImmutable('2026-07-28'));
            self::fail('Expected SalesKpiDataException.');
        } catch (SalesKpiDataException $exception) {
            self::assertSame(['INGCO', 'SMI'], $exception->failedSources());
        }
    }

    private function catalog(SalesDataSource $source): SalesKpiTargetCatalogService
    {
        $data = new SalesKpiDataService($source, new SalesKpiRowNormalizer());

        return new SalesKpiTargetCatalogService($data, app(SalesKpiTargetService::class));
    }

    /** @return array<string,mixed> */
    private function salesRow(string $line, float $lineTotal): array
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
}
