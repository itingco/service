<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ProductMasterPermissionsTest extends TestCase
{
    public function test_product_master_permissions_exist_in_role_catalog(): void
    {
        $menus = (array) config('dashboard_security.menus', []);

        self::assertArrayHasKey('product_focus', $menus);
        self::assertSame('Product Focus', $menus['product_focus']['label'] ?? null);
        self::assertSame('Master Data', $menus['product_focus']['group'] ?? null);

        self::assertArrayHasKey('similar_items', $menus);
        self::assertSame('Similar Items', $menus['similar_items']['label'] ?? null);
        self::assertSame('Master Data', $menus['similar_items']['group'] ?? null);

        $landingOrder = (array) config('dashboard_security.landing_menu_order', []);
        self::assertContains('product_focus', $landingOrder);
        self::assertContains('similar_items', $landingOrder);
    }

    public function test_product_master_routes_use_independent_permissions(): void
    {
        $productFocus = Route::getRoutes()->getByName('product-strategy.product-focus.index');
        $similarItems = Route::getRoutes()->getByName('product-strategy.similar-items.index');

        self::assertNotNull($productFocus);
        self::assertNotNull($similarItems);

        $productFocusMiddleware = $productFocus->gatherMiddleware();
        $similarItemsMiddleware = $similarItems->gatherMiddleware();

        self::assertContains('dashboard.permission:product_focus', $productFocusMiddleware);
        self::assertNotContains('dashboard.permission:business_analytics', $productFocusMiddleware);
        self::assertContains('dashboard.permission:similar_items', $similarItemsMiddleware);
        self::assertNotContains('dashboard.permission:business_analytics', $similarItemsMiddleware);
    }

    public function test_sidebar_and_stock_review_button_use_new_permissions(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $strategy = file_get_contents(resource_path('views/stock-aging/strategy-review.blade.php'));

        self::assertIsString($layout);
        self::assertIsString($strategy);

        self::assertStringContainsString("\$dashboardMenuAccess['product_focus']", $layout);
        self::assertStringContainsString("\$dashboardMenuAccess['similar_items']", $layout);
        self::assertStringContainsString('MASTER DATA', $layout);

        $buttonPos = strpos($strategy, 'Kelola Product Focus');
        self::assertNotFalse($buttonPos);
        $buttonContext = substr($strategy, max(0, $buttonPos - 300), 600);
        self::assertStringContainsString("\$dashboardMenuAccess['product_focus']", $buttonContext);
    }

    public function test_backfill_migration_grants_new_permissions_to_existing_business_analytics_roles(): void
    {
        $this->artisan('migrate', ['--database' => 'sqlite_targets'])->assertExitCode(0);

        $db = DB::connection('sqlite_targets');
        $roleKey = 'legacy_ba_test';
        $otherRoleKey = 'legacy_no_ba_test';
        $now = now();

        $db->table('dashboard_role_menus')->whereIn('role_key', [$roleKey, $otherRoleKey])->delete();
        $db->table('dashboard_roles')->whereIn('role_key', [$roleKey, $otherRoleKey])->delete();

        foreach ([$roleKey, $otherRoleKey] as $key) {
            $db->table('dashboard_roles')->insert([
                'role_key' => $key,
                'name' => $key,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $db->table('dashboard_role_menus')->insert([
            'role_key' => $roleKey,
            'menu_key' => 'business_analytics',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $migrationPath = database_path('migrations/2026_09_06_000600_backfill_product_master_permissions.php');
        self::assertFileExists($migrationPath);

        $migration = require $migrationPath;
        $migration->up();

        $grants = $db->table('dashboard_role_menus')
            ->where('role_key', $roleKey)
            ->pluck('menu_key')
            ->all();

        self::assertContains('business_analytics', $grants);
        self::assertContains('product_focus', $grants);
        self::assertContains('similar_items', $grants);

        $otherGrants = $db->table('dashboard_role_menus')
            ->where('role_key', $otherRoleKey)
            ->pluck('menu_key')
            ->all();

        self::assertNotContains('product_focus', $otherGrants);
        self::assertNotContains('similar_items', $otherGrants);
    }
}
