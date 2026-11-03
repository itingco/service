<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SalesDataSource;
use App\Data\SalesDashboardFilter;
use App\Exports\SalesDashboard\SalesDashboardSheet;
use App\Exports\SalesInsightsExport;
use App\Services\SalesDashboardInsightService;
use App\Services\SalesDashboardService;
use App\Services\SalesResearchInsightService;
use Tests\Fakes\FakeSalesDataSource;
use Tests\TestCase;

final class SalesInsightsExportTest extends TestCase
{
    public function test_advanced_export_has_dedicated_management_and_rd_sheets(): void
    {
        $row = (object) [
            'InvoiceDate'=>'2026-07-22','InvoiceNumber'=>'INV-001','LiniPenjualan'=>'ONLINE',
            'CustomerCode'=>'C001','CustomerName'=>'Customer A','SalesPersonName'=>'Sales A',
            'ItemCode'=>'ITEM-01','ItemName'=>'Hammer','Grade'=>'A','Quantity'=>3,
            'Discount'=>5,'Discount2'=>0,'LineTotal'=>1_000_000,'Discontinue'=>0,
            'StatusItem'=>'ACTIVE','StatusClearanceSale'=>'BUKAN CLEARANCE SALE','ClearanceSaleDiscount'=>0,
        ];
        $this->app->instance(SalesDataSource::class, new FakeSalesDataSource(['INGCO'=>[$row],'SMI'=>[]]));
        $filter = SalesDashboardFilter::from('all', '2026-07-22', 'all');
        $service = $this->app->make(SalesDashboardService::class);
        $report = $service->generate($filter);
        $previous = $service->detailsForPeriod($filter, $filter->previousStartDate, $filter->previousEndDate);
        $export = new SalesInsightsExport(
            $report,
            (new SalesDashboardInsightService())->build($report),
            (new SalesResearchInsightService())->build($report->detailRows, $previous),
        );

        $titles = array_map(static fn (SalesDashboardSheet $sheet): string => $sheet->title(), $export->sheets());
        self::assertSame([
            'Management Insights', 'R&D Product Signals', 'R&D Demand Watch',
            'R&D Portfolio', 'Data Mentah MTD',
        ], $titles);
    }
}
