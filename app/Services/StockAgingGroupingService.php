<?php

declare(strict_types=1);

namespace App\Services;

final class StockAgingGroupingService
{
    private const FIELDS = [
        'database' => 'Database',
        'warehouse' => 'Gudang',
        'item' => 'Item',
        'age' => 'Umur Stok',
        'status' => 'Status Item',
        'focus' => 'Product Focus',
        'brand' => 'Brand',
    ];

    /** @return array<string,string> */
    public function fieldOptions(): array
    {
        return self::FIELDS;
    }

    /** @return list<string> */
    public function normalizeFields(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $result = [];

        foreach ($values as $field) {
            if (!is_scalar($field)) {
                continue;
            }
            $field = strtolower(trim((string) $field));
            if (isset(self::FIELDS[$field])) {
                $result[$field] = true;
            }
        }

        return array_keys($result);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $fields
     * @return list<array<string,mixed>>
     */
    public function group(array $rows, array $fields): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $keys = [];
            $labels = [];
            foreach ($fields as $field) {
                [$key, $label] = $this->valueForField($row, $field);
                $keys[] = $field.'='.$key;
                $labels[$field] = $label;
            }

            $groupKey = implode('|', $keys);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'values' => $labels,
                    'item_count' => 0,
                    'row_count' => 0,
                    'qty' => 0.0,
                    'sisa_stock' => 0.0,
                    'sales_mtd' => 0.0,
                    'ratio' => null,
                    '_items' => [],
                ];
            }

            $identity = (string) ($row['source'] ?? '').'|'.(int) ($row['item_id'] ?? 0);
            $groups[$groupKey]['_items'][$identity] = true;
            $groups[$groupKey]['row_count']++;
            $groups[$groupKey]['qty'] += (float) ($row['qty'] ?? 0);
            $groups[$groupKey]['sisa_stock'] += (float) ($row['sisa_stock'] ?? 0);
            $groups[$groupKey]['sales_mtd'] += (float) ($row['sales_mtd'] ?? 0);
        }

        foreach ($groups as &$group) {
            $group['item_count'] = count($group['_items']);
            $group['ratio'] = $group['sisa_stock'] > 0
                ? ($group['sales_mtd'] / $group['sisa_stock']) * 100
                : null;
            unset($group['_items']);
        }
        unset($group);

        uasort($groups, static function (array $left, array $right) use ($fields): int {
            foreach ($fields as $field) {
                $compare = strnatcasecmp((string) ($left['values'][$field] ?? ''), (string) ($right['values'][$field] ?? ''));
                if ($compare !== 0) {
                    return $compare;
                }
            }

            return 0;
        });

        return array_values($groups);
    }

    /** @param array<string,mixed> $row @return array{0:string,1:string} */
    private function valueForField(array $row, string $field): array
    {
        return match ($field) {
            'database' => [
                strtoupper((string) ($row['source'] ?? '')),
                (string) ($row['database'] ?? $row['source'] ?? '-'),
            ],
            'warehouse' => [
                strtoupper((string) ($row['source'] ?? '')).'|'.(int) ($row['warehouse_id'] ?? 0),
                $this->warehouseLabel($row),
            ],
            'item' => [
                strtoupper((string) ($row['source'] ?? '')).'|'.(int) ($row['item_id'] ?? 0),
                trim((string) ($row['item_code'] ?? '')).' · '.trim((string) ($row['item_name'] ?? '')),
            ],
            'age' => [
                (string) ($row['bucket_key'] ?? ''),
                (string) ($row['bucket_label'] ?? '-'),
            ],
            'status' => [
                (string) ($row['status_key'] ?? ''),
                (string) ($row['status_label'] ?? '-'),
            ],
            'focus' => [
                strtoupper((string) ($row['product_focus'] ?? 'NON FOCUS')),
                (string) ($row['product_focus'] ?? 'NON FOCUS'),
            ],
            'brand' => [
                strtoupper((string) ($row['brand'] ?? '')),
                trim((string) ($row['brand'] ?? '')) !== '' ? (string) $row['brand'] : '-',
            ],
            default => ['-', '-'],
        };
    }

    /** @param array<string,mixed> $row */
    private function warehouseLabel(array $row): string
    {
        $name = trim((string) ($row['warehouse_name'] ?? ''));
        $code = trim((string) ($row['warehouse_code'] ?? ''));
        $label = $name !== '' ? $name : ($code !== '' ? $code : 'Warehouse '.(int) ($row['warehouse_id'] ?? 0));

        if ($code !== '' && strcasecmp($code, $label) !== 0) {
            $label .= ' · '.$code;
        }

        return $label;
    }
}
