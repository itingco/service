<?php

declare(strict_types=1);

namespace App\Support;

use stdClass;

final class SalesRowNormalizer
{
    private const ITEM_CODE_COLUMNS = [
        'itemcode', 'item_code', 'kodebarang', 'kode_barang', 'kdbarang',
        'kd_barang', 'kodeitem', 'kode_item', 'productcode', 'product_code',
        'kode', 'item', 'itemno', 'item_no', 'stockcode', 'stock_code',
    ];

    private const ITEM_NAME_COLUMNS = [
        'itemname', 'item_name', 'namabarang', 'nama_barang', 'nmbarang',
        'nm_barang', 'namaitem', 'nama_item', 'productname', 'product_name',
    ];

    private const QUANTITY_COLUMNS = [
        'quantity', 'qty', 'totalqty', 'total_qty', 'jumlah', 'kuantitas',
        'soldqty', 'sold_qty', 'totalquantity', 'total_quantity',
    ];

    private const SALES_COLUMNS = [
        'linetotal', 'line_total', 'totalsales', 'total_sales', 'salesamount',
        'sales_amount', 'nilaipenjualan', 'nilai_penjualan', 'netamount',
        'net_amount', 'subtotal', 'sub_total', 'amount', 'total', 'sales',
        'penjualan', 'totalpenjualan', 'total_penjualan', 'nilai', 'omset',
        'revenue', 'nettotal', 'net_total',
    ];

    /** @return list<string> */
    public function missingRequiredColumns(array|stdClass $row): array
    {
        $values = $this->lowercaseKeys((array) $row);
        $missing = [];

        if (!$this->containsAny($values, self::ITEM_CODE_COLUMNS)) {
            $missing[] = 'item_code';
        }
        if (!$this->containsAny($values, self::QUANTITY_COLUMNS)) {
            $missing[] = 'quantity';
        }
        if (!$this->containsAny($values, self::SALES_COLUMNS)) {
            $missing[] = 'sales';
        }

        return $missing;
    }

    /**
     * @return array{item_code:string,item_name:string,quantity:float,sales:float,source:string}|null
     */
    public function normalize(array|stdClass $row, string $source): ?array
    {
        $values = $this->lowercaseKeys((array) $row);
        $itemCode = strtoupper(trim((string) $this->first($values, self::ITEM_CODE_COLUMNS)));

        if ($itemCode === '') {
            return null;
        }

        $itemName = trim((string) $this->first($values, self::ITEM_NAME_COLUMNS));

        return [
            'item_code' => $itemCode,
            'item_name' => $itemName !== '' ? $itemName : $itemCode,
            'quantity' => $this->toFloat($this->first($values, self::QUANTITY_COLUMNS)),
            'sales' => $this->toFloat($this->first($values, self::SALES_COLUMNS)),
            'source' => strtoupper(trim($source)),
        ];
    }

    /** @return array<string,mixed> */
    private function lowercaseKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[strtolower(trim((string) $key))] = $value;
        }

        return $normalized;
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
            $digitsAfterComma = strlen($text) - $lastComma - 1;
            $text = $digitsAfterComma <= 2
                ? str_replace(',', '.', $text)
                : str_replace(',', '', $text);
        } elseif ($lastDot !== false) {
            $digitsAfterDot = strlen($text) - $lastDot - 1;
            if ($digitsAfterDot === 3 && substr_count($text, '.') >= 1) {
                $text = str_replace('.', '', $text);
            }
        }

        return is_numeric($text) ? (float) $text : 0.0;
    }
}
