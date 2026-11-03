<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Data/SalesKpi/SalesKpiFilter.php';
require_once __DIR__.'/../app/Data/SalesKpi/SalesKpiPeriod.php';
require_once __DIR__.'/../app/Data/SalesKpi/SalesKpiTransaction.php';
require_once __DIR__.'/../app/Data/SalesKpi/SalesKpiDataset.php';
require_once __DIR__.'/../app/Data/SalesKpi/SalesKpiSourceContribution.php';
require_once __DIR__.'/../app/Data/SalesKpi/SalesKpiLineAggregate.php';
require_once __DIR__.'/../app/Data/SalesKpi/SalesLineKpi.php';
require_once __DIR__.'/../app/Data/SalesKpi/SalesKpiReport.php';
require_once __DIR__.'/../app/Support/SalesLineName.php';
require_once __DIR__.'/../app/Support/SalesKpiRowNormalizer.php';
require_once __DIR__.'/../app/Services/SalesKpi/SalesKpiPeriodService.php';
require_once __DIR__.'/../app/Contracts/SalesKpiTargetLookup.php';
require_once __DIR__.'/../app/Services/SalesKpi/SalesKpiAggregator.php';
require_once __DIR__.'/../app/Services/SalesKpi/SalesKpiCalculator.php';

use App\Data\SalesKpi\SalesKpiDataset;
use App\Data\SalesKpi\SalesKpiFilter;
use App\Data\SalesKpi\SalesKpiTransaction;
use App\Services\SalesKpi\SalesKpiAggregator;
use App\Services\SalesKpi\SalesKpiCalculator;
use App\Services\SalesKpi\SalesKpiPeriodService;
use App\Contracts\SalesKpiTargetLookup;
use App\Support\SalesKpiRowNormalizer;

function kpiAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function kpiFloat(float $expected, ?float $actual, string $message, float $delta = 0.0001): void
{
    if ($actual === null || abs($expected - $actual) > $delta) {
        throw new RuntimeException($message.' Expected '.$expected.', got '.var_export($actual, true));
    }
}

function kpiTx(
    string $database,
    string $line,
    float $total,
    string $grade = '',
    bool $discontinued = false,
    bool $clearance = false,
    string $itemCode = 'ITEM',
): SalesKpiTransaction {
    $line = trim($line);
    $lineKey = strtoupper(preg_replace('/\s+/', ' ', $line) ?? $line);

    return new SalesKpiTransaction(
        database: $database,
        line: $line,
        lineKey: $lineKey,
        lineTotal: $total,
        discontinued: $discontinued,
        clearance: $clearance,
        grade: $grade,
        quantity: 1,
        invoiceDate: '2026-07-20',
        invoiceNumber: 'INV-001',
        itemCode: $itemCode,
        itemName: 'Item '.$itemCode,
        itemStatus: $discontinued ? 'DISCONTINUE' : 'ACTIVE',
        clearanceStatus: $clearance ? 'CLEARANCE SALE' : 'BUKAN CLEARANCE SALE',
        clearanceDiscount: $clearance ? '0.2' : '0',
    );
}

$periodService = new SalesKpiPeriodService();
$march = $periodService->make(new DateTimeImmutable('2026-03-31'));
kpiAssert($march->currentEnd->format('Y-m-d') === '2026-03-31', 'Full current period must retain March 31.');
kpiAssert($march->growthCurrentEnd->format('Y-m-d') === '2026-03-28', 'Growth current period must be truncated to February length.');
kpiAssert($march->growthPreviousEnd->format('Y-m-d') === '2026-02-28', 'Growth previous period must end February 28.');

$normalizer = new SalesKpiRowNormalizer();
$normalized = $normalizer->normalize((object) [
    'LiniPenjualan' => ' Gerai Katamso ',
    'LineTotal' => '1.250.000',
    'Discontinue' => '1',
    'StatusClearanceSale' => ' clearance sale ',
    'Grade' => ' a ',
    'Quantity' => '2',
], 'AS_INGCO');
kpiAssert($normalized->database === 'AS_INGCO', 'Database source must be attached.');
kpiAssert($normalized->lineKey === 'GERAI KATAMSO', 'LiniPenjualan key must be normalized.');
kpiFloat(1_250_000.0, $normalized->lineTotal, 'Indonesian amount must be parsed.');
kpiAssert($normalized->discontinued && $normalized->clearance, 'Double-status row must preserve both flags.');

