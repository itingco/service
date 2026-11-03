<?php

declare(strict_types=1);

function assertContainsText(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function assertNotContainsText(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$old = file_get_contents($root.'/app/Exports/SalesDashboardExport.php') ?: '';
$newPath = $root.'/app/Exports/SalesInsightsExport.php';
$controller = file_get_contents($root.'/app/Http/Controllers/SalesInsightsController.php') ?: '';

assertNotContainsText('Management Insights', $old, 'Export lama masih membawa sheet management.');
if (!is_file($newPath)) {
    throw new RuntimeException('SalesInsightsExport belum tersedia.');
}
$new = file_get_contents($newPath) ?: '';
assertContainsText("'Management Insights'", $new, 'Sheet Management Insights belum tersedia.');
assertContainsText("'R&D Product Signals'", $new, 'Sheet R&D Product Signals belum tersedia.');
assertContainsText("'R&D Demand Watch'", $new, 'Sheet R&D Demand Watch belum tersedia.');
assertContainsText('new SalesInsightsExport', $controller, 'Controller belum menggunakan SalesInsightsExport.');
assertNotContainsText("abort(501", $controller, 'Export masih berupa placeholder 501.');

echo "Sales insights export separation checks passed.\n";
