<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StockAgingDetailQueryService;
use PHPUnit\Framework\TestCase;

final class StockAgingDetailQueryServiceTest extends TestCase
{
    public function test_filter_all_returns_every_matching_row_sorted_without_pagination(): void
    {
        $service = new StockAgingDetailQueryService();

        self::assertTrue(
            method_exists($service, 'filterAll'),
            'StockAgingDetailQueryService must expose filterAll() for Excel export without pagination.',
        );

        $rows = [
            $this->row('INGCO', 7, '0-30', 'B002', 10),
            $this->row('SMI', 7, '0-30', 'SHOULD-NOT-EXPORT', 20),
            $this->row('INGCO', 8, '0-30', 'OTHER-WH', 20),
            $this->row('INGCO', 7, '31-60', 'OTHER-BUCKET', 40),
            $this->row('INGCO', 7, '0-30', 'A001', 5),
            $this->row('INGCO', 7, '0-30', 'A001', 25),
        ];

        /** @var list<array<string,mixed>> $filtered */
        $filtered = $service->filterAll($rows, 'ingco', 7, '0-30');

        self::assertCount(3, $filtered);
        self::assertSame(['A001', 'A001', 'B002'], array_column($filtered, 'item_code'));
        self::assertSame([25, 5, 10], array_column($filtered, 'age'));
    }

    /** @return array<string,mixed> */
    private function row(string $source, int $warehouseId, string $bucket, string $itemCode, int $age): array
    {
        return [
            'source' => $source,
            'warehouse_id' => $warehouseId,
            'bucket_key' => $bucket,
            'item_code' => $itemCode,
            'age' => $age,
        ];
    }
}
