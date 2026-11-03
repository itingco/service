<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesKpi\SalesKpiDataset;
use App\Data\SalesKpi\SalesKpiTransaction;
use App\Services\SalesKpi\SalesKpiAggregator;
use PHPUnit\Framework\TestCase;

final class SalesKpiAggregatorTest extends TestCase
{
    public function test_same_line_from_both_databases_merges_and_keeps_source_totals(): void
    {
        $dataset = new SalesKpiDataset(
            current: [
                $this->tx('AS_INGCO', 'JAKARTA', 300.0),
                $this->tx('AS_SMI', ' jakarta ', 200.0),
                $this->tx('AS_SMI', 'GERAI KATAMSO', 50.0),
            ],
            growthCurrent: [],
            growthPrevious: [],
        );

        $rows = (new SalesKpiAggregator())->aggregate($dataset);

        self::assertCount(2, $rows);
        self::assertSame(300.0, $rows['JAKARTA']->source('AS_INGCO')->currentOmzet);
        self::assertSame(200.0, $rows['JAKARTA']->source('AS_SMI')->currentOmzet);
        self::assertSame(500.0, $rows['JAKARTA']->currentOmzet());
    }

    public function test_discontinued_and_clearance_are_kept_as_separate_metrics(): void
    {
        $dataset = new SalesKpiDataset(
            current: [$this->tx('AS_INGCO', 'JAKARTA', 500.0, true, true)],
            growthCurrent: [],
            growthPrevious: [],
        );

        $row = (new SalesKpiAggregator())->aggregate($dataset)['JAKARTA'];

        self::assertSame(500.0, $row->discontinuedOmzet());
        self::assertSame(500.0, $row->clearanceOmzet());
    }

    private function tx(
        string $database,
        string $line,
        float $total,
        bool $discontinued = false,
        bool $clearance = false,
    ): SalesKpiTransaction {
        $lineDisplay = trim($line);
        $lineKey = strtoupper(preg_replace('/\s+/', ' ', $lineDisplay) ?? $lineDisplay);

        return new SalesKpiTransaction(
            database: $database,
            line: $lineDisplay,
            lineKey: $lineKey,
            lineTotal: $total,
            discontinued: $discontinued,
            clearance: $clearance,
            grade: '',
            quantity: 1,
            invoiceDate: '2026-07-20',
            invoiceNumber: 'INV-001',
            itemCode: 'ITEM',
            itemName: 'Item',
            itemStatus: $discontinued ? 'DISCONTINUE' : 'ACTIVE',
            clearanceStatus: $clearance ? 'CLEARANCE SALE' : 'BUKAN CLEARANCE SALE',
            clearanceDiscount: $clearance ? '0.2' : '0',
        );
    }
}
