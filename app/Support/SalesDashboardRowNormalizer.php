<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use InvalidArgumentException;
use stdClass;

final class SalesDashboardRowNormalizer
{
    private const LINE_COLUMNS = [
        'linipenjualan', 'salesline', 'saleschannel', 'channel', 'lini',
        'kategori', 'category', 'linecategory', 'salescategory',
    ];

    private const SALES_COLUMNS = [
        'linetotal', 'totalsales', 'salesamount', 'nilaipenjualan', 'netamount',
        'subtotal', 'amount', 'total', 'sales', 'penjualan', 'totalpenjualan',
        'nilai', 'omset', 'revenue', 'nettotal',
    ];

    private const INVOICE_NUMBER_COLUMNS = ['invoicenumber', 'invoice', 'fakturnumber', 'nofaktur'];
    private const INVOICE_DATE_COLUMNS = ['invoicedate', 'tanggalinvoice', 'tanggalfaktur', 'date'];
    private const CUSTOMER_CODE_COLUMNS = ['customercode', 'kodecustomer', 'customerno'];
    private const CUSTOMER_NAME_COLUMNS = ['customername', 'namacustomer'];
    private const SALES_PERSON_COLUMNS = ['salespersonname', 'salesperson', 'salesname', 'namasales'];

    private const ITEM_CODE_COLUMNS = [
        'itemcode', 'kodebarang', 'kdbarang', 'kodeitem', 'productcode',
        'itemno', 'stockcode',
    ];

    private const ITEM_NAME_COLUMNS = [
        'itemname', 'namabarang', 'nmbarang', 'namaitem', 'productname',
    ];

    private const GRADE_COLUMNS = ['grade', 'itemgrade'];
    private const QUANTITY_COLUMNS = ['quantity', 'qty', 'jumlah'];
    private const DISCOUNT_COLUMNS = ['discount', 'discount1'];
    private const DISCOUNT2_COLUMNS = ['discount2'];
    private const PRICE_LEVEL_COLUMNS = ['pricelevelname', 'pricelevel'];
    private const REMARKS_COLUMNS = ['remarks', 'remark', 'keterangan'];
    private const STATUS_ITEM_COLUMNS = ['statusitem', 'itemstatus'];
    private const CLEARANCE_STATUS_COLUMNS = ['statusclearancesale', 'statusclearence', 'clearancestatus'];
    private const CLEARANCE_DISCOUNT_COLUMNS = ['clearancesalediscount', 'clearencesalediscount', 'discountclearance', 'discountclearence'];

    private const DISCONTINUE_COLUMNS = [
        'discontinue', 'discontinued', 'isdiscontinue', 'isdiscontinued',
        'statusitem', 'itemdiscontinue',
    ];

    private const CLEARANCE_COLUMNS = [
        'statusclearancesale', 'statusclearence', 'clearancestatus',
        'clearancesale', 'clearencesale', 'clearance', 'clearence',
        'isclearancesale', 'isclearencesale', 'clearancesalediscount',
        'clearencesalediscount', 'discountclearance', 'discountclearence',
        'discountclearancesale', 'discountclearencesale',
    ];

    /** @return list<string> */
    public function missingRequiredColumns(array|stdClass $row): array
    {
        $values = $this->canonicalKeys((array) $row);
        $missing = [];

        if (!$this->containsAny($values, self::LINE_COLUMNS)) {
            $missing[] = 'sales_line';
        }
        if (!$this->containsAny($values, self::SALES_COLUMNS)) {
            $missing[] = 'sales';
        }

        return $missing;
    }

    /** @return list<string> */
    public function missingColumnsForStatus(array|stdClass $row, string $status): array
    {
        $values = $this->canonicalKeys((array) $row);
        $missing = [];

        if (in_array($status, ['normal', 'discontinued', 'both'], true)
            && !$this->containsAny($values, self::DISCONTINUE_COLUMNS)) {
            $missing[] = 'discontinue';
        }

        if (in_array($status, ['normal', 'clearance', 'both'], true)
            && !$this->containsAny($values, self::CLEARANCE_COLUMNS)) {
            $missing[] = 'clearance';
        }

        return $missing;
    }

