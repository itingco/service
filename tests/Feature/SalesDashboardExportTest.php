<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use App\Data\SalesDashboardFilter;
use App\Exports\SalesDashboardExport;
use App\Exports\SalesDashboard\SalesDashboardSheet;
use App\Services\SalesDashboardService;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesDashboardExportTest extends TestCase
{
    public function test_operational_excel_contains_only_summary_and_raw_data_sheets(): void
    {
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => [$this->row('INV-001', 'JAKARTA', 300)],
            'SMI' => [$this->row('INV-002', 'GERAI BOGOR', 100)],
        ]));

        $response = $this->actingAsDashboardAdmin()->get('/sales-dashboard/export/excel?database=all&end_date=2026-07-22&item_status=all');
        $response->assertOk()->assertHeader('content-disposition');

        $report = $this->app->make(SalesDashboardService::class)
            ->generate(SalesDashboardFilter::from('all', '2026-07-22', 'all'));
        $export = new SalesDashboardExport($report);
        $titles = array_map(static fn (SalesDashboardSheet $sheet): string => $sheet->title(), $export->sheets());
        self::assertSame(['Ringkasan Sales', 'Data Mentah'], $titles);

        $path = sys_get_temp_dir().'/sales-dashboard-raw-test.xlsx';
        file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
        $workbook = IOFactory::load($path);
        $summary = $workbook->getSheetByName('Ringkasan Sales');
        $raw = $workbook->getSheetByName('Data Mentah');

        self::assertSame('Contribution terhadap Total Omzet', $summary->getCell('O6')->getValue());
        self::assertSame('Contribution Lini', $raw->getCell('E6')->getValue());
        self::assertSame('AS_INGCO', $raw->getCell('A7')->getValue());
        self::assertEqualsWithDelta(0.75, (float) $raw->getCell('E7')->getValue(), .0001);
    }

    private function row(string $invoice, string $line, int $total): object
    {
        return (object) [
            'InvoiceDate' => '2026-07-22',
            'InvoiceNumber' => $invoice,
            'LiniPenjualan' => $line,
            'CustomerCode' => 'C001',
            'CustomerName' => 'Customer A',
            'SalesPersonName' => 'Sales A',
            'ItemCode' => 'ITEM-1',
            'ItemName' => 'Hammer',
            'Grade' => 'A',
            'Quantity' => 1,
            'LineTotal' => $total,
            'Discontinue' => 0,
            'StatusItem' => 'ACTIVE',
            'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
            'ClearanceSaleDiscount' => 0,
        ];
    }
}
