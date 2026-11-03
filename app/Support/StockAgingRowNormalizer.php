<?php

declare(strict_types=1);

namespace App\Support;

use stdClass;

final class StockAgingRowNormalizer
{
    private const ITEM_ID = ['itemid'];
    private const ITEM_CODE = ['itemcode'];
    private const ITEM_NAME = ['itemname'];
    private const WAREHOUSE_ID = ['warehouseid'];
    private const WAREHOUSE_CODE = ['warehousecode'];
    private const WAREHOUSE_NAME = ['warehousename', 'warehouse'];
    private const AGE = ['age'];
    private const QTY = ['smallestonhand', 'smallestqty', 'smallestquantity'];
    private const PRICE = ['pricekhusus1', 'khusus1'];
    private const SISA = ['sisastok', 'stockvalue'];
    private const DISCONTINUE = ['discontinue', 'discontinued', 'statusitem'];
    private const BRAND = ['brand', 'brandname', 'itembrand'];
    private const HAS_DISCOUNT_RULE = ['hasdiscountrule', 'discount_rule', 'discountsale', 'hasdiscountsale'];

    /** @return list<string> */
    public function missingRequiredColumns(array|stdClass $row): array
    {
        $values = $this->canonicalKeys((array) $row);
        $required = [
            'ItemID' => self::ITEM_ID,
            'WarehouseID' => self::WAREHOUSE_ID,
            'Warehouse Name' => self::WAREHOUSE_NAME,
            'Age' => self::AGE,
            'Smallest On Hand' => self::QTY,
            'Price KHUSUS-1' => self::PRICE,
        ];
        $missing = [];

        foreach ($required as $label => $candidates) {
            if (!$this->containsAny($values, $candidates)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /** @return array<string,mixed> */
    public function normalize(array|stdClass $row, string $source): array
    {
        $values = $this->canonicalKeys((array) $row);
        $physicalSource = strtoupper(trim($source));
        $brand = strtoupper(trim((string) $this->first($values, self::BRAND)));
        $logicalSource = $physicalSource === 'SMI' && str_contains($brand, 'WADFOW') ? 'WADFOW' : $physicalSource;
        $age = max(0, (int) round($this->toFloat($this->first($values, self::AGE))));
        $qty = $this->toFloat($this->first($values, self::QTY));
        $price = $this->toFloat($this->first($values, self::PRICE));
        $providedSisa = $this->first($values, self::SISA);
        $sisa = ($providedSisa === null || $providedSisa === '') ? $qty * $price : $this->toFloat($providedSisa);
        $warehouseCode = trim((string) $this->first($values, self::WAREHOUSE_CODE));
        $warehouseName = trim((string) $this->first($values, self::WAREHOUSE_NAME));
        [$bucketKey, $bucketLabel] = $this->bucket($age);

        $discontinued = $this->toFlag($this->first($values, self::DISCONTINUE));
        $hasDiscountRule = $this->toFlag($this->first($values, self::HAS_DISCOUNT_RULE));
        $itemReject = $this->isSaleWarehouse($warehouseCode, $warehouseName);
        [$statusKey, $statusLabel] = $this->status($discontinued, $hasDiscountRule, $itemReject);

        return [
            'source' => $logicalSource,
            'physical_source' => $physicalSource,
            'database' => $logicalSource,
            'brand' => $brand,
            'item_id' => (int) round($this->toFloat($this->first($values, self::ITEM_ID))),
            'item_code' => trim((string) $this->first($values, self::ITEM_CODE)),
            'item_name' => trim((string) $this->first($values, self::ITEM_NAME)),
            'warehouse_id' => (int) round($this->toFloat($this->first($values, self::WAREHOUSE_ID))),
            'warehouse_code' => $warehouseCode,
            'warehouse_name' => $warehouseName,
            'age' => $age,
            'bucket_key' => $bucketKey,
            'bucket_label' => $bucketLabel,
            'qty' => $qty,
            'price' => $price,
            'sisa_stock' => $sisa,
            'discontinued' => $discontinued,
            'has_discount_rule' => $hasDiscountRule,
            'item_reject' => $itemReject,
            // Legacy key retained so older views/services do not break.
            'clearance' => $itemReject,
            'status_key' => $statusKey,
            'status_label' => $statusLabel,
        ];
    }

    /** @return array{0:string,1:string} */
    public function bucket(int $age): array
    {
        return match (true) {
            $age <= 30 => ['0-30', '0-30 Hari'],
            $age <= 60 => ['31-60', '31-60 Hari'],
            $age <= 90 => ['61-90', '61-90 Hari'],
            $age <= 120 => ['91-120', '91-120 Hari'],
            default => ['over-120', '>120 Hari'],
        };
    }

    /** @return array{0:string,1:string} */
    private function status(bool $discontinued, bool $hasDiscountRule, bool $itemReject): array
    {
        if ($itemReject) {
            return ['reject', 'ITEM REJECT'];
        }
        if ($discontinued && $hasDiscountRule) {
            return ['discontinued_sale', 'DISCONTINUE + SALE'];
        }
        if ($discontinued) {
            return ['discontinued', 'DISCONTINUE'];
        }

        return ['normal', 'NORMAL'];
    }

    private function isSaleWarehouse(string $warehouseCode, string $warehouseName): bool
    {
        return str_contains(strtoupper($warehouseCode), 'SALE')
            || str_contains(strtoupper($warehouseName), 'SALE');
    }

    /** @return array<string,mixed> */
    private function canonicalKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->canonicalKey((string) $key)] = $value;
        }

        return $normalized;
    }

    private function canonicalKey(string $key): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($key))) ?? '';
    }

    /** @param array<string,mixed> $values */
    private function containsAny(array $values, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $values)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $values */
    private function first(array $values, array $candidates): mixed
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $values)) {
                return $values[$candidate];
            }
        }

        return null;
    }

    private function toFlag(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value > 0;
        }

        $text = strtoupper(trim((string) $value));
        if (in_array($text, ['YES', 'YA', 'Y', 'TRUE', 'DISCONTINUE', 'DISCONTINUED', '1'], true)) {
            return true;
        }
        if (in_array($text, ['NO', 'TIDAK', 'N', 'FALSE', 'ACTIVE', 'NORMAL', '0'], true)) {
            return false;
        }

        return $this->toFloat($value) > 0;
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        $text = preg_replace('/[^0-9,\.\-+]/', '', $text) ?? '';
        if ($text === '' || $text === '-' || $text === '+') {
            return 0.0;
        }

        $lastComma = strrpos($text, ',');
        $lastDot = strrpos($text, '.');
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $text = str_replace('.', '', $text);
                $text = str_replace(',', '.', $text);
            } else {
                $text = str_replace(',', '', $text);
            }
        } elseif ($lastComma !== false) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : 0.0;
    }
}
