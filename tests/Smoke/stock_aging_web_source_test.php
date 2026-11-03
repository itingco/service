<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routes = (string) file_get_contents($root.'/routes/web.php');
$layout = (string) file_get_contents($root.'/resources/views/layouts/app.blade.php');
$index = (string) file_get_contents($root.'/resources/views/stock-aging/index.blade.php');
$details = (string) file_get_contents($root.'/resources/views/stock-aging/details.blade.php');
$request = (string) file_get_contents($root.'/app/Http/Requests/StockAgingRequest.php');
$normalizer = (string) file_get_contents($root.'/app/Support/StockAgingRowNormalizer.php');

$requiredFiles = [
    'app/Http/Controllers/StockAgingController.php',
    'app/Http/Requests/StockAgingRequest.php',
    'app/Http/Requests/StockAgingDetailRequest.php',
    'app/Services/StockAgingDetailQueryService.php',
    'resources/views/stock-aging/index.blade.php',
    'resources/views/stock-aging/details.blade.php',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root.'/'.$file)) {
        fwrite(STDERR, "FAIL: missing {$file}\n");
        exit(1);
    }
}

$checks = [
    [str_contains($routes, 'dashboard.permission:stock_aging'), 'stock_aging permission route missing'],
    [str_contains($routes, "->name('stock-aging.index')"), 'stock aging index route missing'],
    [str_contains($routes, "->name('stock-aging.details')"), 'stock aging detail route missing'],
    [str_contains($routes, "->name('stock-aging.details.export.excel')"), 'stock aging detail Excel export route missing'],
    [str_contains($details, 'stock-aging.details.export.excel'), 'stock aging detail Excel export button missing'],
    [str_contains($layout, 'app-sidebar'), 'collapsible sidebar markup missing'],
    [str_contains($layout, 'sidebarToggle'), 'sidebar toggle control missing'],
    [str_contains($layout, 'localStorage'), 'sidebar state persistence missing'],
    [str_contains($index, 'name="database"'), 'stock aging database filter missing'],
    [str_contains($index, 'Gabungan'), 'combined database option missing'],
    [str_contains($request, "Rule::in(['all', 'ingco', 'smi'])"), 'database request validation missing'],
    [str_contains($normalizer, "str_contains(") && str_contains($normalizer, "'SALE'"), 'warehouse SALE clearance derivation missing'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: stock aging database filter, SALE clearance, and sidebar source checks.\n");
