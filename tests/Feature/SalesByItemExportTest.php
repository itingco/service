<?php

namespace Tests\Feature;

use App\Data\SalesByItemFilter;
use App\Data\SalesByItemReport;
use App\Services\SalesByItemService;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;
use Tests\TestCase;

class SalesByItemExportTest extends TestCase
{
    public function test_excel_route_downloads_filtered_filename(): void
    {
        Excel::fake();
        $filter = SalesByItemFilter::from('ingco', '2026-07-22');
        $service = Mockery::mock(SalesByItemService::class);
        $service->shouldReceive('generate')->once()->andReturn(SalesByItemReport::empty($filter));
        $this->app->instance(SalesByItemService::class, $service);

        $this->get('/sales-by-item/export/excel?database=ingco&end_date=2026-07-22');

        Excel::assertDownloaded('Sales_By_Item_INGCO_2026-07-01_2026-07-22.xlsx');
    }
}
