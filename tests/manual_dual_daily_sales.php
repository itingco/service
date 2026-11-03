<?php

declare(strict_types=1);

function assertContainsDual(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$web = file_get_contents($root.'/resources/views/sales-dashboard/index.blade.php') ?: '';
$webLabels = str_replace(['<br>', '<br/>', '<br />'], ' ', $web);
$pdf = file_get_contents($root.'/resources/views/sales-dashboard/pdf.blade.php') ?: '';
$bladeExcel = file_get_contents($root.'/resources/views/sales-dashboard/export-excel.blade.php') ?: '';
$export = file_get_contents($root.'/app/Exports/SalesDashboardExport.php') ?: '';
$insightService = file_get_contents($root.'/app/Services/SalesDashboardInsightService.php') ?: '';
$insightView = file_get_contents($root.'/resources/views/sales-insights/partials/management-insights.blade.php') ?: '';
$insightExport = file_get_contents($root.'/app/Exports/SalesInsightsExport.php') ?: '';

foreach ([$webLabels, $pdf, $bladeExcel, $export] as $content) {
    assertContainsDual('Daily Sales Target / HK', $content, 'Label static Daily Sales Target / HK belum konsisten.');
    assertContainsDual('Required Daily Sales', $content, 'Label Required Daily Sales belum konsisten.');
}

assertContainsDual('requiredDailySales', $web, 'Web belum menampilkan nilai Required Daily Sales.');
assertContainsDual('requiredDailySales', $pdf, 'PDF belum menampilkan nilai Required Daily Sales.');
assertContainsDual('requiredDailySales', $bladeExcel, 'Legacy Excel blade belum menampilkan nilai Required Daily Sales.');
assertContainsDual('requiredDailySales', $export, 'Excel export belum menampilkan nilai Required Daily Sales.');
assertContainsDual("'daily_sales_target_per_hk' => \$row->dailyTarget", $insightService, 'Sales Insights belum membawa static daily target.');
assertContainsDual("'required_daily_sales' => \$row->requiredDailySales", $insightService, 'Sales Insights belum memakai nilai dinamis yang benar.');
assertContainsDual('Daily Target / HK', $insightView, 'Pace Sales Insights belum menampilkan static daily target.');
assertContainsDual('Required / Hari', $insightView, 'Pace Sales Insights belum menampilkan required daily sales.');
assertContainsDual('Daily Sales Target / HK', $insightExport, 'Export Sales Insights belum memuat static daily target.');
assertContainsDual('Required Daily Sales', $insightExport, 'Export Sales Insights belum memuat required daily sales.');

echo "Dual daily sales display checks passed.\n";
