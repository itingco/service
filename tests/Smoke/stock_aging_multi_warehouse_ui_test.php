<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$component = $root.'/resources/views/components/warehouse-multi-select.blade.php';
$controller = $root.'/app/Http/Controllers/StockAgingController.php';
$index = $root.'/resources/views/stock-aging/index.blade.php';
$strategy = $root.'/resources/views/stock-aging/strategy-review.blade.php';

if (!is_file($component)) $fail('Reusable warehouse combo component must exist.');
if (!is_file($controller)) $fail('StockAgingController overwrite file must exist.');
if (!is_file($index)) $fail('Stock Aging index overwrite file must exist.');

$componentText = file_get_contents($component) ?: '';
$controllerText = file_get_contents($controller) ?: '';
$indexText = file_get_contents($index) ?: '';
$strategyText = file_get_contents($strategy) ?: '';

foreach (['data-warehouse-search', 'data-warehouse-checkbox', 'data-warehouse-select-all', 'data-warehouse-clear', 'data-warehouse-chips'] as $needle) {
    if (!str_contains($componentText, $needle)) $fail("Combo component missing {$needle}.");
}
if (!str_contains($controllerText, "StockStrategyWarehouseSelection::normalize")) $fail('Stock Aging controller must normalize warehouse query.');
if (!str_contains($controllerText, "StockStrategyWarehouseSelection::filterRows")) $fail('Stock Aging controller must filter warehouse rows.');
if (!str_contains($controllerText, "StockAgingAggregator")) $fail('Stock Aging controller must re-aggregate filtered rows.');
if (!str_contains($indexText, "<x-warehouse-multi-select")) $fail('Stock Aging page must render friendly warehouse combo.');
if (!str_contains($strategyText, "<x-warehouse-multi-select")) $fail('Strategy Review must use the same friendly warehouse combo.');
if (str_contains($strategyText, 'Ctrl/Cmd + klik')) $fail('Old native multi-select helper text must be removed.');

echo "PASS: stock aging + strategy review multi-warehouse combo wiring.\n";
