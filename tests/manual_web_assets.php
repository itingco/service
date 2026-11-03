<?php

declare(strict_types=1);

$required = [
    'app/Http/Requests/SalesDashboardRequest.php',
    'app/Http/Requests/SalesDashboardDetailRequest.php',
    'app/Http/Controllers/SalesDashboardController.php',
    'app/Services/SalesDashboardDetailQueryService.php',
    'app/Exports/SalesDashboardExport.php',
    'app/Exports/SalesDashboard/SalesDashboardSheet.php',
    'resources/views/sales-dashboard/index.blade.php',
    'resources/views/sales-dashboard/pdf.blade.php',
    'public/js/sales-dashboard-datatable.js',
];

foreach ($required as $file) {
    if (!is_file(__DIR__.'/../'.$file)) {
        throw new RuntimeException("Required file missing: {$file}");
    }
}

$routes = file_get_contents(__DIR__.'/../routes/web.php');
foreach (['sales-dashboard.index', 'sales-dashboard.details', 'sales-dashboard.export.excel', 'sales-dashboard.export.pdf'] as $route) {
    if (!str_contains($routes, $route)) {
        throw new RuntimeException("Route missing: {$route}");
    }
}

$blade = file_get_contents(__DIR__.'/../resources/views/sales-dashboard/index.blade.php');
foreach ([
    'Monthly Sales',
    'Contribution',
    'Data Mentah Penjualan',
    'Export Semua Data ke Excel',
    'data-sales-raw-table',
    'window.salesDashboardTableConfig',
    'sales-dashboard-datatable.js',
] as $needle) {
    if (!str_contains($blade, $needle)) {
        throw new RuntimeException("Dashboard content missing: {$needle}");
    }
}

foreach (['salesTargetChart', 'salesComparisonChart', 'window.salesDashboardData', 'sales-dashboard-charts.js'] as $removed) {
    if (str_contains($blade, $removed)) {
        throw new RuntimeException("Sales chart content must be removed: {$removed}");
    }
}

$javascript = file_get_contents(__DIR__.'/../public/js/sales-dashboard-datatable.js');
foreach (['recordsFiltered', 'line_contribution', 'fetch(', 'data-raw-search'] as $needle) {
    if (!str_contains($javascript, $needle)) {
        throw new RuntimeException("Sales raw DataTable behavior missing: {$needle}");
    }
}

print("manual_web_assets: OK\n");
