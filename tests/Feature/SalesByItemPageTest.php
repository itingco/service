<?php

namespace Tests\Feature;

use App\Data\SalesByItemFilter;
use App\Data\SalesByItemReport;
use App\Services\SalesByItemService;
use Mockery;
use Tests\TestCase;

class SalesByItemPageTest extends TestCase
{
    public function test_page_uses_default_filter_and_renders_report(): void
    {
        $filter = SalesByItemFilter::from('all', now()->format('Y-m-d'));
        $service = Mockery::mock(SalesByItemService::class);
        $service->shouldReceive('generate')->once()->andReturn(SalesByItemReport::empty($filter));
        $this->app->instance(SalesByItemService::class, $service);

        $this->get('/sales-by-item')
            ->assertOk()
            ->assertSee('Sales By Item')
            ->assertSee('Semua Database');
    }

    public function test_invalid_database_is_rejected(): void
    {
        $this->get('/sales-by-item?database=wrong&end_date=2026-07-22')
            ->assertSessionHasErrors('database');
    }
}
