<?php

declare(strict_types=1);

namespace App\Exports;

use App\Data\SalesDashboardLineRow;
use App\Data\SalesDashboardReport;
use App\Exports\SalesDashboard\SalesDashboardSheet;
use DateTimeImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class SalesDashboardExport implements WithMultipleSheets
{
    private const HEADER_ROW = 6;

    public function __construct(private readonly SalesDashboardReport $report)
    {
    }

    /** @return list<SalesDashboardSheet> */
    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->rawDataSheet(),
        ];
    }

    private function summarySheet(): SalesDashboardSheet
    {
        $rows = array_map(static fn (SalesDashboardLineRow $row): array => [
            $row->line,
            $row->monthlyTarget,
            $row->dailyTarget,
            $row->requiredDailySales,
            $row->dtd,
            $row->ldtd,
            $row->dtdGrowth,
            $row->mtd,
            $row->lmtd,
            $row->mtdGrowth,
            $row->averageDailySales,
            $row->estimatedEom,
            $row->estimatedEomPercentage,
            $row->achievementPercentage,
            $row->gap,
            $row->contributionPercentage,
        ], $this->report->rows);

        $row = $this->report->totalRow;
        $rows[] = [
            'TOTAL', $row->monthlyTarget, $row->dailyTarget, $row->requiredDailySales, $row->dtd, $row->ldtd,
            $row->dtdGrowth, $row->mtd, $row->lmtd, $row->mtdGrowth,
            $row->averageDailySales, $row->estimatedEom, $row->estimatedEomPercentage,
            $row->achievementPercentage, $row->gap, $row->contributionPercentage,
        ];

        return new SalesDashboardSheet(
            'Ringkasan Sales',
            [...$this->metadataRows(), [
                'LiniPenjualan', 'Monthly Sales Target', 'Daily Sales Target / HK', 'Required Daily Sales', 'DTD', 'LDTD',
                '%Age Penjualan/Day', 'MTD', 'LMTD', 'MTD Growth (%)', 'Avg Daily Sales',
                'Est Ach EOM', 'Est Ach EOM (%)', 'Sales Ach VS Monthly Target (%)',
                'Gap Sales 100%', 'Contribution terhadap Total Omzet',
            ], ...$rows],
            self::HEADER_ROW,
            [
                'B'=>'#,##0','C'=>'#,##0','D'=>'#,##0','E'=>'#,##0','F'=>'#,##0','G'=>'0.00%',
                'H'=>'#,##0','I'=>'#,##0','J'=>'0.00%','K'=>'#,##0','L'=>'#,##0',
                'M'=>'0.00%','N'=>'0.00%','O'=>'#,##0','P'=>'0.00%',
            ],
        );
    }

    private function rawDataSheet(): SalesDashboardSheet
    {
        $rows = array_map(static fn (array $row): array => [
            $row['database'] ?? '',
            $row['invoice_date'] ?? '',
            $row['invoice_number'] ?? '',
            $row['line'] ?? '',
            $row['line_contribution'] ?? null,
            $row['customer_code'] ?? '',
            $row['customer_name'] ?? '',
            $row['sales_person_name'] ?? '',
            $row['item_code'] ?? '',
            $row['item_name'] ?? '',
            ($row['grade'] ?? '') === '' ? 'KOSONG' : ($row['grade'] ?? ''),
            ($row['discontinued'] ?? false) ? 'YA' : 'TIDAK',
            $row['status_item'] ?? '',
            ($row['clearance'] ?? false) ? 'YA' : 'TIDAK',
            ($row['clearance'] ?? false) ? 'ITEM REJECT' : 'BUKAN ITEM REJECT',
            $row['clearance_discount'] ?? '',
            $row['quantity'] ?? 0,
            $row['discount'] ?? 0,
            $row['discount2'] ?? 0,
            $row['price_level_name'] ?? '',
            $row['line_total'] ?? 0,
            $row['remarks'] ?? '',
        ], $this->report->detailRows);

        return new SalesDashboardSheet(
            'Data Mentah',
            [...$this->metadataRows(), [
                'Database', 'InvoiceDate', 'InvoiceNumber', 'LiniPenjualan', 'Contribution Lini',
                'CustomerCode', 'CustomerName', 'SalesPersonName', 'ItemCode', 'ItemName', 'Grade',
                'Discontinue', 'StatusItem', 'Item Reject', 'Status Item Reject', 'Reject Discount',
                'Quantity', 'Discount', 'Discount2', 'PriceLevelName', 'LineTotal', 'Remarks',
            ], ...$rows],
            self::HEADER_ROW,
            ['E'=>'0.00%','P'=>'0.00','Q'=>'#,##0.00','R'=>'0.00','S'=>'0.00','U'=>'#,##0'],
        );
    }

    /** @return list<list<string>> */
    private function metadataRows(): array
    {
        $filter = $this->report->filter;
        return [
            ['Sales Report Dashboard'],
            ['Database: '.$filter->databaseLabel().' | Status item: '.$filter->itemStatusLabel()],
            ['Periode MTD: '.$filter->currentStartDate->format('d-m-Y').' s.d. '.$filter->currentEndDate->format('d-m-Y')],
            ['Periode LMTD: '.$filter->previousStartDate->format('d-m-Y').' s.d. '.$filter->previousEndDate->format('d-m-Y')],
            ['Tanggal ekspor: '.(new DateTimeImmutable())->format('d-m-Y H:i')],
        ];
    }
}
