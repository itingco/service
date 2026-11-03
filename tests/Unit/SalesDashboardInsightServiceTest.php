<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesDashboardFilter;
use App\Data\SalesDashboardLineRow;
use App\Data\SalesDashboardReport;
use App\Services\SalesDashboardInsightService;
use PHPUnit\Framework\TestCase;

final class SalesDashboardInsightServiceTest extends TestCase
{
    public function test_it_builds_management_summary_actions_rankings_and_drilldowns(): void
    {
        $filter = SalesDashboardFilter::from('all', '2026-07-22', 'all');
        $rows = [
            $this->row('JAKARTA', 1_000, 10, 100, 50, 500, 550, -0.10, 800, 0.80, 0.50, 500, 0.3125),
            $this->row('ONLINE', 1_000, 10, 0, 200, 1_100, 900, 0.20, 1_300, 1.30, 1.10, -100, 0.6875),
            $this->row('NO TARGET', 0, 0, 0, 20, 100, 0, null, 150, null, null, -100, 0.0625),
        ];

        $report = new SalesDashboardReport(
            filter: $filter,
            rows: $rows,
            totalRow: $this->row('TOTAL', 2_000, 20, 100, 270, 1_700, 1_450, 0.1724, 2_250, 1.125, 0.85, 300, 1.0),
            detailRows: $this->detailRows(),
        );

        $insights = (new SalesDashboardInsightService())->build($report);

        self::assertSame(2_000.0, $insights->totalTarget);
        self::assertSame(1_700.0, $insights->totalMtd);
        self::assertSame(300.0, $insights->remainingTarget);
        self::assertSame(2_250.0, $insights->forecastEom);
        self::assertSame(1, $insights->onTrackCount);
        self::assertSame(1, $insights->atRiskCount);
        self::assertSame('BEHIND', $insights->linePaces[0]['pace_status']);
        self::assertSame(10.0, $insights->linePaces[0]['daily_sales_target_per_hk']);
        self::assertSame(100.0, $insights->linePaces[0]['required_daily_sales']);
        self::assertSame('TARGET TERCAPAI', $insights->linePaces[1]['pace_status']);
        self::assertSame('TARGET BELUM DIATUR', $insights->linePaces[2]['pace_status']);
        self::assertSame('TINGGI', $insights->actions[0]['priority']);
        self::assertSame('Customer 1', $insights->topCustomers[0]['label']);
        self::assertSame(300.0, $insights->topCustomers[0]['sales']);
        self::assertEqualsWithDelta(0.93, $insights->topFiveCustomerConcentration, 0.0001);
        self::assertSame('Sales Alpha', $insights->topSalespersons[0]['label']);
        self::assertSame('I-001 — Item 1', $insights->topItems[0]['label']);
        self::assertSame(520.0, $insights->gradeMix['A']['sales']);
        self::assertSame(300.0, $insights->statusMix['discontinued']['sales']);
        self::assertSame('01 Jul', $insights->dailyTrend['labels'][0]);
        self::assertCount(22, $insights->dailyTrend['labels']);
    }

    private function row(
        string $line,
        float $target,
        float $dailyTarget,
        float $requiredDailySales,
        float $average,
        float $mtd,
        float $lmtd,
        ?float $growth,
        float $estimated,
        ?float $estimatedPercentage,
        ?float $achievement,
        float $gap,
        ?float $contribution,
    ): SalesDashboardLineRow {
        return new SalesDashboardLineRow(
            line: $line,
            monthlyTarget: $target,
            dailyTarget: $dailyTarget,
            requiredDailySales: $requiredDailySales,
            dtd: 0,
            ldtd: 0,
            dtdGrowth: null,
            mtd: $mtd,
            lmtd: $lmtd,
            mtdGrowth: $growth,
            averageDailySales: $average,
            estimatedEom: $estimated,
            estimatedEomPercentage: $estimatedPercentage,
            achievementPercentage: $achievement,
            gap: $gap,
            contributionPercentage: $contribution,
        );
    }

    /** @return list<array<string,mixed>> */
    private function detailRows(): array
    {
        $amounts = [300, 220, 180, 130, 100, 70];
        $rows = [];

        foreach ($amounts as $index => $amount) {
            $number = $index + 1;
            $rows[] = [
                'invoice_date' => sprintf('2026-07-%02d', $number),
                'customer_code' => 'C'.$number,
                'customer_name' => 'Customer '.$number,
                'sales_person_name' => $number <= 3 ? 'Sales Alpha' : 'Sales Beta',
                'item_code' => 'I-'.sprintf('%03d', $number),
                'item_name' => 'Item '.$number,
                'grade' => $number <= 2 ? 'A' : ($number <= 4 ? 'B' : ''),
                'line_total' => (float) $amount,
                'sales' => (float) $amount,
                'discontinued' => $number === 1,
                'clearance' => $number === 2,
            ];
        }

        return $rows;
    }
}
