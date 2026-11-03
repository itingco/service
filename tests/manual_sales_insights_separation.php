<?php

declare(strict_types=1);

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$routes = file_get_contents($root.'/routes/web.php') ?: '';
$oldView = file_get_contents($root.'/resources/views/sales-dashboard/index.blade.php') ?: '';
$newViewPath = $root.'/resources/views/sales-insights/index.blade.php';
$newControllerPath = $root.'/app/Http/Controllers/SalesInsightsController.php';

expectTrue(str_contains($routes, "'/sales-insights'"), 'Route /sales-insights belum tersedia.');
expectTrue(str_contains($routes, "sales-insights.index"), 'Nama route sales-insights.index belum tersedia.');
expectTrue(!str_contains($oldView, "sales-dashboard.partials.management-insights"), 'Panel advanced masih tertanam di halaman lama.');
expectTrue(str_contains($oldView, "sales-insights.index"), 'Tombol Dashboard Analisis Lanjutan belum tersedia di halaman lama.');
expectTrue(is_file($newControllerPath), 'SalesInsightsController belum tersedia.');
expectTrue(is_file($newViewPath), 'View sales-insights belum tersedia.');

$newView = is_file($newViewPath) ? (file_get_contents($newViewPath) ?: '') : '';
expectTrue(str_contains($newView, 'Sales Insights &amp; R&amp;D Dashboard'), 'Judul dashboard baru belum tersedia.');
expectTrue(str_contains($newView, 'research-insights'), 'Partial R&D belum digunakan pada dashboard baru.');
expectTrue(str_contains($newView, 'sales-dashboard-insights.js'), 'Asset grafik management belum digunakan pada dashboard baru.');

echo "Sales insights route separation checks passed.\n";
