<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ThreeMonthSalesReviewFilter;
use App\Data\ThreeMonthSalesReviewLineRow;
use App\Data\ThreeMonthSalesReviewReport;
use App\Support\SalesLineName;

final class ThreeMonthSalesReviewService
{
    public function __construct(private readonly SalesDashboardService $salesDashboardService)
    {
    }

    public function generate(ThreeMonthSalesReviewFilter $filter): ThreeMonthSalesReviewReport
    {
        $salesFilter = $filter->salesDashboardFilter();

        $firstRows = $this->salesDashboardService->detailsForPeriod(
            $salesFilter,
            $filter->firstMonthStartDate,
            $filter->firstMonthEndDate,
        );
        $secondRows = $this->salesDashboardService->detailsForPeriod(
            $salesFilter,
            $filter->secondMonthStartDate,
            $filter->secondMonthEndDate,
        );
        $currentRows = $this->salesDashboardService->detailsForPeriod(
            $salesFilter,
            $filter->currentMonthStartDate,
            $filter->currentMonthEndDate,
        );

        $firstComparableRows = $filter->firstComparableEndDate->format('Y-m-d') === $filter->firstMonthEndDate->format('Y-m-d')
            ? $firstRows
            : $this->salesDashboardService->detailsForPeriod(
                $salesFilter,
                $filter->firstMonthStartDate,
                $filter->firstComparableEndDate,
            );

        $secondComparableRows = $filter->secondComparableEndDate->format('Y-m-d') === $filter->secondMonthEndDate->format('Y-m-d')
            ? $secondRows
            : $this->salesDashboardService->detailsForPeriod(
                $salesFilter,
                $filter->secondMonthStartDate,
                $filter->secondComparableEndDate,
            );

        $first = $this->sumByLine($firstRows);
        $second = $this->sumByLine($secondRows);
        $current = $this->sumByLine($currentRows);
        $firstComparable = $this->sumByLine($firstComparableRows);
        $secondComparable = $this->sumByLine($secondComparableRows);

        $lines = $this->orderedLines($first, $second, $current, $firstComparable, $secondComparable);
        $totalAllLines = array_sum($first) + array_sum($second) + array_sum($current);

        $rows = [];
        foreach ($lines as $line) {
            $row = $this->makeRow(
                line: $line,
                firstMonthSales: $first[$line] ?? 0.0,
                secondMonthSales: $second[$line] ?? 0.0,
                currentMonthSales: $current[$line] ?? 0.0,
                firstComparableSales: $firstComparable[$line] ?? 0.0,
                secondComparableSales: $secondComparable[$line] ?? 0.0,
                totalAllLines: $totalAllLines,
            );

            $rows[] = $row;
        }

        $totalRow = $this->makeRow(
            line: 'TOTAL',
            firstMonthSales: array_sum($first),
            secondMonthSales: array_sum($second),
            currentMonthSales: array_sum($current),
            firstComparableSales: array_sum($firstComparable),
            secondComparableSales: array_sum($secondComparable),
            totalAllLines: $totalAllLines,
        );

        $topContributor = null;
        foreach ($rows as $row) {
            if ($topContributor === null || $row->totalSales > $topContributor->totalSales) {
                $topContributor = $row;
            }
        }

        return new ThreeMonthSalesReviewReport(
            filter: $filter,
            rows: $rows,
            totalRow: $totalRow,
            topContributor: $topContributor,
        );
    }

    /** @param list<array<string,mixed>> $rows @return array<string,float> */
    private function sumByLine(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $line = trim((string) ($row['line'] ?? ''));
            if ($line === '') {
                $line = SalesLineName::OTHERS;
            }

            $result[$line] = ($result[$line] ?? 0.0) + (float) ($row['sales'] ?? $row['line_total'] ?? 0.0);
        }

        return $result;
    }

    /**
     * @param array<string,float> ...$maps
     * @return list<string>
     */
    private function orderedLines(array ...$maps): array
    {
        $aliases = (array) config('sales_dashboard.aliases', []);
        $lines = [];

        foreach (array_keys((array) config('sales_dashboard.targets', [])) as $line) {
            $normalized = SalesLineName::normalize((string) $line);
            $normalized = SalesLineName::normalize((string) ($aliases[$normalized] ?? $normalized));
            if (!in_array($normalized, $lines, true)) {
                $lines[] = $normalized;
            }
        }

        $extras = [];
        foreach ($maps as $map) {
            foreach (array_keys($map) as $line) {
                if (!in_array($line, $lines, true)) {
                    $extras[$line] = true;
                }
            }
        }

        $extraLines = array_keys($extras);
        sort($extraLines, SORT_NATURAL | SORT_FLAG_CASE);

        return [...$lines, ...$extraLines];
    }

    private function makeRow(
        string $line,
        float $firstMonthSales,
        float $secondMonthSales,
        float $currentMonthSales,
        float $firstComparableSales,
        float $secondComparableSales,
        float $totalAllLines,
    ): ThreeMonthSalesReviewLineRow {
        $totalSales = $firstMonthSales + $secondMonthSales + $currentMonthSales;
        $threeMonthGrowth = $this->growth($currentMonthSales, $firstComparableSales);

        return new ThreeMonthSalesReviewLineRow(
            line: $line,
            firstMonthSales: $firstMonthSales,
            secondMonthSales: $secondMonthSales,
            secondMonthGrowth: $this->growth($secondMonthSales, $firstMonthSales),
            currentMonthSales: $currentMonthSales,
            currentMonthGrowth: $this->growth($currentMonthSales, $secondComparableSales),
            totalSales: $totalSales,
            averageMonthlySales: $totalSales / 3,
            threeMonthGrowth: $threeMonthGrowth,
            contributionPercentage: $totalAllLines !== 0.0 ? $totalSales / $totalAllLines : null,
            trend: $this->trend($threeMonthGrowth, $currentMonthSales, $firstComparableSales),
        );
    }

    private function growth(float $current, float $previous): ?float
    {
        if ($previous === 0.0) {
            return null;
        }

        return ($current / $previous) - 1.0;
    }

    private function trend(?float $growth, float $current, float $previous): string
    {
        if ($growth !== null) {
            if ($growth > 0.0) {
                return 'naik';
            }
            if ($growth < 0.0) {
                return 'turun';
            }

            return 'stabil';
        }

        return match (true) {
            $current > $previous => 'naik',
            $current < $previous => 'turun',
            default => 'stabil',
        };
    }
}
