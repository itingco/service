<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\StockAgingFilter;
use App\Data\StockAgingReport;

final class StockAgingAggregator
{
    private const BUCKET_ORDER = ['0-30', '31-60', '61-90', '91-120', 'over-120'];

    /** @param list<array<string,mixed>> $rows */
    public function aggregate(StockAgingFilter $filter, array $rows): StockAgingReport
    {
        $sources = [];
        $detailRows = [];
        $grand = $this->emptyTotal();
        $grandItems = [];
        $grandWarehouses = [];
        $over120Stock = 0.0;

        foreach ($rows as $row) {
            if (!$filter->matchesDatabase((string) ($row['source'] ?? ''))) {
                continue;
            }
            if (!$filter->matchesItemStatus(
                (bool) ($row['discontinued'] ?? false),
                (bool) ($row['has_discount_rule'] ?? false),
                (bool) ($row['item_reject'] ?? $row['clearance'] ?? false),
            )) {
                continue;
            }

            $source = (string) $row['source'];
            $warehouseId = (int) $row['warehouse_id'];
            $warehouseKey = (string) $warehouseId;
            $bucketKey = (string) $row['bucket_key'];
            $itemIdentity = $source.'|'.$warehouseId.'|'.(int) $row['item_id'];
            $warehouseIdentity = $source.'|'.$warehouseId;

            if (!isset($sources[$source])) {
                $sources[$source] = [
                    'database' => (string) ($row['database'] ?? $source),
                    'warehouses' => [],
                    'subtotal' => $this->emptyTotal(),
                    '_items' => [],
                ];
            }

            if (!isset($sources[$source]['warehouses'][$warehouseKey])) {
                $sources[$source]['warehouses'][$warehouseKey] = [
                    'warehouse_id' => $warehouseId,
                    'warehouse_code' => (string) $row['warehouse_code'],
                    'warehouse_name' => (string) $row['warehouse_name'],
                    'buckets' => [],
                    'subtotal' => $this->emptyTotal(),
                    '_items' => [],
                ];
            }

            $warehouse =& $sources[$source]['warehouses'][$warehouseKey];
            if (!isset($warehouse['buckets'][$bucketKey])) {
                $warehouse['buckets'][$bucketKey] = [
                    'key' => $bucketKey,
                    'label' => (string) $row['bucket_label'],
                    ...$this->emptyTotal(),
                    '_items' => [],
                ];
            }

            $bucket =& $warehouse['buckets'][$bucketKey];
            $this->addToTotal($bucket, $row, $itemIdentity);
            $this->addToTotal($warehouse['subtotal'], $row, $itemIdentity);
            $this->addToTotal($sources[$source]['subtotal'], $row, $itemIdentity);
            $this->addToTotal($grand, $row, $itemIdentity);

            $bucket['_items'][$itemIdentity] = true;
            $warehouse['_items'][$itemIdentity] = true;
            $sources[$source]['_items'][$itemIdentity] = true;
            $grandItems[$itemIdentity] = true;
            $grandWarehouses[$warehouseIdentity] = true;

            if ($bucketKey === 'over-120') {
                $over120Stock += (float) $row['sisa_stock'];
            }

            $detailRows[] = $row;
            unset($bucket, $warehouse);
        }

        foreach ($sources as &$source) {
            foreach ($source['warehouses'] as &$warehouse) {
                $warehouse['buckets'] = $this->sortBuckets($warehouse['buckets']);
                foreach ($warehouse['buckets'] as &$bucket) {
                    $this->finalizeTotal($bucket);
                }
                unset($bucket);
                $this->finalizeTotal($warehouse['subtotal']);
                unset($warehouse['_items']);
            }
            unset($warehouse);
            $this->finalizeTotal($source['subtotal']);
            unset($source['_items']);
        }
        unset($source);

        ksort($sources, SORT_NATURAL | SORT_FLAG_CASE);
        $this->finalizeTotal($grand);

        return new StockAgingReport(
            filter: $filter,
            sources: $sources,
            detailRows: $detailRows,
            grandTotal: $grand,
            over120Stock: $over120Stock,
            totalItems: count($grandItems),
            totalWarehouses: count($grandWarehouses),
        );
    }

    /** @return array{item_count:int,qty:float,sisa_stock:float,sales_mtd:float,ratio:?float,_items:array<string,bool>} */
    private function emptyTotal(): array
    {
        return [
            'item_count' => 0,
            'qty' => 0.0,
            'sisa_stock' => 0.0,
            'sales_mtd' => 0.0,
            'ratio' => null,
            '_items' => [],
        ];
    }

    /** @param array<string,mixed> $total @param array<string,mixed> $row */
    private function addToTotal(array &$total, array $row, string $itemIdentity): void
    {
        $total['qty'] = (float) $total['qty'] + (float) $row['qty'];
        $total['sisa_stock'] = (float) $total['sisa_stock'] + (float) $row['sisa_stock'];
        $total['sales_mtd'] = (float) $total['sales_mtd'] + (float) ($row['sales_mtd'] ?? 0.0);
        $total['_items'][$itemIdentity] = true;
    }

    /** @param array<string,mixed> $total */
    private function finalizeTotal(array &$total): void
    {
        $total['item_count'] = count((array) ($total['_items'] ?? []));
        $sisa = (float) $total['sisa_stock'];
        $total['ratio'] = $sisa > 0.0 ? ((float) $total['sales_mtd'] / $sisa) * 100 : null;
        unset($total['_items']);
    }

    /** @param array<string,array<string,mixed>> $buckets @return array<string,array<string,mixed>> */
    private function sortBuckets(array $buckets): array
    {
        $sorted = [];
        foreach (self::BUCKET_ORDER as $key) {
            if (isset($buckets[$key])) {
                $sorted[$key] = $buckets[$key];
            }
        }

        return $sorted;
    }
}
