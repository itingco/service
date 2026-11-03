<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/app/Support/StockStrategyWarehouseSelection.php';

use App\Support\StockStrategyWarehouseSelection;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$rows = [
    ['source' => 'INGCO', 'warehouse_id' => 1, 'item_code' => 'A'],
    ['source' => 'INGCO', 'warehouse_id' => 2, 'item_code' => 'B'],
    ['source' => 'SMI', 'warehouse_id' => 1, 'item_code' => 'C'],
    ['source' => 'SMI', 'warehouse_id' => 3, 'item_code' => 'D'],
];

$keys = StockStrategyWarehouseSelection::normalize(['ingco|1', ' SMI|3 ', 'INGCO|1', 'invalid']);
$assert($keys === ['INGCO|1', 'SMI|3'], 'Selection must normalize, deduplicate, and discard invalid keys.');

$selected = StockStrategyWarehouseSelection::filterRows($rows, ['INGCO|1', 'SMI|3']);
$assert(array_column($selected, 'item_code') === ['A', 'D'], 'Multiple warehouses must be included together.');

$sameIdDifferentSource = StockStrategyWarehouseSelection::filterRows($rows, ['SMI|1']);
$assert(array_column($sameIdDifferentSource, 'item_code') === ['C'], 'Warehouse ID collision across sources must not leak rows.');

$all = StockStrategyWarehouseSelection::filterRows($rows, []);
$assert(count($all) === 4, 'Empty selection must mean all warehouses.');

$legacyScalar = StockStrategyWarehouseSelection::normalize('ingco|2');
$assert($legacyScalar === ['INGCO|2'], 'Legacy single warehouse query must remain compatible.');

$options = StockStrategyWarehouseSelection::optionsFromSources([
    'INGCO' => [
        'database' => 'AS_INGCO',
        'warehouses' => [
            ['warehouse_id' => 1, 'warehouse_name' => 'G-JUANDA', 'warehouse_code' => 'JND'],
            ['warehouse_id' => 2, 'warehouse_name' => '', 'warehouse_code' => 'G-BOGOR'],
        ],
    ],
    'SMI' => [
        'database' => 'AS_SMI',
        'warehouses' => [
            ['warehouse_id' => 1, 'warehouse_name' => 'G-JUANDA', 'warehouse_code' => 'JND'],
        ],
    ],
]);
$assert(isset($options['INGCO|1'], $options['SMI|1']), 'Options must keep same WarehouseID separate across databases.');
$assert($options['INGCO|1'] === 'AS_INGCO · G-JUANDA', 'Option label must include database and warehouse name.');
$assert($options['INGCO|2'] === 'AS_INGCO · G-BOGOR', 'Warehouse code must be used when name is empty.');

fwrite(STDOUT, "PASS: multi-warehouse selection + option builder.\n");
