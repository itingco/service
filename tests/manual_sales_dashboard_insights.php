<?php

declare(strict_types=1);

require __DIR__.'/../app/Data/SalesDashboardFilter.php';
require __DIR__.'/../app/Data/SalesDashboardLineRow.php';
require __DIR__.'/../app/Data/SalesDashboardReport.php';
require __DIR__.'/../app/Data/SalesDashboardInsights.php';
require __DIR__.'/../app/Services/SalesDashboardInsightService.php';

use App\Data\SalesDashboardFilter;
use App\Data\SalesDashboardLineRow;
use App\Data\SalesDashboardReport;
use App\Services\SalesDashboardInsightService;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

$filter = SalesDashboardFilter::from('all', '2026-07-22', 'all');
$makeRow = static fn (
    string $line,
    float $target,
    float $dailyTarget,
    float $requiredDailySales,
    float $average,
    float $mtd,
    float $lmtd,
    ?float $growth,
    float $estimated,
    ?float $estimatedPercentage,
    ?float $achievement,
    float $gap,
    ?float $contribution,
): SalesDashboardLineRow => new SalesDashboardLineRow(
    line: $line,
    monthlyTarget: $target,
    dailyTarget: $dailyTarget,
    requiredDailySales: $requiredDailySales,
    dtd: 0,
    ldtd: 0,
    dtdGrowth: null,
    mtd: $mtd,
    lmtd: $lmtd,
    mtdGrowth: $growth,
    averageDailySales: $average,
    estimatedEom: $estimated,
    estimatedEomPercentage: $estimatedPercentage,
    achievementPercentage: $achievement,
    gap: $gap,
    contributionPercentage: $contribution,
);

$rows = [
    $makeRow('JAKARTA', 1000, 10, 100, 50, 500, 550, -0.10, 800, 0.80, 0.50, 500, 0.3125),
    $makeRow('ONLINE', 1000, 10, 0, 200, 1100, 900, 0.20, 1300, 1.30, 1.10, -100, 0.6875),
    $makeRow('NO TARGET', 0, 0, 0, 20, 100, 0, null, 150, null, null, -100, 0.0625),
];
$details = [];
foreach ([300, 220, 180, 130, 100, 70] as $index => $amount) {
    $number = $index + 1;
    $details[] = [
        'invoice_date' => sprintf('2026-07-%02d', $number),
        'customer_code' => 'C'.$number,
        'customer_name' => 'Customer '.$number,
        'sales_person_name' => $number <= 3 ? 'Sales Alpha' : 'Sales Beta',
        'item_code' => 'I-'.sprintf('%03d', $number),
        'item_name' => 'Item '.$number,
        'grade' => $number <= 2 ? 'A' : ($number <= 4 ? 'B' : ''),
        'line_total' => (float) $amount,
        'sales' => (float) $amount,
        'discontinued' => $number === 1,
        'clearance' => $number === 2,
    ];
}
$report = new SalesDashboardReport(
    $filter,
    $rows,
    $makeRow('TOTAL', 2000, 20, 100, 270, 1700, 1450, 0.1724, 2250, 1.125, 0.85, 300, 1.0),
    $details,
);
$insights = (new SalesDashboardInsightService())->build($report);

assertSameValue('BEHIND', $insights->linePaces[0]['pace_status'], 'Pace JAKARTA salah.');
assertSameValue(10.0, $insights->linePaces[0]['daily_sales_target_per_hk'], 'Pace harus membawa static Daily Target / HK.');
assertSameValue(100.0, $insights->linePaces[0]['required_daily_sales'], 'Pace harus memakai Required Daily Sales, bukan static Daily Target.');
assertSameValue('TARGET TERCAPAI', $insights->linePaces[1]['pace_status'], 'Pace ONLINE salah.');
assertSameValue('Customer 1', $insights->topCustomers[0]['label'], 'Top customer salah.');
assertSameValue(22, count($insights->dailyTrend['labels']), 'Jumlah tanggal tren salah.');

echo "Sales dashboard insight checks passed.\n";
