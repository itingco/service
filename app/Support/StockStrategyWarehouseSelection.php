<?php

declare(strict_types=1);

namespace App\Support;

final class StockStrategyWarehouseSelection
{
    /**
     * Normalize GET input into canonical SOURCE|WarehouseID keys.
     * Supports both an array query and the legacy single-string query.
     *
     * @return list<string>
     */
    public static function normalize(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $result = [];

        foreach ($values as $key) {
            if (!is_scalar($key)) {
                continue;
            }

            $key = strtoupper(trim((string) $key));
            if (!preg_match('/^(INGCO|WADFOW|SMI)\|([1-9][0-9]*)$/', $key)) {
                continue;
            }

            $result[$key] = true;
        }

        return array_keys($result);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $warehouseKeys
     * @return list<array<string,mixed>>
     */
    public static function filterRows(array $rows, array $warehouseKeys): array
    {
        if ($warehouseKeys === []) {
            return array_values($rows);
        }

        $selected = array_fill_keys($warehouseKeys, true);

        return array_values(array_filter($rows, static function (array $row) use ($selected): bool {
            $source = strtoupper(trim((string) ($row['source'] ?? '')));
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);

            if ($source === '' || $warehouseId <= 0) {
                return false;
            }

            return isset($selected[$source.'|'.$warehouseId]);
        }));
    }

    /**
     * Build source-aware warehouse options from StockAgingReport::sources.
     *
     * @param array<string,array<string,mixed>> $sources
     * @return array<string,string>
     */
    public static function optionsFromSources(array $sources): array
    {
        $options = [];

        foreach ($sources as $sourceKey => $sourceData) {
            $source = strtoupper(trim((string) $sourceKey));
            if ($source === '') {
                continue;
            }

            $database = trim((string) ($sourceData['database'] ?? $source));

            foreach ((array) ($sourceData['warehouses'] ?? []) as $warehouse) {
                if (!is_array($warehouse)) {
                    continue;
                }

                $warehouseId = (int) ($warehouse['warehouse_id'] ?? 0);
                if ($warehouseId <= 0) {
                    continue;
                }

                $name = trim((string) ($warehouse['warehouse_name'] ?? ''));
                $code = trim((string) ($warehouse['warehouse_code'] ?? ''));
                $display = $name !== '' ? $name : ($code !== '' ? $code : 'Warehouse '.$warehouseId);

                $options[$source.'|'.$warehouseId] = $database.' · '.$display;
            }
        }

        uasort($options, static fn (string $left, string $right): int => strnatcasecmp($left, $right));

        return $options;
    }
}
