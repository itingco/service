<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use App\Data\SalesKpi\SalesKpiFilter;
use App\Exports\SalesKpi\SalesKpiExport;
use App\Exports\SalesKpi\SalesKpiSheet;
use App\Models\SalesKpiTarget;
use App\Services\SalesKpi\SalesKpiService;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesKpiExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
        SalesKpiTarget::query()->create([
            'sales_line' => 'JAKARTA',
            'monthly_target' => 500_000_000,
        ]);
    }

    public function test_excel_export_returns_six_numeric_worksheets(): void
    {
        $this->bindCombinedKpiFakeWithTwentyFiveDetails();

        $response = $this->actingAsDashboardAdmin()
            ->get('/sales-kpi/export/excel?end_date=2026-07-20&search=HAMMER');
        $response->assertOk();
        $response->assertHeader('content-disposition');
        self::assertStringContainsString(
            'Sales_KPI_GABUNGAN_2026-07-20.xlsx',
            (string) $response->headers->get('content-disposition'),
        );

        $report = $this->app->make(SalesKpiService::class)
            ->generate(SalesKpiFilter::from('2026-07-20', search: 'HAMMER'));
        $export = new SalesKpiExport($report);
        $titles = array_map(
            static fn (SalesKpiSheet $sheet): string => $sheet->title(),
            $export->sheets(),
        );

        self::assertSame([
            'Ringkasan KPI',
            'Target Omzet',
            'Growth',
            'Discontinued Clearance',
            'Grade A B',
            'Detail Item',
        ], $titles);

        $spreadsheet = $this->openWorkbook($export, 'sales-kpi-combined-test.xlsx');
        $summary = $spreadsheet->getSheetByName('Ringkasan KPI');
        $detail = $spreadsheet->getSheetByName('Detail Item');

        self::assertCount(6, $spreadsheet->getSheetNames());
        self::assertSame('Target Gabungan', $summary->getCell('B7')->getValue());
        self::assertSame('Omzet AS_INGCO', $summary->getCell('C7')->getValue());
        self::assertSame('Omzet AS_SMI', $summary->getCell('D7')->getValue());
        self::assertSame('Omzet Gabungan', $summary->getCell('E7')->getValue());
        self::assertSame(500_000_000.0, (float) $summary->getCell('B8')->getValue());
        self::assertSame('Database', $detail->getCell('A7')->getValue());
        self::assertSame(25, $detail->getHighestDataRow() - 7);
        self::assertContains($detail->getCell('A8')->getValue(), ['AS_INGCO', 'AS_SMI']);
    }


    public function test_detail_excel_export_includes_all_rows_matching_detail_search(): void
    {
        $this->bindCombinedKpiFakeWithTwentyFiveDetails();

        $response = $this->actingAsDashboardAdmin()->get('/sales-kpi/details/export/excel?'.http_build_query([
            'end_date' => '2026-07-20',
            'line' => '',
            'discontinue' => 'all',
            'clearance' => 'all',
            'grade' => 'ALL',
            'search' => '',
            'detail_type' => 'risk',
            'detail_search' => 'HAMMER',
        ]));

        $response->assertOk();
        self::assertStringContainsString(
            'Sales_KPI_Detail_RISK_2026-07-20.xlsx',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_pdf_export_returns_combined_pdf_download(): void
    {
        $this->bindCombinedKpiFakeWithTwentyFiveDetails();

        $response = $this->actingAsDashboardAdmin()
            ->get('/sales-kpi/export/pdf?end_date=2026-07-20');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        self::assertStringContainsString(
            'Sales_KPI_GABUNGAN_2026-07-20.pdf',
            (string) $response->headers->get('content-disposition'),
        );
        self::assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    private function openWorkbook(SalesKpiExport $export, string $filename): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = sys_get_temp_dir().'/'.$filename;
        file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));

        return IOFactory::load($path);
    }

    private function bindCombinedKpiFakeWithTwentyFiveDetails(): void
    {
        $ingco = [];
        $smi = [];
        for ($index = 1; $index <= 25; $index++) {
            $row = $this->row(sprintf('INV-%03d', $index), 'HAMMER', 10_000_000);
            if ($index <= 13) {
                $ingco[] = $row;
            } else {
                $smi[] = $row;
            }
        }

        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource([
            'INGCO' => $ingco,
            'SMI' => $smi,
        ]));
    }

    private function row(string $invoiceNumber, string $itemName, int $lineTotal): object
    {
        return (object) [
            'InvoiceDate' => '2026-07-20',
            'InvoiceNumber' => $invoiceNumber,
            'LiniPenjualan' => 'JAKARTA',
            'ItemCode' => 'ABC01',
            'ItemName' => $itemName,
            'Quantity' => 2,
            'LineTotal' => $lineTotal,
            'Discontinue' => 1,
            'StatusItem' => 'DISCONTINUE',
            'Grade' => 'A',
            'StatusClearanceSale' => 'CLEARANCE SALE',
            'ClearanceSaleDiscount' => 0.2,
        ];
    }
}
