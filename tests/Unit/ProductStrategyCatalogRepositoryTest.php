<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProductStrategy\ProductStrategyCatalogRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProductStrategyCatalogRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'sqlite_targets'])->assertExitCode(0);

        config([
            'product_strategy.master_connection' => 'sqlite_targets',
            'product_strategy.focus_table' => 'ItemFocus',
            'product_strategy.similar_table' => 'ItemSimilar',
            'product_strategy.analytics_connection' => 'sqlite_targets',
            'product_strategy.analytics_table' => 'business_analytics_monthly_sales',
        ]);

        $schema = Schema::connection('sqlite_targets');
        $schema->dropIfExists('ItemSimilar');
        $schema->dropIfExists('ItemFocus');

        $schema->create('ItemFocus', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('ItemCode', 100)->unique();
            $table->string('ItemName', 255)->nullable();
            $table->string('ItemFocusType', 80);
            $table->timestamp('Updated')->nullable();
        });

        $schema->create('ItemSimilar', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('ItemCode', 100);
            $table->string('SimilarItemCode', 100);
            $table->unique(['ItemCode', 'SimilarItemCode']);
        });

        DB::connection('sqlite_targets')->table('business_analytics_monthly_sales')->delete();
    }

    public function test_product_focus_stores_item_name_and_is_sorted_by_priority_number(): void
    {
        $this->seedItem('ITEM-25', 'Analytics Twenty Five');
        $this->seedItem('ITEM-50', 'Analytics Fifty');

        $repository = $this->app->make(ProductStrategyCatalogRepository::class);

        $repository->saveProductFocus('ITEM-50', 'Master Fifty', 'Top 50');
        $repository->saveProductFocus('ITEM-25', 'Master Twenty Five', 'Top 25');
        $repository->saveProductFocus('ITEM-25', 'Master Twenty Five Updated', 'TOP 20');

        $rows = $repository->productFocusRows();

        self::assertCount(2, $rows);
        self::assertSame('ITEM-25', $rows[0]['item_code']);
        self::assertSame(20, $rows[0]['focus_rank']);
        self::assertSame('Master Twenty Five Updated', $rows[0]['item_name']);
        self::assertSame('ITEM-50', $rows[1]['item_code']);
        self::assertSame('Master Fifty', $rows[1]['item_name']);
        self::assertCount(1, DB::connection('sqlite_targets')->table('ItemFocus')->where('ItemCode', 'ITEM-25')->get());
    }

    public function test_product_focus_falls_back_to_analytics_when_item_name_is_blank(): void
    {
        $this->seedItem('ABN10301', 'HEKTER TEMBAK ANGIN');

        $repository = $this->app->make(ProductStrategyCatalogRepository::class);
        $repository->saveProductFocus('ABN10301', '', 'TOP 25');

        $row = DB::connection('sqlite_targets')->table('ItemFocus')->where('ItemCode', 'ABN10301')->first();
        self::assertSame('HEKTER TEMBAK ANGIN', $row->ItemName);

        $rows = $repository->productFocusRows();
        self::assertSame('HEKTER TEMBAK ANGIN', $rows[0]['item_name']);
    }

    public function test_existing_null_item_name_is_displayed_from_analytics_fallback(): void
    {
        $this->seedItem('CIWLI20451', 'MESIN BUKA BAUT CORDLESS 20V 405 N.M');

        DB::connection('sqlite_targets')->table('ItemFocus')->insert([
            'ItemCode' => 'CIWLI20451',
            'ItemName' => null,
            'ItemFocusType' => 'TOP 25',
            'Updated' => now(),
        ]);

        $repository = $this->app->make(ProductStrategyCatalogRepository::class);
        $rows = $repository->productFocusRows();

        self::assertSame('MESIN BUKA BAUT CORDLESS 20V 405 N.M', $rows[0]['item_name']);
    }

    public function test_replace_product_focus_keeps_excel_item_name(): void
    {
        $repository = $this->app->make(ProductStrategyCatalogRepository::class);

        $processed = $repository->replaceProductFocus([
            [
                'item_code' => 'ABN10301',
                'item_name' => 'HEKTER TEMBAK ANGIN',
                'focus_category' => '25 BEST PRODUCT',
            ],
            [
                'item_code' => 'CIWLI20451',
                'item_name' => 'MESIN BUKA BAUT CORDLESS 20V 405 N.M',
                'focus_category' => '25 BEST PRODUCT',
            ],
        ]);

        self::assertSame(2, $processed);
        self::assertSame(
            'HEKTER TEMBAK ANGIN',
            DB::connection('sqlite_targets')->table('ItemFocus')->where('ItemCode', 'ABN10301')->value('ItemName'),
        );
        self::assertSame(
            'MESIN BUKA BAUT CORDLESS 20V 405 N.M',
            DB::connection('sqlite_targets')->table('ItemFocus')->where('ItemCode', 'CIWLI20451')->value('ItemName'),
        );
    }

    public function test_similar_items_are_directional_and_duplicate_pairs_are_not_duplicated(): void
    {
        $this->seedItem('ITEM-1', 'Main Item');
        $this->seedItem('ITEM-2', 'Alternative Item');

        $repository = $this->app->make(ProductStrategyCatalogRepository::class);

        $repository->saveSimilarItem('ITEM-1', 'ignored', 'ITEM-2', 'ignored');
        $repository->saveSimilarItem('ITEM-1', 'ignored again', 'ITEM-2', 'ignored again');

        $rows = $repository->similarFor('ITEM-1');

        self::assertCount(1, $rows);
        self::assertSame('ITEM-2', $rows[0]['similar_item_code']);
        self::assertSame('Alternative Item', $rows[0]['similar_item_name']);
        self::assertSame([], $repository->similarFor('ITEM-2'));
        self::assertSame(1, DB::connection('sqlite_targets')->table('ItemSimilar')->count());
    }

    public function test_search_can_match_item_name_from_item_focus_master(): void
    {
        $repository = $this->app->make(ProductStrategyCatalogRepository::class);
        $repository->saveProductFocus('AG750282', 'Angle Grinder 750W', 'TOP 25');

        $rows = $repository->productFocusRows('grinder');

        self::assertCount(1, $rows);
        self::assertSame('AG750282', $rows[0]['item_code']);
    }

    private function seedItem(string $itemCode, string $itemName): void
    {
        DB::connection('sqlite_targets')->table('business_analytics_monthly_sales')->insert([
            'period' => '2026-10',
            'source' => 'INGCO',
            'sales_line' => 'LAINNYA',
            'customer_code' => '',
            'customer_name' => '',
            'salesperson_name' => '',
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'sales_amount' => 0,
            'quantity' => 0,
            'invoice_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
