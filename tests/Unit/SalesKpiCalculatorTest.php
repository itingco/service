<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesKpi\SalesKpiDataset;
use App\Data\SalesKpi\SalesKpiFilter;
use App\Data\SalesKpi\SalesKpiReport;
use App\Data\SalesKpi\SalesKpiTransaction;
use App\Models\SalesKpiTarget;
use App\Services\SalesKpi\SalesKpiAggregator;
use App\Services\SalesKpi\SalesKpiCalculator;
use App\Services\SalesKpi\SalesKpiPeriodService;
use App\Services\SalesKpi\SalesKpiTargetService;
use Tests\TestCase;

final class SalesKpiCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_single_combined_target_uses_sum_of_both_database_actuals(): void
    {
        $this->target('JAKARTA', 1_000);
        $line = $this->calculateReport(
            current: [
                $this->tx('AS_INGCO', 'JAKARTA', 350),
                $this->tx('AS_SMI', 'JAKARTA', 350),
            ],
            previous: [
                $this->tx('AS_INGCO', 'JAKARTA', 300),
                $this->tx('AS_SMI', 'JAKARTA', 300),
            ],
        )->line('JAKARTA');

        self::assertSame(1_000.0, $line->target);
        self::assertTrue($line->targetConfigured);
        self::assertSame(700.0, $line->currentOmzet);
        self::assertSame(40.0, $line->targetScore);
        self::assertNull($line->source('AS_INGCO')->target);
        self::assertNull($line->source('AS_SMI')->target);
    }

    public function test_target_achievement_35_percent_scores_20_of_40(): void
    {
        $this->target('JAKARTA', 1_000);
        $line = $this->calculateReport(current: [$this->tx('AS_INGCO', 'JAKARTA', 350)])->line('JAKARTA');
        self::assertEqualsWithDelta(20.0, $line->targetScore, 0.0001);
    }

    public function test_growth_is_calculated_after_summing_sources(): void
    {
        $this->target('JAKARTA', 1_000);
        $line = $this->calculateReport(
            current: [$this->tx('AS_INGCO', 'JAKARTA', 300), $this->tx('AS_SMI', 'JAKARTA', 200)],
            growthCurrent: [$this->tx('AS_INGCO', 'JAKARTA', 300), $this->tx('AS_SMI', 'JAKARTA', 200)],
            previous: [$this->tx('AS_INGCO', 'JAKARTA', 250), $this->tx('AS_SMI', 'JAKARTA', 150)],
        )->line('JAKARTA');

        self::assertEqualsWithDelta(0.25, $line->growthRate, 0.0001);
        self::assertSame(10.0, $line->growthScore);
    }

    public function test_missing_target_makes_only_target_dependent_components_unavailable(): void
    {
        $line = $this->calculateReport(
            current: [$this->tx('AS_INGCO', 'JAKARTA', 350, grade: 'A')],
            previous: [$this->tx('AS_INGCO', 'JAKARTA', 300)],
        )->line('JAKARTA');

        self::assertFalse($line->targetConfigured);
        self::assertNull($line->targetScore);
        self::assertNull($line->riskScore);
        self::assertNotNull($line->growthScore);
        self::assertNotNull($line->gradeScore());
        self::assertSame(30.0, $line->availableMaximum);
    }

    public function test_discontinued_and_clearance_row_is_counted_twice(): void
    {
        $this->target('JAKARTA', 10_000);
        $line = $this->calculateReport(current: [
            $this->tx('AS_INGCO', 'JAKARTA', 500, discontinued: true, clearance: true),
        ])->line('JAKARTA');

        self::assertSame(500.0, $line->discontinuedOmzet);
        self::assertSame(500.0, $line->clearanceOmzet);
        self::assertSame(1_000.0, $line->riskRealization);
        self::assertSame(20.0, $line->riskScore);
    }

    public function test_line_filter_limits_rows_and_summary(): void
    {
        $this->target('JAKARTA', 1_000);
        $this->target('GERAI KATAMSO', 1_000);
        $current = [
            $this->tx('AS_INGCO', 'JAKARTA', 700, grade: 'A'),
            $this->tx('AS_SMI', 'GERAI KATAMSO', 100, grade: 'B'),
        ];

        $report = $this->calculateReport(
            current: $current,
            previous: $current,
            filter: SalesKpiFilter::from('2026-07-20', ' jakarta '),
        );

        self::assertCount(1, $report->rows);
        self::assertSame('JAKARTA', $report->rows[0]->lineKey);
        self::assertSame(['JAKARTA'], $report->chartData()['labels']);
        self::assertArrayHasKey('GERAI KATAMSO', $report->availableLines);
    }

    private function target(string $line, float $value): void
    {
        SalesKpiTarget::query()->create(['sales_line' => $line, 'monthly_target' => $value]);
    }

    private function calculateReport(
        array $current,
        array $growthCurrent = [],
        array $previous = [],
        ?SalesKpiFilter $filter = null,
    ): SalesKpiReport {
        $filter ??= SalesKpiFilter::from('2026-07-20');
        $period = (new SalesKpiPeriodService())->make($filter->selectedDate);
        $dataset = new SalesKpiDataset(
            current: $current,
            growthCurrent: $growthCurrent === [] ? $current : $growthCurrent,
            growthPrevious: $previous,
        );
        $aggregates = (new SalesKpiAggregator())->aggregate($dataset);

        return (new SalesKpiCalculator(app(SalesKpiTargetService::class)))->calculate(
            $filter,
            $period,
            $aggregates,
            $current,
        );
    }

    private function tx(
        string $database,
        string $line,
        float $total,
        string $grade = '',
        bool $discontinued = false,
        bool $clearance = false,
    ): SalesKpiTransaction {
        $line = trim($line);
        $lineKey = strtoupper(preg_replace('/\s+/', ' ', $line) ?? $line);

        return new SalesKpiTransaction(
            database: $database,
            line: $line,
            lineKey: $lineKey,
            lineTotal: $total,
            discontinued: $discontinued,
            clearance: $clearance,
            grade: $grade,
            quantity: 1,
            invoiceDate: '2026-07-20',
            invoiceNumber: 'INV-001',
            itemCode: 'ITEM',
            itemName: 'Item',
            itemStatus: $discontinued ? 'DISCONTINUE' : 'ACTIVE',
            clearanceStatus: $clearance ? 'CLEARANCE SALE' : 'BUKAN CLEARANCE SALE',
            clearanceDiscount: $clearance ? '0.2' : '0',
        );
    }
}
