<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\SalesDashboard\SalesDashboardSheet;
use DateTimeImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class CustomerAnalysisExport implements WithMultipleSheets
{
    private const HEADER_ROW = 6;

    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    /** @return list<SalesDashboardSheet> */
    public function sheets(): array
    {
        return [
            $this->customerSummarySheet(),
            $this->rawTransactionSheet(),
        ];
    }

    private function customerSummarySheet(): SalesDashboardSheet
    {
        $rows = array_map(static function (array $row): array {
            $lastItems = array_map(
                static fn (array $item): string => trim(sprintf(
                    '%s - %s (Qty %s)',
                    (string) ($item['item_code'] ?? ''),
                    (string) ($item['item_name'] ?? ''),
                    number_format((float) ($item['quantity'] ?? 0), 0, ',', '.'),
                )),
                (array) ($row['last_purchase_items'] ?? []),
            );

            return [
                $row['source'] ?? '',
                $row['customer_id'] ?? '',
                $row['customer_code'] ?? '',
                $row['customer_name'] ?? '',
                $row['sales_person_id'] ?? '',
                $row['sales_person_name'] ?? '',
                strtoupper((string) ($row['status'] ?? '')),
                $row['total_sales_3m'] ?? 0,
                $row['total_qty_3m'] ?? 0,
                $row['invoice_count_3m'] ?? 0,
                $row['last_purchase_date'] ?? '',
                $row['last_invoice'] ?? '',
                implode(' | ', $lastItems),
            ];
        }, (array) ($this->data['customer_rows'] ?? []));

        return new SalesDashboardSheet(
            'Customer Summary',
            [...$this->metadataRows(), [
                'Database', 'CustomerID', 'CustomerCode', 'CustomerName',
                'SalesPersonID', 'SalesPersonName', 'Status', 'Sales 3 Bulan',
                'Qty 3 Bulan', 'Jumlah Invoice', 'Transaksi Terakhir',
                'Invoice Terakhir', 'Barang Terakhir / Top 3',
            ], ...$rows],
            self::HEADER_ROW,
            ['H' => '#,##0', 'I' => '#,##0.00', 'J' => '#,##0'],
        );
    }

    private function rawTransactionSheet(): SalesDashboardSheet
    {
        $rows = array_map(static fn (array $row): array => [
            $row['database'] ?? '',
            $row['invoice_date'] ?? '',
            $row['invoice_number'] ?? '',
            $row['customer_id'] ?? '',
            $row['customer_code'] ?? '',
            $row['customer_name'] ?? '',
            $row['sales_person_id'] ?? '',
            $row['sales_person_name'] ?? '',
            $row['line'] ?? '',
            $row['item_code'] ?? '',
            $row['item_name'] ?? '',
            ($row['grade'] ?? '') === '' ? 'KOSONG' : ($row['grade'] ?? ''),
            ($row['discontinued'] ?? false) ? 'YA' : 'TIDAK',
            $row['status_item'] ?? '',
            ($row['clearance'] ?? false) ? 'YA' : 'TIDAK',
            $row['clearance_status'] ?? '',
            $row['clearance_discount'] ?? '',
            $row['quantity'] ?? 0,
            $row['discount'] ?? 0,
            $row['discount2'] ?? 0,
            $row['price_level_name'] ?? '',
            $row['line_total'] ?? ($row['sales'] ?? 0),
            $row['remarks'] ?? '',
        ], (array) ($this->data['raw_rows'] ?? []));

        return new SalesDashboardSheet(
            'Data Mentah 3 Bulan',
            [...$this->metadataRows(), [
                'Database', 'InvoiceDate', 'InvoiceNumber', 'CustomerID',
                'CustomerCode', 'CustomerName', 'SalesPersonID', 'SalesPersonName',
                'LiniPenjualan', 'ItemCode', 'ItemName', 'Grade', 'Discontinue',
                'StatusItem', 'Clearance', 'StatusClearance', 'ClearanceDiscount',
                'Quantity', 'Discount', 'Discount2', 'PriceLevelName', 'LineTotal', 'Remarks',
            ], ...$rows],
            self::HEADER_ROW,
            ['Q' => '0.00', 'R' => '#,##0.00', 'S' => '0.00', 'T' => '0.00', 'V' => '#,##0'],
        );
    }

    /** @return list<list<string>> */
    private function metadataRows(): array
    {
        $filters = (array) ($this->data['filters'] ?? []);
        $periodStart = $this->data['period_start'] ?? null;
        $periodEnd = $this->data['period_end'] ?? null;
        $database = (string) ($filters['database'] ?? 'BOTH');
        $status = strtoupper((string) ($filters['status'] ?? 'ALL'));
        $salesPersonId = $filters['sales_person_id'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        $periodText = ($periodStart instanceof DateTimeImmutable && $periodEnd instanceof DateTimeImmutable)
            ? $periodStart->format('d-m-Y').' s.d. '.$periodEnd->format('d-m-Y')
            : '-';

        return [
            ['Customer Analysis - Export Excel'],
            ['Database: '.$database.' | Status: '.$status.' | SalesPersonID: '.($salesPersonId ?: 'SEMUA')],
            ['Periode review: '.$periodText],
            ['Pencarian: '.($search !== '' ? $search : '-')],
            ['Tanggal ekspor: '.(new DateTimeImmutable())->format('d-m-Y H:i')],
        ];
    }
}