$filter = SalesKpiFilter::from('2026-07-20');
$period = $periodService->make($filter->selectedDate);
$dataset = new SalesKpiDataset(
    current: [
        kpiTx('AS_INGCO', 'JAKARTA', 300, 'A', true, true, 'ING-1'),
        kpiTx('AS_SMI', ' jakarta ', 200, 'B', false, false, 'SMI-1'),
        kpiTx('AS_INGCO', 'GERAI KATAMSO', 175, 'A'),
        kpiTx('AS_SMI', 'NEW LINE', 100, 'B'),
    ],
    growthCurrent: [
        kpiTx('AS_INGCO', 'JAKARTA', 300),
        kpiTx('AS_SMI', 'JAKARTA', 200),
        kpiTx('AS_INGCO', 'GERAI KATAMSO', 90),
    ],
    growthPrevious: [
        kpiTx('AS_INGCO', 'JAKARTA', 250),
        kpiTx('AS_SMI', 'JAKARTA', 150),
        kpiTx('AS_INGCO', 'GERAI KATAMSO', 100),
    ],
);
$aggregates = (new SalesKpiAggregator())->aggregate($dataset);
kpiAssert(count($aggregates) === 3, 'Three normalized sales lines must be present.');
kpiFloat(500.0, $aggregates['JAKARTA']->currentOmzet(), 'Same line from two databases must merge.');
kpiFloat(300.0, $aggregates['JAKARTA']->source('AS_INGCO')->currentOmzet, 'INGCO source total must remain visible.');
kpiFloat(200.0, $aggregates['JAKARTA']->source('AS_SMI')->currentOmzet, 'SMI source total must remain visible.');

$calculator = new SalesKpiCalculator(new class implements SalesKpiTargetLookup {
    public function targetForLine(string $line): ?float
    {
        return match (strtoupper(trim($line))) {
            'JAKARTA' => 1000.0,
            'GERAI KATAMSO' => 500.0,
            default => null,
        };
    }

    public function all(): array
    {
        return [
            'JAKARTA' => 1000.0,
            'GERAI KATAMSO' => 500.0,
        ];
    }
});
$report = $calculator->calculate($filter, $period, $aggregates, $dataset->current);

$jakarta = $report->line('JAKARTA');
kpiAssert($jakarta !== null, 'JAKARTA must exist.');
kpiFloat(1000.0, $jakarta->target, 'One combined target must be used for the merged line.');
kpiFloat(0.25, $jakarta->growthRate, 'Combined growth must be calculated after summing sources.');
kpiFloat(10.0, $jakarta->growthScore, '25% combined growth must cap at 10 points.');
kpiFloat(600.0, $jakarta->riskRealization, 'Discontinued and clearance must be counted independently.');

$katamso = $report->line('GERAI KATAMSO');
kpiAssert($katamso !== null, 'GERAI KATAMSO must exist.');
kpiAssert($katamso->hasTarget(), 'A configured combined target must be available regardless of source contribution.');
kpiFloat(500.0, $katamso->target, 'The combined target must not be split by database.');

$newLine = $report->line('NEW LINE');
kpiAssert($newLine !== null && $newLine->target === null, 'Unconfigured line must remain visible without a target.');
kpiAssert($report->summary['overall'] !== null, 'Target-weighted overall score must be available.');

$filteredReport = $calculator->calculate(
    SalesKpiFilter::from('2026-07-20', ' jakarta ', 'yes', 'yes', 'A', 'ING-1'),
    $period,
    $aggregates,
    $dataset->current,
);
kpiAssert(count($filteredReport->rows) === 1, 'Selecting one LiniPenjualan must narrow KPI rows to that line.');
kpiAssert($filteredReport->rows[0]->lineKey === 'JAKARTA', 'Selected KPI row must be JAKARTA.');
kpiAssert($filteredReport->line('GERAI KATAMSO') === null, 'Other lines must not remain in KPI output.');
kpiAssert(array_keys($filteredReport->availableLines) === ['GERAI KATAMSO', 'JAKARTA', 'NEW LINE'], 'Line selector options must remain available after filtering.');
kpiAssert($filteredReport->summary !== $report->summary, 'Line filter must recalculate summary for the selected line.');
kpiAssert(count($filteredReport->detailRows) === 1, 'Detail filters must narrow detail rows inside the selected line.');
kpiAssert($filteredReport->detailRows[0]->database === 'AS_INGCO', 'Filtered row must retain database source.');

$detailOnlyReport = $calculator->calculate(
    SalesKpiFilter::from('2026-07-20', null, 'yes', 'yes', 'A', 'ING-1'),
    $period,
    $aggregates,
    $dataset->current,
);
kpiAssert($detailOnlyReport->summary === $report->summary, 'Non-line detail filters must not alter KPI summary scores.');

print("manual_sales_kpi: OK\n");
