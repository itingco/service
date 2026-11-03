<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Contracts/SalesDataSource.php';
require_once __DIR__.'/../app/Contracts/SalesKpiTargetLookup.php';
require_once __DIR__.'/../app/Support/SalesLineName.php';
require_once __DIR__.'/../app/Exceptions/SalesDataException.php';
require_once __DIR__.'/../app/Data/SalesDashboardFilter.php';
require_once __DIR__.'/../app/Data/SalesDashboardLineRow.php';
require_once __DIR__.'/../app/Data/SalesDashboardReport.php';
require_once __DIR__.'/../app/Data/SalesDashboardDetailPage.php';
require_once __DIR__.'/../app/Support/SalesDashboardRowNormalizer.php';
require_once __DIR__.'/../app/Services/SalesDashboardAggregator.php';
require_once __DIR__.'/../app/Services/SalesDashboardService.php';
require_once __DIR__.'/../app/Services/SalesDashboardDetailQueryService.php';

use App\Contracts\SalesDataSource;
use App\Contracts\SalesKpiTargetLookup;
use App\Data\SalesDashboardFilter;
use App\Services\SalesDashboardAggregator;
use App\Services\SalesDashboardDetailQueryService;
use App\Services\SalesDashboardService;
use App\Support\SalesDashboardRowNormalizer;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function assertFloat(float $expected, float $actual, string $message, float $delta = 0.0001): void
{
    if (abs($expected - $actual) > $delta) {
        throw new RuntimeException($message." Expected {$expected}, got {$actual}");
    }
}

function assertTrueValue(bool $actual, string $message): void
{
    if (!$actual) {
        throw new RuntimeException($message);
    }
}


$config = require __DIR__.'/../config/sales_dashboard.php';
assertFloat(7145000000.0, (float) array_sum($config['targets']), 'Production targets total 7.145 billion.');

$filter = SalesDashboardFilter::from('all', '2026-07-22', 'all');
assertSameValue('2026-06-01', $filter->queryStartDate->format('Y-m-d'), 'Query starts on previous month day one.');
assertSameValue('2026-07-01', $filter->currentStartDate->format('Y-m-d'), 'Current period starts day one.');
assertSameValue('2026-06-22', $filter->previousEndDate->format('Y-m-d'), 'Previous MTD ends on comparable day.');
assertSameValue('2026-07-21', $filter->previousDayDate->format('Y-m-d'), 'LDTD uses prior calendar day.');
assertSameValue(27, $filter->totalSalesDays, 'July 2026 has 27 non-Sunday sales days.');
assertSameValue(21, $filter->elapsedDays, '22 July uses 21 elapsed days like the reference.');
assertSameValue(6, $filter->remainingSalesDays, 'Reference has six remaining sales days.');
assertSameValue(['INGCO', 'SMI'], $filter->sources(), 'All database selects both sources.');

$normalizer = new SalesDashboardRowNormalizer();
$normalized = $normalizer->normalize([
    'LiniPenjualan' => 'Gerai Setiabudi',
    'ItemCode' => 'ABC-01',
    'ItemName' => 'Example Item',
    'LineTotal' => '1.250.000',
    'Discontinue' => 1,
    'ClearenceSale' => '10%',
], 'INGCO');
assertSameValue('GERAI SETIABUDI', $normalized['line'], 'Line is normalized to uppercase.');
assertFloat(1250000.0, $normalized['sales'], 'Indonesian-formatted sales is parsed.');
assertTrueValue($normalized['discontinued'], 'Discontinue flag is true.');
assertTrueValue($normalized['clearance'], 'Clearance percent is true.');
assertSameValue([], $normalizer->missingRequiredColumns([
    'LiniPenjualan' => 'A',
    'LineTotal' => 1,
]), 'Required aliases are recognized.');
assertSameValue(['clearance'], $normalizer->missingColumnsForStatus([
    'LiniPenjualan' => 'A',
    'LineTotal' => 1,
    'Discontinue' => 0,
], 'clearance'), 'Clearance filter requires a clearance column.');

