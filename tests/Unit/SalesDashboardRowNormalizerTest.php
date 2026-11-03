<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SalesDashboardRowNormalizer;
use PHPUnit\Framework\TestCase;

final class SalesDashboardRowNormalizerTest extends TestCase
{
    public function test_it_normalizes_raw_function_fields_and_status_flags(): void
    {
        $normalizer = new SalesDashboardRowNormalizer();
        $row = $normalizer->normalize([
            'InvoiceDate' => '2026-07-20 10:11:12',
            'InvoiceNumber' => 'INV-001',
            'LiniPenjualan' => 'Gerai Setiabudi',
            'CustomerCode' => 'C001',
            'CustomerName' => 'Customer A',
            'SalesPersonName' => 'Sales A',
            'ItemCode' => 'ITEM-1',
            'ItemName' => 'Hammer',
            'Grade' => 'a',
            'Quantity' => 2,
            'LineTotal' => '1.250.000',
            'Discontinue' => 1,
            'StatusItem' => 'DISCONTINUE',
            'StatusClearanceSale' => 'CLEARANCE SALE',
            'ClearanceSaleDiscount' => 0.2,
        ], 'INGCO');

        self::assertSame('AS_INGCO', $row['database']);
        self::assertSame('2026-07-20', $row['invoice_date']);
        self::assertSame('GERAI SETIABUDI', $row['line']);
        self::assertSame(1250000.0, $row['sales']);
        self::assertSame('A', $row['grade']);
        self::assertTrue($row['discontinued']);
        self::assertTrue($row['clearance']);
        self::assertSame('CLEARANCE SALE', $row['clearance_status']);
    }

    public function test_it_reports_missing_clearance_column_for_clearance_filter(): void
    {
        $normalizer = new SalesDashboardRowNormalizer();

        self::assertSame(
            ['clearance'],
            $normalizer->missingColumnsForStatus([
                'LiniPenjualan' => 'JAKARTA',
                'LineTotal' => 100,
                'Discontinue' => 0,
            ], 'clearance'),
        );
    }
}
