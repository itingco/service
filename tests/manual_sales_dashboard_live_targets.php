<?php

declare(strict_types=1);

require __DIR__.'/../app/Contracts/SalesKpiTargetLookup.php';
require __DIR__.'/../app/Support/SalesLineName.php';
require __DIR__.'/../app/Data/SalesDashboardFilter.php';
require __DIR__.'/../app/Data/SalesDashboardLineRow.php';
require __DIR__.'/../app/Data/SalesDashboardReport.php';
require __DIR__.'/../app/Services/SalesDashboardAggregator.php';

use App\Contracts\SalesKpiTargetLookup;
use App\Data\SalesDashboardFilter;
use App\Services\SalesDashboardAggregator;

final class MutableTargetLookup implements SalesKpiTargetLookup
{
    /** @param array<string,?float> $targets */
    public function __construct(public array $targets)
    {
    }

    public function targetForLine(string $line): ?float
    {
        return $this->targets[strtoupper(trim($line))] ?? null;
    }

    /** @return array<string,?float> */
    public function all(): array
    {
        return $this->targets;
    }
}

function transaction(string $line, float $sales): array
{
    return [
        'line' => $line,
        'sales' => $sales,
        'line_total' => $sales,
        'discontinued' => false,
        'clearance' => false,
        'item_code' => 'ITEM-1',
        'item_name' => 'Item',
        'source' => 'INGCO',
        'database' => 'AS_INGCO',
    ];
}

$lookup = new MutableTargetLookup(['JAKARTA' => 100_000_000.0]);
$aggregator = new SalesDashboardAggregator($lookup);
$filter = SalesDashboardFilter::from('all', '2026-07-22', 'all');
$periodRows = [
    'dtd' => [transaction('JAKARTA', 1_000_000)],
    'ldtd' => [],
    'mtd' => [transaction('JAKARTA', 10_000_000)],
    'lmtd' => [],
];

$first = $aggregator->aggregate($filter, $periodRows);
if ($first->rows[0]->monthlyTarget !== 100_000_000.0) {
    throw new RuntimeException('Sales Dashboard belum membaca target dari target lookup.');
}

$lookup->targets['JAKARTA'] = 250_000_000.0;
$second = $aggregator->aggregate($filter, $periodRows);
if ($second->rows[0]->monthlyTarget !== 250_000_000.0) {
    throw new RuntimeException('Perubahan target belum langsung terbaca oleh Sales Dashboard.');
}

fwrite(STDOUT, "PASS: Sales Dashboard memakai target terbaru dari target lookup.\n");
