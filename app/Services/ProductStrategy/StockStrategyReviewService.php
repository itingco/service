<?php

declare(strict_types=1);

namespace App\Services\ProductStrategy;

final class StockStrategyReviewService
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array{focus_category:string,focus_rank:int}> $focusMap
     * @return array{warehouses:list<array<string,mixed>>,totals:array<string,float|int>}
     */
    public function build(array $rows, array $focusMap): array
    {
        $enriched = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['item_code'] ?? '')));
            $focus = $focusMap[$code] ?? null;
            $row['product_focus'] = $focus['focus_category'] ?? 'NON FOCUS';
            $row['focus_rank'] = $focus['focus_rank'] ?? 999999;
            $enriched[] = $row;
        }

        usort($enriched, static function (array $a, array $b): int {
            return [(string) ($a['source'] ?? ''), (int) ($a['warehouse_id'] ?? 0), (int) ($a['focus_rank'] ?? 999999), -(int) ($a['age'] ?? 0), -(float) ($a['sisa_stock'] ?? 0), (string) ($a['item_code'] ?? '')]
                <=> [(string) ($b['source'] ?? ''), (int) ($b['warehouse_id'] ?? 0), (int) ($b['focus_rank'] ?? 999999), -(int) ($b['age'] ?? 0), -(float) ($b['sisa_stock'] ?? 0), (string) ($b['item_code'] ?? '')];
        });

        $warehouses = [];
        foreach ($enriched as $row) {
            $key = strtoupper((string) ($row['source'] ?? '')).'|'.(int) ($row['warehouse_id'] ?? 0);
            if (!isset($warehouses[$key])) {
                $warehouses[$key] = [
                    'source' => strtoupper((string) ($row['source'] ?? '')),
                    'database' => (string) ($row['database'] ?? ''),
                    'warehouse_id' => (int) ($row['warehouse_id'] ?? 0),
                    'warehouse_code' => (string) ($row['warehouse_code'] ?? ''),
                    'warehouse_name' => (string) ($row['warehouse_name'] ?? ''),
                    'items' => [],
                    'sisa_stock' => 0.0,
                    'qty' => 0.0,
                ];
            }
            $warehouses[$key]['items'][] = $row;
            $warehouses[$key]['sisa_stock'] += (float) ($row['sisa_stock'] ?? 0);
            $warehouses[$key]['qty'] += (float) ($row['qty'] ?? 0);
        }

        return [
            'warehouses' => array_values($warehouses),
            'totals' => [
                'item_rows' => count($enriched),
                'warehouses' => count($warehouses),
                'sisa_stock' => array_sum(array_map(static fn (array $row): float => (float) ($row['sisa_stock'] ?? 0), $enriched)),
                'qty' => array_sum(array_map(static fn (array $row): float => (float) ($row['qty'] ?? 0), $enriched)),
            ],
        ];
    }
}