$targets = [
    'JAKARTA' => 250000000.0,
    'GERAI SETIA BUDI' => 135000000.0,
    'LAIN - LAIN' => 900000000.0,
    'LAINNYA' => 950000000.0,
];
$aliases = [
    'GERAI SETIABUDI' => 'GERAI SETIA BUDI',
    'WILLIAM' => 'WILIAM',
];
$targetLookup = new class($targets) implements SalesKpiTargetLookup {
    /** @param array<string,?float> $targets */
    public function __construct(public array $targets) {}

    public function targetForLine(string $line): ?float
    {
        return $this->targets[strtoupper(trim($line))] ?? null;
    }

    public function all(): array
    {
        return $this->targets;
    }
};
$aggregator = new SalesDashboardAggregator($targetLookup, $targets, $aliases);
$periodRows = [
    'dtd' => [
        ['line' => 'JAKARTA', 'sales' => 982000.0, 'discontinued' => false, 'clearance' => false, 'item_code' => 'A', 'item_name' => 'A', 'source' => 'INGCO'],
        ['line' => 'Gerai Setiabudi', 'sales' => 926900.0, 'discontinued' => true, 'clearance' => false, 'item_code' => 'B', 'item_name' => 'B', 'source' => 'INGCO'],
        ['line' => 'Unknown New Channel', 'sales' => 1000.0, 'discontinued' => false, 'clearance' => true, 'item_code' => 'C', 'item_name' => 'C', 'source' => 'SMI'],
    ],
    'ldtd' => [
        ['line' => 'JAKARTA', 'sales' => 23000.0, 'discontinued' => false, 'clearance' => false, 'item_code' => 'A', 'item_name' => 'A', 'source' => 'INGCO'],
    ],
    'mtd' => [
        ['line' => 'JAKARTA', 'sales' => 41615500.0, 'discontinued' => false, 'clearance' => false, 'item_code' => 'A', 'item_name' => 'A', 'source' => 'INGCO'],
        ['line' => 'Gerai Setiabudi', 'sales' => 926900.0, 'discontinued' => true, 'clearance' => false, 'item_code' => 'B', 'item_name' => 'B', 'source' => 'INGCO'],
        ['line' => 'Unknown New Channel', 'sales' => 1000.0, 'discontinued' => false, 'clearance' => true, 'item_code' => 'C', 'item_name' => 'C', 'source' => 'SMI'],
    ],
    'lmtd' => [
        ['line' => 'JAKARTA', 'sales' => 53850599.0, 'discontinued' => false, 'clearance' => false, 'item_code' => 'A', 'item_name' => 'A', 'source' => 'SMI'],
    ],
];
$report = $aggregator->aggregate($filter, $periodRows);
$jakarta = $report->rows[0];
assertSameValue('JAKARTA', $jakarta->line, 'Target order is preserved.');
assertFloat(41615500.0, $jakarta->mtd, 'MTD is aggregated.');
assertFloat(53850599.0, $jakarta->lmtd, 'LMTD is aggregated.');
assertFloat(982000.0, $jakarta->dtd, 'DTD is selected day.');
assertFloat(23000.0, $jakarta->ldtd, 'LDTD is previous day.');
assertFloat(250000000.0 / 27.0, $jakarta->dailyTarget, 'Daily Sales Target / HK uses total sales days.');
assertFloat((250000000.0 - 41615500.0) / 6.0, $jakarta->requiredDailySales, 'Required Daily Sales uses remaining sales days.');
assertFloat(41615500.0 / 21.0, $jakarta->averageDailySales, 'Average uses elapsed day rule.');
assertFloat((41615500.0 / 21.0) * 27.0, $jakarta->estimatedEom, 'EOM estimate follows reference formula.');
assertSameValue('GERAI SETIA BUDI', $report->rows[1]->line, 'Alias maps to canonical line.');
assertSameValue(3, count($report->rows), 'Legacy LAIN - LAIN and LAINNYA collapse into one dashboard row.');
assertSameValue('LAINNYA', $report->rows[2]->line, 'Canonical other line is LAINNYA.');
assertFloat(950000000.0, $report->rows[2]->monthlyTarget, 'Canonical LAINNYA target overrides legacy fallback target.');
assertFloat(1000.0, $report->rows[2]->mtd, 'Unknown lines are placed in LAINNYA.');
assertFloat(42543400.0, $report->totalRow->mtd, 'Total MTD sums all lines.');
assertFloat(41615500.0 / 42543400.0, (float) $jakarta->contributionPercentage, 'Contribution is line MTD divided by total MTD.');
$targetLookup->targets['JAKARTA'] = 500000000.0;
$updatedTargetReport = $aggregator->aggregate($filter, $periodRows);
assertFloat(500000000.0, $updatedTargetReport->rows[0]->monthlyTarget, 'Sales Dashboard reads target changes without rebuilding the service.');
assertFloat(1.0, (float) $report->totalRow->contributionPercentage, 'Total contribution is 100 percent.');
assertSameValue(3, count($report->detailRows), 'MTD raw rows are retained for DataTable and Excel.');
assertSameValue('JAKARTA', $report->detailRows[0]['line'], 'Raw row uses canonical LiniPenjualan.');
assertFloat(41615500.0 / 42543400.0, (float) $report->detailRows[0]['line_contribution'], 'Raw row repeats its line contribution.');

$detailPage = (new SalesDashboardDetailQueryService())->paginate(
    $report->detailRows,
    'JAKARTA',
    'line_total',
    'desc',
    0,
    10,
);
assertSameValue(3, $detailPage->recordsTotal, 'Raw DataTable reports all MTD rows.');
assertSameValue(1, $detailPage->recordsFiltered, 'Raw DataTable search filters rows.');

final class FakeDashboardDataSource implements SalesDataSource
{
    /** @param array<string,list<object>> $rows */
    public array $calls = [];

    public function __construct(private array $rows)
    {
    }

    public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $key = $source.'|'.$startDate->format('Y-m-d').'|'.$endDate->format('Y-m-d');
        $this->calls[] = $key;
        return $this->rows[$key] ?? [];
    }
}

$statusFilter = SalesDashboardFilter::from('all', '2026-07-22', 'discontinued');
$fake = new FakeDashboardDataSource([
    'INGCO|2026-07-01|2026-07-22' => [
        (object) ['LiniPenjualan' => 'JAKARTA', 'LineTotal' => 100, 'Discontinue' => 1, 'ClearenceSale' => 0],
        (object) ['LiniPenjualan' => 'JAKARTA', 'LineTotal' => 900, 'Discontinue' => 0, 'ClearenceSale' => 0],
    ],
    'INGCO|2026-07-22|2026-07-22' => [
        (object) ['LiniPenjualan' => 'JAKARTA', 'LineTotal' => 100, 'Discontinue' => 1, 'ClearenceSale' => 0],
    ],
]);
$service = new SalesDashboardService($fake, $normalizer, $aggregator);
$filteredReport = $service->generate($statusFilter);
assertFloat(100.0, $filteredReport->rows[0]->mtd, 'Service applies discontinued filter before aggregation.');
assertSameValue(8, count($fake->calls), 'All database mode calls four periods for each database.');

print("manual_dashboard: OK\n");