    /** @return array<string,mixed> */
    public function normalize(array|stdClass $row, string $source): array
    {
        $values = $this->canonicalKeys((array) $row);
        $line = $this->normalizeText($this->first($values, self::LINE_COLUMNS));

        if ($line === '') {
            throw new InvalidArgumentException('Lini penjualan kosong pada hasil function.');
        }

        $clearanceStatus = trim((string) $this->first($values, self::CLEARANCE_STATUS_COLUMNS));
        $clearanceDiscount = $this->first($values, self::CLEARANCE_DISCOUNT_COLUMNS);
        $sales = $this->toFloat($this->first($values, self::SALES_COLUMNS));

        return [
            'database' => 'AS_'.strtoupper(trim($source)),
            'source' => strtoupper(trim($source)),
            'line' => $line,
            'sales' => $sales,
            'line_total' => $sales,
            'invoice_number' => trim((string) $this->first($values, self::INVOICE_NUMBER_COLUMNS)),
            'invoice_date' => $this->normalizeDate($this->first($values, self::INVOICE_DATE_COLUMNS)),
            'customer_code' => trim((string) $this->first($values, self::CUSTOMER_CODE_COLUMNS)),
            'customer_name' => trim((string) $this->first($values, self::CUSTOMER_NAME_COLUMNS)),
            'sales_person_name' => trim((string) $this->first($values, self::SALES_PERSON_COLUMNS)),
            'item_code' => trim((string) $this->first($values, self::ITEM_CODE_COLUMNS)),
            'item_name' => trim((string) $this->first($values, self::ITEM_NAME_COLUMNS)),
            'grade' => strtoupper(trim((string) $this->first($values, self::GRADE_COLUMNS))),
            'quantity' => $this->toFloat($this->first($values, self::QUANTITY_COLUMNS)),
            'discount' => $this->toFloat($this->first($values, self::DISCOUNT_COLUMNS)),
            'discount2' => $this->toFloat($this->first($values, self::DISCOUNT2_COLUMNS)),
            'price_level_name' => trim((string) $this->first($values, self::PRICE_LEVEL_COLUMNS)),
            'remarks' => trim((string) $this->first($values, self::REMARKS_COLUMNS)),
            'discontinued' => $this->toFlag($this->first($values, self::DISCONTINUE_COLUMNS)),
            'status_item' => trim((string) $this->first($values, self::STATUS_ITEM_COLUMNS)),
            'clearance' => $this->toFlag($this->first($values, self::CLEARANCE_COLUMNS)),
            'clearance_status' => $clearanceStatus,
            'clearance_discount' => $this->normalizeScalar($clearanceDiscount),
        ];
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

    private function normalizeText(mixed $value): string
    {
        $text = strtoupper(trim((string) $value));
        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    private function normalizeDate(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return substr($text, 0, 10);
    }

    private function normalizeScalar(mixed $value): string|float
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        return is_numeric($text) ? (float) $text : $text;
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
            return (float) $value > 0.0;
        }

        $text = strtoupper(trim((string) $value));
        if (in_array($text, ['TRUE', 'YES', 'YA', 'Y', 'DISCONTINUE', 'DISCONTINUED', 'CLEARANCE', 'CLEARANCE SALE', 'CLEARENCE', 'ITEM REJECT'], true)) {
            return true;
        }
        if (in_array($text, ['FALSE', 'NO', 'TIDAK', 'N', 'NORMAL', 'ACTIVE', 'BUKAN CLEARANCE SALE', 'BUKAN ITEM REJECT'], true)) {
            return false;
        }

        return $this->toFloat($value) > 0.0;
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
            $digitsAfter = strlen($text) - $lastComma - 1;
            $text = $digitsAfter <= 2
                ? str_replace(',', '.', $text)
                : str_replace(',', '', $text);
        } elseif ($lastDot !== false) {
            $digitsAfter = strlen($text) - $lastDot - 1;
            if ($digitsAfter === 3) {
                $text = str_replace('.', '', $text);
            }
        }

        return is_numeric($text) ? (float) $text : 0.0;
    }
}
