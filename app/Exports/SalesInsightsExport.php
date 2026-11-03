<?php

declare(strict_types=1);

namespace App\Exports;

use App\Data\SalesDashboardInsights;
use App\Data\SalesDashboardReport;
use App\Data\SalesResearchInsights;
use App\Exports\SalesDashboard\SalesDashboardSheet;
use DateTimeImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class SalesInsightsExport implements WithMultipleSheets
{
    private const HEADER_ROW = 6;

    public function __construct(
        private readonly SalesDashboardReport $report,
        private readonly SalesDashboardInsights $management,
        private readonly SalesResearchInsights $research,
    ) {
    }

    /** @return list<SalesDashboardSheet> */
    public function sheets(): array
    {
        return [
            $this->managementSheet(),
            $this->productSignalsSheet(),
            $this->demandWatchSheet(),
            $this->portfolioSheet(),
            $this->rawDataSheet(),
        ];
    }

    private function managementSheet(): SalesDashboardSheet
    {
        $actions = [];
        foreach ($this->management->actions as $action) {
            $actions[$action['line']] = $action;
        }

        $rows = array_map(static function (array $pace) use ($actions): array {
            $action = $actions[$pace['line']] ?? null;
            return [
                $pace['line'],
                $pace['pace_status'],
                $pace['achievement_percentage'],
                $pace['mtd_growth'],
                $pace['monthly_target'],
                $pace['mtd'],
                $pace['remaining_target'],
                $pace['daily_sales_target_per_hk'],
                $pace['required_daily_sales'],
                $pace['average_daily_sales'],
                $pace['estimated_eom'],
                $pace['estimated_eom_percentage'],
                $pace['forecast_gap'],
                $action['priority'] ?? '',
                $action['issue'] ?? '',
                $action['suggestion'] ?? '',
            ];
        }, $this->management->linePaces);

        return new SalesDashboardSheet(
            'Management Insights',
            [...$this->metadataRows('Sales Insights & R&D Dashboard'), [
                'LiniPenjualan', 'Pace Status', 'Achievement (%)', 'MTD Growth (%)',
                'Monthly Target', 'MTD', 'Sisa Target', 'Daily Sales Target / HK', 'Required Daily Sales',
                'Average Daily Sales', 'Forecast EOM', 'Forecast EOM (%)', 'Risk Gap',
                'Priority', 'Masalah', 'Rekomendasi Tindakan',
            ], ...$rows],
            self::HEADER_ROW,
            ['C'=>'0.00%','D'=>'0.00%','E'=>'#,##0','F'=>'#,##0','G'=>'#,##0','H'=>'#,##0','I'=>'#,##0','J'=>'#,##0','K'=>'#,##0','L'=>'0.00%','M'=>'#,##0'],
        );
    }

    private function productSignalsSheet(): SalesDashboardSheet
    {
        $rows = array_map(static fn (array $row): array => [
            $row['item_code'],
            $row['item_name'],
            $row['grade'],
            $row['current_sales'],
            $row['previous_sales'],
            $row['growth'],
            $row['quantity'],
            $row['customer_count'],
            $row['top_customer_share'],
            $row['primary_line'],
            $row['primary_line_share'],
            $row['average_discount'],
            $row['discontinued'] ? 'YA' : 'TIDAK',
            $row['clearance'] ? 'YA' : 'TIDAK',
            $row['signal'],
            $row['recommendation'],
        ], $this->research->productSignals);

        return new SalesDashboardSheet(
            'R&D Product Signals',
            [...$this->metadataRows('R&D Product Momentum'), [
                'ItemCode', 'ItemName', 'Grade', 'MTD', 'LMTD', 'Growth', 'Quantity',
                'Customer Count', 'Top Customer Share', 'Primary LiniPenjualan',
                'Primary Line Share', 'Average Discount Input', 'Discontinued',
                'Item Reject', 'Signal', 'Rekomendasi R&D',
            ], ...$rows],
            self::HEADER_ROW,
            ['D'=>'#,##0','E'=>'#,##0','F'=>'0.00%','G'=>'#,##0.00','I'=>'0.00%','K'=>'0.00%','L'=>'0.00'],
        );
    }

    private function demandWatchSheet(): SalesDashboardSheet
    {
        $rows = [];
        foreach ($this->research->newDemand as $row) {
            $rows[] = ['DEMAND BARU', $row['item_code'], $row['item_name'], $row['current_sales'], $row['previous_sales'], $row['customer_count'], $row['primary_line'], $row['recommendation']];
        }
        foreach ($this->research->lostDemand as $row) {
            $rows[] = ['DEMAND HILANG', $row['item_code'], $row['item_name'], $row['current_sales'], $row['previous_sales'], $row['customer_count'], $row['primary_line'], $row['recommendation']];
        }
        foreach ($this->research->discountWatch as $row) {
            $rows[] = ['DISCOUNT WATCH', $row['item_code'], $row['item_name'], $row['current_sales'], $row['previous_sales'], $row['customer_count'], $row['primary_line'], $row['recommendation']];
        }

        return new SalesDashboardSheet(
            'R&D Demand Watch',
            [...$this->metadataRows('R&D Demand Watch'), [
                'Kategori', 'ItemCode', 'ItemName', 'MTD', 'LMTD', 'Customer Count',
                'Primary LiniPenjualan', 'Rekomendasi',
            ], ...$rows],
            self::HEADER_ROW,
            ['D'=>'#,##0','E'=>'#,##0'],
        );
    }

    private function portfolioSheet(): SalesDashboardSheet
    {
        $rows = [['GRADE DEMAND']];
        foreach ($this->research->gradeDemand as $row) {
            $rows[] = ['GRADE', $row['grade'], $row['current_sales'], $row['previous_sales'], $row['growth']];
        }
        $rows[] = [];
        $rows[] = ['CHANNEL–PRODUCT FIT'];
        foreach ($this->research->channelFit as $row) {
            $rows[] = ['CHANNEL', $row['line'], trim($row['item_code'].' — '.$row['item_name'], ' —'), $row['sales'], $row['share_of_line']];
        }

        return new SalesDashboardSheet(
            'R&D Portfolio',
            [...$this->metadataRows('R&D Portfolio & Channel Fit'), [
                'Jenis', 'Dimensi', 'Produk / MTD', 'Nilai / LMTD', 'Growth / Share',
            ], ...$rows],
            self::HEADER_ROW,
            ['C'=>'#,##0','D'=>'#,##0','E'=>'0.00%'],
        );
    }

    private function rawDataSheet(): SalesDashboardSheet
    {
        $rows = array_map(static fn (array $row): array => [
            $row['database'] ?? '',
            $row['invoice_date'] ?? '',
            $row['invoice_number'] ?? '',
            $row['line'] ?? '',
            $row['customer_code'] ?? '',
            $row['customer_name'] ?? '',
            $row['sales_person_name'] ?? '',
            $row['item_code'] ?? '',
            $row['item_name'] ?? '',
            ($row['grade'] ?? '') === '' ? 'KOSONG' : ($row['grade'] ?? ''),
            ($row['discontinued'] ?? false) ? 'YA' : 'TIDAK',
            ($row['clearance'] ?? false) ? 'YA' : 'TIDAK',
            $row['quantity'] ?? 0,
            $row['discount'] ?? 0,
            $row['discount2'] ?? 0,
            $row['price_level_name'] ?? '',
            $row['line_total'] ?? 0,
            $row['remarks'] ?? '',
        ], $this->report->detailRows);

        return new SalesDashboardSheet(
            'Data Mentah MTD',
            [...$this->metadataRows('Data Mentah untuk Sales Insights'), [
                'Database', 'InvoiceDate', 'InvoiceNumber', 'LiniPenjualan', 'CustomerCode',
                'CustomerName', 'SalesPersonName', 'ItemCode', 'ItemName', 'Grade',
                'Discontinued', 'Item Reject', 'Quantity', 'Discount', 'Discount2',
                'PriceLevelName', 'LineTotal', 'Remarks',
            ], ...$rows],
            self::HEADER_ROW,
            ['M'=>'#,##0.00','N'=>'0.00','O'=>'0.00','Q'=>'#,##0'],
        );
    }

    /** @return list<list<string>> */
    private function metadataRows(string $title): array
    {
        $filter = $this->report->filter;
        return [
            [$title],
            ['Database: '.$filter->databaseLabel().' | Status item: '.$filter->itemStatusLabel()],
            ['Periode MTD: '.$filter->currentStartDate->format('d-m-Y').' s.d. '.$filter->currentEndDate->format('d-m-Y')],
            ['Periode LMTD: '.$filter->previousStartDate->format('d-m-Y').' s.d. '.$filter->previousEndDate->format('d-m-Y')],
            ['Tanggal ekspor: '.(new DateTimeImmutable())->format('d-m-Y H:i')],
        ];
    }
}
