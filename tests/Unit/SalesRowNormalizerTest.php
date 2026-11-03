<?php

namespace Tests\Unit;

use App\Support\SalesRowNormalizer;
use PHPUnit\Framework\TestCase;

class SalesRowNormalizerTest extends TestCase
{
    public function test_it_accepts_common_function_aliases(): void
    {
        $result = (new SalesRowNormalizer())->normalize((object) [
            'KodeBarang' => ' abc-01 ',
            'NamaBarang' => 'Mesin Bor',
            'Qty' => '1.234,50',
            'TotalSales' => '12.345.678,90',
        ], 'INGCO');

        self::assertSame('ABC-01', $result['item_code']);
        self::assertSame('Mesin Bor', $result['item_name']);
        self::assertSame(1234.5, $result['quantity']);
        self::assertSame(12345678.9, $result['sales']);
    }

    public function test_it_skips_rows_without_item_code(): void
    {
        self::assertNull((new SalesRowNormalizer())->normalize((object) [
            'ItemName' => 'Tanpa kode',
            'Quantity' => 1,
            'LineTotal' => 100,
        ], 'SMI'));
    }
}
