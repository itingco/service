<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root.'/app/Http/Controllers/StockStrategyReviewController.php') ?: '';
$view = file_get_contents($root.'/resources/views/stock-aging/strategy-review.blade.php') ?: '';
$component = file_get_contents($root.'/resources/views/components/warehouse-multi-select.blade.php') ?: '';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($controller, 'StockStrategyWarehouseSelection::normalize'), 'Controller must normalize warehouse input.');
$assert(str_contains($controller, 'StockStrategyWarehouseSelection::filterRows'), 'Controller must filter selected warehouses.');
$assert(str_contains($controller, 'StockStrategyWarehouseSelection::optionsFromSources'), 'Controller must provide source-aware warehouse options.');
$assert(str_contains($controller, "'warehouseKeys'"), 'Controller must pass warehouseKeys to Blade.');
$assert(str_contains($controller, "'warehouseOptions'"), 'Controller must pass warehouseOptions to Blade.');
$assert(str_contains($view, '<x-warehouse-multi-select'), 'Strategy Review must use reusable combo component.');
$assert(str_contains($component, 'name="{{ $fieldName }}"'), 'Reusable component must submit the warehouse array field.');
$assert(str_contains($component, 'multiple'), 'Underlying form field must support multiple warehouses.');
$assert(str_contains($component, 'data-warehouse-search'), 'Warehouse combo must be searchable.');
$assert(str_contains($component, 'data-warehouse-select-all'), 'Warehouse combo must support select all.');
$assert(str_contains($component, 'data-warehouse-clear'), 'Warehouse combo must support clear selection.');
$assert(str_contains($component, 'Kosong = semua warehouse'), 'UI must explain all-warehouse behavior.');

fwrite(STDOUT, "PASS: Strategy Review friendly multi-warehouse combo wiring.\n");
