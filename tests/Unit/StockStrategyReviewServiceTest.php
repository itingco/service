<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProductStrategy\StockStrategyReviewService;
use PHPUnit\Framework\TestCase;

final class StockStrategyReviewServiceTest extends TestCase
{
    public function test_rows_are_ordered_by_product_focus_then_age_then_stock_value(): void
    {
        $service = new StockStrategyReviewService();
        $rows = [
            ['source'=>'INGCO','warehouse_id'=>1,'warehouse_name'=>'MAIN','item_code'=>'C','item_name'=>'C','age'=>180,'bucket_key'=>'over-120','bucket_label'=>'>120 Hari','qty'=>1,'sisa_stock'=>900,'sales_mtd'=>0],
            ['source'=>'INGCO','warehouse_id'=>1,'warehouse_name'=>'MAIN','item_code'=>'A','item_name'=>'A','age'=>40,'bucket_key'=>'31-60','bucket_label'=>'31-60 Hari','qty'=>1,'sisa_stock'=>100,'sales_mtd'=>0],
            ['source'=>'INGCO','warehouse_id'=>1,'warehouse_name'=>'MAIN','item_code'=>'B','item_name'=>'B','age'=>120,'bucket_key'=>'91-120','bucket_label'=>'91-120 Hari','qty'=>1,'sisa_stock'=>500,'sales_mtd'=>0],
            ['source'=>'INGCO','warehouse_id'=>1,'warehouse_name'=>'MAIN','item_code'=>'D','item_name'=>'D','age'=>120,'bucket_key'=>'91-120','bucket_label'=>'91-120 Hari','qty'=>1,'sisa_stock'=>700,'sales_mtd'=>0],
        ];
        $focus = [
            'A' => ['focus_category'=>'TOP 25','focus_rank'=>25],
            'B' => ['focus_category'=>'TOP 50','focus_rank'=>50],
            'D' => ['focus_category'=>'TOP 50','focus_rank'=>50],
        ];

        $result = $service->build($rows, $focus);
        $items = array_column($result['warehouses'][0]['items'], 'item_code');

        self::assertSame(['A', 'D', 'B', 'C'], $items);
        self::assertSame('NON FOCUS', $result['warehouses'][0]['items'][3]['product_focus']);
    }
}
