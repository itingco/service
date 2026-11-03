<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SalesKpiTargetLookup;
use App\Data\SalesDashboardFilter;
use App\Services\SalesDashboardAggregator;
use PHPUnit\Framework\TestCase;

final class SalesDashboardAggregatorTest extends TestCase
{
    public function test_it_builds_metrics_contribution_and_raw_rows_by_sales_line(): void
    {
        $filter = SalesDashboardFilter::from('all', '2026-07-22', 'all');
        $targets = new MutableSalesDashboardTargetLookup([
            'JAKARTA' => 250000000.0,
            'LAIN - LAIN' => 900000000.0,
            'LAINNYA' => 950000000.0,
        ]);
        $aggregator = new SalesDashboardAggregator($targets);

        $report = $aggregator->aggregate($filter, [
            'dtd' => [$this->transaction('JAKARTA', 982000)],
            'ldtd' => [$this->transaction('JAKARTA', 23000)],
            'mtd' => [
                $this->transaction('JAKARTA', 300),
                $this->transaction('UNKNOWN CHANNEL', 100),
            ],
            'lmtd' => [$this->transaction('JAKARTA', 53850599)],
        ]);

        $row = $report->rows[0];
        self::assertSame(300.0, $row->mtd);
        self::assertSame(53850599.0, $row->lmtd);
        self::assertSame(982000.0, $row->dtd);
        self::assertSame(23000.0, $row->ldtd);
        self::assertEqualsWithDelta(250000000 / 27, $row->dailyTarget, .001);
        self::assertEqualsWithDelta((250000000 - 300) / 6, $row->requiredDailySales, .001);
        self::assertEqualsWithDelta(0.75, $row->contributionPercentage, .0001);
        self::assertSame(2, count($report->detailRows));
        self::assertSame('LAINNYA', $report->detailRows[1]['line']);
        self::assertCount(2, $report->rows, 'Alias LAIN - LAIN and LAINNYA must collapse into one row.');
        self::assertSame('LAINNYA', $report->rows[1]->line);
        self::assertSame(950000000.0, $report->rows[1]->monthlyTarget, 'Canonical SQLite target must override legacy fallback target.');
        self::assertEqualsWithDelta(0.25, $report->detailRows[1]['line_contribution'], .0001);
    }


    public function test_it_reads_updated_targets_without_rebuilding_the_service(): void
    {
        $filter = SalesDashboardFilter::from('all', '2026-07-22', 'all');
        $targets = new MutableSalesDashboardTargetLookup(['JAKARTA' => 100000000.0]);
        $aggregator = new SalesDashboardAggregator($targets);
        $periodRows = [
            'dtd' => [],
            'ldtd' => [],
            'mtd' => [$this->transaction('JAKARTA', 10000000)],
            'lmtd' => [],
        ];

        self::assertSame(100000000.0, $aggregator->aggregate($filter, $periodRows)->rows[0]->monthlyTarget);

        $targets->targets['JAKARTA'] = 250000000.0;

        self::assertSame(250000000.0, $aggregator->aggregate($filter, $periodRows)->rows[0]->monthlyTarget);
    }

    /** @return array<string,mixed> */
    private function transaction(string $line, float $sales): array
    {
        return [
            'line' => $line,
            'sales' => $sales,
            'line_total' => $sales,
            'discontinued' => false,
            'clearance' => false,
            'item_code' => '',
            'item_name' => '',
            'source' => 'INGCO',
            'database' => 'AS_INGCO',
        ];
    }
}


final class MutableSalesDashboardTargetLookup implements SalesKpiTargetLookup
{
    /** @param array<string,?float> $targets */
    public function __construct(public array $targets)
    {
    }

    public function targetForLine(string $line): ?float
    {
        return $this->targets[strtoupper(trim($line))] ?? null;
    }

    /** @return array<string,?float> */
    public function all(): array
    {
        return $this->targets;
    }
}
