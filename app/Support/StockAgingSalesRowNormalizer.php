<?php

declare(strict_types=1);

namespace App\Support;

use stdClass;

final class StockAgingSalesRowNormalizer
{
    /** @return list<string> */
    public function missingRequiredColumns(array|stdClass $row): array
    {
        $values = $this->canonicalKeys((array) $row);
        $missing = [];
        foreach (['itemid' => 'ItemID', 'warehouseid' => 'WarehouseID', 'linetotal' => 'LineTotal'] as $key => $label) {
            if (!array_key_exists($key, $values)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /** @return array{item_id:int,warehouse_id:int,sales:float} */
    public function normalize(array|stdClass $row): array
    {
        $values = $this->canonicalKeys((array) $row);

        return [
            'item_id' => (int) round($this->toFloat($values['itemid'] ?? 0)),
            'warehouse_id' => (int) round($this->toFloat($values['warehouseid'] ?? 0)),
            'sales' => $this->toFloat($values['linetotal'] ?? 0),
        ];
    }

    /** @return array<string,mixed> */
    private function canonicalKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $canonical = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $key))) ?? '';
            $normalized[$canonical] = $value;
        }

        return $normalized;
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = preg_replace('/[^0-9,\.\-+]/', '', trim((string) $value)) ?? '';
        if ($text === '' || $text === '-' || $text === '+') {
            return 0.0;
        }
        if (str_contains($text, ',') && str_contains($text, '.')) {
            if (strrpos($text, ',') > strrpos($text, '.')) {
                $text = str_replace('.', '', $text);
                $text = str_replace(',', '.', $text);
            } else {
                $text = str_replace(',', '', $text);
            }
        } elseif (str_contains($text, ',')) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : 0.0;
    }
}
