<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SalesKpiRowNormalizer;
use PHPUnit\Framework\TestCase;

final class SalesKpiRowNormalizerTest extends TestCase
{
    public function test_it_normalizes_the_verified_function_columns(): void
    {
        $row = (new SalesKpiRowNormalizer())->normalize((object) [
            'InvoiceDate' => '2026-07-20',
            'InvoiceNumber' => 'INV-001',
            'LiniPenjualan' => ' Gerai Katamso ',
            'ItemCode' => 'ABC01',
            'ItemName' => 'Hammer',
            'Quantity' => '2',
            'LineTotal' => '1.250.000',
            'Discontinue' => '1',
            'StatusItem' => 'DISCONTINUE',
            'Grade' => ' a ',
            'StatusClearanceSale' => ' clearance sale ',
            'ClearanceSaleDiscount' => '0.2',
        ], 'AS_INGCO');

        self::assertSame('GERAI KATAMSO', $row->lineKey);
        self::assertSame('Gerai Katamso', $row->line);
        self::assertSame(1_250_000.0, $row->lineTotal);
        self::assertTrue($row->discontinued);
        self::assertTrue($row->clearance);
        self::assertSame('A', $row->grade);
        self::assertSame(2.0, $row->quantity);
    }

    public function test_it_attaches_the_normalized_database_source(): void
    {
        $transaction = (new SalesKpiRowNormalizer())->normalize((object) [
            'LiniPenjualan' => 'JAKARTA',
            'LineTotal' => 100,
            'Discontinue' => 0,
            'Grade' => 'A',
            'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
        ], 'AS_INGCO');

        self::assertSame('AS_INGCO', $transaction->database);
    }

    public function test_clearance_discount_alone_does_not_mark_clearance(): void
    {
        $row = (new SalesKpiRowNormalizer())->normalize((object) [
            'LiniPenjualan' => 'JAKARTA',
            'LineTotal' => 100,
            'Discontinue' => 0,
            'Grade' => 'B',
            'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
            'ClearanceSaleDiscount' => 0.5,
        ], 'AS_SMI');

        self::assertFalse($row->clearance);
    }

    public function test_it_reports_each_missing_scoring_column_by_real_name(): void
    {
        $missing = (new SalesKpiRowNormalizer())->missingRequiredColumns((object) [
            'LiniPenjualan' => 'JAKARTA',
            'LineTotal' => 100,
        ]);

        self::assertSame(['Discontinue', 'StatusClearanceSale', 'Grade'], $missing);
    }
}
