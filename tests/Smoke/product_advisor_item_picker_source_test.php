<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = (string) file_get_contents($root.'/resources/views/business-analytics/product-advisor.blade.php');
$routes = (string) file_get_contents($root.'/routes/web.php');
$controller = (string) file_get_contents($root.'/app/Http/Controllers/ProductStrategyAdvisorController.php');

$checks = [
    [str_contains($routes, "->name('business-analytics.product-advisor.items')"), 'product advisor item search endpoint route missing'],
    [str_contains($controller, 'function items('), 'product advisor item search controller action missing'],
    [str_contains($view, 'id="productAdvisorSearch"'), 'live product search input missing'],
    [str_contains($view, 'id="productAdvisorItemCode"'), 'selected item hidden field missing'],
    [str_contains($view, 'id="productAdvisorResults"'), 'live search result panel missing'],
    [str_contains($view, "route('business-analytics.product-advisor.items')"), 'view is not wired to live item endpoint'],
    [str_contains($view, 'fetch('), 'live item endpoint is not fetched from the browser'],
    [str_contains($view, 'Pilih item dari hasil pencarian'), 'selection validation message missing'],
    [!str_contains($view, '<datalist id="productAdvisorItems">'), 'legacy datalist picker must be removed'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: Product Strategy Advisor uses a live searchable item picker.\n");
