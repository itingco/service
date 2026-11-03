<?php

declare(strict_types=1);

$required = [
    'app/Contracts/SalesKpiTargetLookup.php',
    'app/Http/Requests/SalesKpiRequest.php',
    'app/Http/Requests/SalesKpiDetailRequest.php',
    'app/Http/Requests/SalesKpiDetailExportRequest.php',
    'app/Http/Requests/SalesKpiTargetUpdateRequest.php',
    'app/Http/Controllers/SalesKpiController.php',
    'app/Http/Controllers/SalesKpiTargetController.php',
    'app/Services/SalesKpi/SalesKpiService.php',
    'app/Services/SalesKpi/SalesKpiCombinedDataService.php',
    'app/Services/SalesKpi/SalesKpiDetailQueryService.php',
    'app/Services/SalesKpi/SalesKpiTargetService.php',
    'app/Services/SalesKpi/SalesKpiTargetCatalogService.php',
    'app/Exports/SalesKpi/SalesKpiExport.php',
    'app/Exports/SalesKpi/SalesKpiDetailExport.php',
    'database/migrations/2026_07_28_000000_create_sales_kpi_targets_table.php',
    'resources/views/auth/login.blade.php',
    'resources/views/sales-kpi/index.blade.php',
    'resources/views/sales-kpi/targets/index.blade.php',
    'resources/views/sales-kpi/partials/detail-table.blade.php',
    'resources/views/sales-kpi/pdf.blade.php',
    'public/js/sales-kpi-charts.js',
    'public/js/sales-kpi-datatable.js',
    'config/dashboard_auth.php',
];

foreach ($required as $file) {
    if (!is_file(__DIR__.'/../'.$file)) {
        throw new RuntimeException("Required KPI file missing: {$file}");
    }
}

$routes = file_get_contents(__DIR__.'/../routes/web.php');
foreach ([
    'dashboard.login',
    'dashboard.logout',
    'sales-kpi.index',
    'sales-kpi.details',
    'sales-kpi.details.export.excel',
    'sales-kpi.export.excel',
    'sales-kpi.export.pdf',
    'sales-kpi.targets.index',
    'sales-kpi.targets.update',
] as $route) {
    if (!str_contains($routes, $route)) {
        throw new RuntimeException("KPI route missing: {$route}");
    }
}

$blade = file_get_contents(__DIR__.'/../resources/views/sales-kpi/index.blade.php');
foreach ([
    'Ringkasan KPI',
    'Target Omzet',
    'Growth vs Last Month',
    'Discontinued &amp; Clearance',
    'Grade A &amp; Grade B',
    'Filter LiniPenjualan dan Detail',
    '$report->availableLines',
    'Target Gabungan',
    'Omzet AS_INGCO',
    'Omzet AS_SMI',
    'Omzet Gabungan',
    'data-kpi-recap-table',
    'data-kpi-detail-table',
    'kpiContributionPieChart',
    'kpiSummaryBarChart',
    'targetKpiChart',
    'growthKpiChart',
    'riskKpiChart',
    'gradeKpiChart',
    'window.salesKpiData',
    'window.salesKpiTableConfig',
    'sales-kpi-datatable.js',
    'Pengaturan Target',
] as $needle) {
    if (!str_contains($blade, $needle)) {
        throw new RuntimeException("KPI page content missing: {$needle}");
    }
}

foreach (['name="database"', 'Target AS_INGCO', 'Target AS_SMI', 'overallKpiChart', 'componentKpiChart'] as $removed) {
    if (str_contains($blade, $removed)) {
        throw new RuntimeException("Obsolete KPI content must be removed: {$removed}");
    }
}


$detailBlade = file_get_contents(__DIR__.'/../resources/views/sales-kpi/partials/detail-table.blade.php');
foreach (['Export Semua Hasil ke Excel', 'data-detail-export', 'sales-kpi.details.export.excel'] as $needle) {
    if (!str_contains($detailBlade, $needle)) {
        throw new RuntimeException("KPI detail export content missing: {$needle}");
    }
}

$targetBlade = file_get_contents(__DIR__.'/../resources/views/sales-kpi/targets/index.blade.php');
foreach (['Target Gabungan', 'Omzet Bulan Ini', 'Simpan Semua Perubahan', 'Tidak ada transaksi bulan ini'] as $needle) {
    if (!str_contains($targetBlade, $needle)) {
        throw new RuntimeException("Target settings content missing: {$needle}");
    }
}

$javascript = file_get_contents(__DIR__.'/../public/js/sales-kpi-charts.js');
$datatable = file_get_contents(__DIR__.'/../public/js/sales-kpi-datatable.js');
if (stripos($javascript, 'chart.js') !== false || stripos($blade, 'cdn.jsdelivr.net/npm/chart') !== false) {
    throw new RuntimeException('KPI charts must not use a Chart.js CDN.');
}
foreach (['recordsFiltered', 'data-kpi-recap-table', 'localeCompare', 'fetch('] as $needle) {
    if (!str_contains($datatable, $needle)) {
        throw new RuntimeException("DataTable behavior missing: {$needle}");
    }
}

print("manual_sales_kpi_assets: OK\n");
