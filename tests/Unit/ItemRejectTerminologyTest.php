<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesDashboardFilter;
use App\Data\StockAgingFilter;
use App\Support\SalesDashboardRowNormalizer;
use PHPUnit\Framework\TestCase;

final class ItemRejectTerminologyTest extends TestCase
{
    public function test_clearance_legacy_key_is_presented_as_item_reject(): void
    {
        $stockFilter = StockAgingFilter::from('2026-09-05', 'clearance', 'all');
        $dashboardFilter = SalesDashboardFilter::from('all', '2026-09-05', 'clearance');
        $combinedFilter = StockAgingFilter::from('2026-09-05', 'both', 'all');

        self::assertSame('Item Reject', $stockFilter->itemStatusLabel());
        self::assertSame('ITEM REJECT', $dashboardFilter->itemStatusLabel());
        self::assertSame('Discontinued + Item Reject', $combinedFilter->itemStatusLabel());
    }

    public function test_normalizer_accepts_new_item_reject_status_text(): void
    {
        $normalizer = new SalesDashboardRowNormalizer();
        $row = [
            'InvoiceDate' => '2026-09-05',
            'InvoiceNumber' => 'TEST-001',
            'LiniPenjualan' => 'TEST',
            'ItemCode' => 'ITEM-1',
            'ItemName' => 'Item Test',
            'LineTotal' => 100000,
            'StatusClearanceSale' => 'ITEM REJECT',
        ];

        $normalized = $normalizer->normalize($row, 'INGCO');

        self::assertTrue($normalized['clearance']);
    }
}
