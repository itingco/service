<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__.'/../app/'.str_replace('\\', '/', $relative).'.php';
    if (is_file($path)) {
        require $path;
    }
});

use App\Data\SalesByItemFilter;
use App\Services\SalesByItemAggregator;
use App\Support\SalesRowNormalizer;

$tests = [];

function test(string $name, callable $callback): void
{
    global $tests;
    $tests[$name] = $callback;
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message !== '' ? $message.' — ' : '').
            'Expected '.var_export($expected, true).', got '.var_export($actual, true)
        );
    }
}

function assertFloat(float $expected, float $actual, float $delta = 0.0001): void
{
    if (abs($expected - $actual) > $delta) {
        throw new RuntimeException("Expected {$expected}, got {$actual}");
    }
}

test('filter derives first day of selected month', function (): void {
    $filter = SalesByItemFilter::from('all', '2026-07-22');

    assertSameValue('all', $filter->database);
    assertSameValue('2026-07-01', $filter->startDate->format('Y-m-d'));
    assertSameValue('2026-07-22', $filter->endDate->format('Y-m-d'));
    assertSameValue(['INGCO', 'SMI'], $filter->sources());
});

test('normalizer accepts Indonesian aliases and decimal formats', function (): void {
    $normalizer = new SalesRowNormalizer();
    $row = (object) [
        'KodeBarang' => ' itm-001 ',
        'NamaBarang' => 'Mesin Bor',
        'Qty' => '1.234,50',
        'TotalSales' => '12.345.678,90',
    ];

    $result = $normalizer->normalize($row, 'INGCO');

    assertSameValue('ITM-001', $result['item_code']);
    assertSameValue('Mesin Bor', $result['item_name']);
    assertFloat(1234.50, $result['quantity']);
    assertFloat(12345678.90, $result['sales']);
    assertSameValue('INGCO', $result['source']);
});

test('normalizer reports missing required columns', function (): void {
    $missing = (new SalesRowNormalizer())->missingRequiredColumns((object) ['Unknown' => 1]);
    assertSameValue(['item_code', 'quantity', 'sales'], $missing);
});

test('aggregator combines matching item codes and calculates contribution', function (): void {
    $filter = SalesByItemFilter::from('all', '2026-07-22');
    $aggregator = new SalesByItemAggregator();

    $report = $aggregator->aggregate($filter, [
        ['item_code' => 'ITEM001', 'item_name' => 'Bor', 'quantity' => 2.0, 'sales' => 100000.0, 'source' => 'INGCO'],
        ['item_code' => 'ITEM001', 'item_name' => 'Bor', 'quantity' => 1.0, 'sales' => 50000.0, 'source' => 'SMI'],
        ['item_code' => 'ITEM002', 'item_name' => 'Gerinda', 'quantity' => 5.0, 'sales' => 50000.0, 'source' => 'INGCO'],
    ]);

    assertFloat(200000.0, $report->totalSales);
    assertFloat(8.0, $report->totalQuantity);
    assertSameValue(2, $report->totalItems);
    assertSameValue('ITEM001', $report->rows[0]->itemCode);
    assertFloat(150000.0, $report->rows[0]->totalSales);
    assertFloat(50000.0, $report->rows[0]->averagePrice);
    assertFloat(75.0, $report->rows[0]->contribution);
    assertSameValue('INGCO & SMI', $report->rows[0]->source);
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "PASS: {$name}\n");
    } catch (Throwable $e) {
        $failures++;
        fwrite(STDERR, "FAIL: {$name}\n  {$e->getMessage()}\n");
    }
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} test(s) failed.\n");
    exit(1);
}

fwrite(STDOUT, "\nAll ".count($tests)." tests passed.\n");
