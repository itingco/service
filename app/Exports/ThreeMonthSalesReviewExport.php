<?php

declare(strict_types=1);

namespace App\Exports;

use App\Data\ThreeMonthSalesReviewLineRow;
use App\Data\ThreeMonthSalesReviewReport;
use App\Exports\SalesDashboard\SalesDashboardSheet;
use DateTimeImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class ThreeMonthSalesReviewExport implements WithMultipleSheets
{
    private const HEADER_ROW = 6;

    public function __construct(private readonly ThreeMonthSalesReviewReport $report)
    {
    }

    /** @return list<SalesDashboardSheet> */
    public function sheets(): array
    {
        $filter = $this->report->filter;
        $rows = array_map(static fn (ThreeMonthSalesReviewLineRow $row): array => [
            $row->line,
            $row->firstMonthSales,
            null,
            $row->secondMonthSales,
            $row->secondMonthGrowth,
            $row->currentMonthSales,
            $row->currentMonthGrowth,
            $row->totalSales,
            $row->averageMonthlySales,
            $row->threeMonthGrowth,
            $row->contributionPercentage,
            strtoupper($row->trend),
        ], $this->report->rows);

        $row = $this->report->totalRow;
        $rows[] = [
            'TOTAL',
            $row->firstMonthSales,
            null,
            $row->secondMonthSales,
            $row->secondMonthGrowth,
            $row->currentMonthSales,
            $row->currentMonthGrowth,
            $row->totalSales,
            $row->averageMonthlySales,
            $row->threeMonthGrowth,
            $row->contributionPercentage,
            strtoupper($row->trend),
        ];

        return [
            new SalesDashboardSheet(
                'Review 3 Bulan',
                [
                    ['Review Penjualan 3 Bulan'],
                    ['Database: '.$filter->databaseLabel().' | Status item: '.$filter->itemStatusLabel()],
                    ['Periode: '.$filter->periodStartDate->format('d-m-Y').' s.d. '.$filter->selectedDate->format('d-m-Y')],
                    ['Catatan: '.$filter->comparisonNote()],
                    ['Tanggal ekspor: '.(new DateTimeImmutable())->format('d-m-Y H:i')],
                    [
                        'LiniPenjualan',
                        $filter->firstMonthLabel().' Sales',
                        $filter->firstMonthLabel().' Growth',
                        $filter->secondMonthLabel().' Sales',
                        $filter->secondMonthLabel().' Growth',
                        $filter->currentMonthLabel().' Sales',
                        $filter->currentMonthLabel().' Growth',
                        'Total Sales 3 Bulan',
                        'Average / Bulan',
                        'Growth 3 Bulan',
                        'Contribution',
                        'Trend',
                    ],
                    ...$rows,
                ],
                self::HEADER_ROW,
                [
                    'B' => '#,##0', 'C' => '0.00%', 'D' => '#,##0', 'E' => '0.00%',
                    'F' => '#,##0', 'G' => '0.00%', 'H' => '#,##0', 'I' => '#,##0',
                    'J' => '0.00%', 'K' => '0.00%',
                ],
            ),
        ];
    }
}
