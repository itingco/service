<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\SalesKpi\SalesKpiTransaction;
use DateTimeInterface;
use InvalidArgumentException;

final class SalesKpiRowNormalizer
{
    private const REQUIRED = [
        'LiniPenjualan',
        'LineTotal',
        'Discontinue',
        'StatusClearanceSale',
        'Grade',
    ];

    /** @return list<string> */
    public function missingRequiredColumns(object|array $row): array
    {
        $values = (array) $row;

        return array_values(array_filter(
            self::REQUIRED,
            static fn (string $column): bool => !array_key_exists($column, $values),
        ));
    }

    public function normalize(object|array $row, string $databaseKey): SalesKpiTransaction
    {
        $databaseKey = strtoupper(trim($databaseKey));
        if (!in_array($databaseKey, ['AS_INGCO', 'AS_SMI'], true)) {
            throw new InvalidArgumentException('Sumber database transaksi KPI tidak valid.');
        }

        $values = (array) $row;
        $missing = $this->missingRequiredColumns($row);
        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Kolom '.implode(', ', $missing).' tidak ditemukan pada hasil fn_PenjualanLini.',
            );
        }

        $lineDisplay = SalesLineName::normalize((string) ($values['LiniPenjualan'] ?? ''));
        $lineKey = $lineDisplay;
        $clearanceStatus = strtoupper(trim((string) ($values['StatusClearanceSale'] ?? '')));
        $grade = strtoupper(trim((string) ($values['Grade'] ?? '')));

        return new SalesKpiTransaction(
            database: $databaseKey,
            line: $lineDisplay,
            lineKey: $lineKey,
            lineTotal: $this->toFloat($values['LineTotal'] ?? 0),
            discontinued: $this->toFloat($values['Discontinue'] ?? 0) === 1.0,
            clearance: in_array($clearanceStatus, ['CLEARANCE SALE', 'ITEM REJECT'], true),
            grade: $grade,
            quantity: $this->toFloat($values['Quantity'] ?? 0),
            invoiceDate: $this->dateText($values['InvoiceDate'] ?? ''),
            invoiceNumber: trim((string) ($values['InvoiceNumber'] ?? '')),
            itemCode: trim((string) ($values['ItemCode'] ?? '')),
            itemName: trim((string) ($values['ItemName'] ?? '')),
            itemStatus: strtoupper(trim((string) ($values['StatusItem'] ?? ''))),
            clearanceStatus: $clearanceStatus,
            clearanceDiscount: trim((string) ($values['ClearanceSaleDiscount'] ?? '')),
        );
    }

    private function collapseWhitespace(string $value): string
    {
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function dateText(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    private function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '' || strtoupper($text) === 'NULL') {
            return 0.0;
        }

        $text = str_replace(["\u{00A0}", ' '], '', $text);
        if (is_numeric($text)) {
            return (float) $text;
        }

        $hasComma = str_contains($text, ',');
        $hasDot = str_contains($text, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($text, ',');
            $lastDot = strrpos($text, '.');

            if ($lastDot !== false && $lastComma !== false && $lastDot > $lastComma) {
                $text = str_replace(',', '', $text);
            } else {
                $text = str_replace('.', '', $text);
                $text = str_replace(',', '.', $text);
            }
        } elseif ($hasDot && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $text) === 1) {
            $text = str_replace('.', '', $text);
        } elseif ($hasComma && preg_match('/^-?\d{1,3}(,\d{3})+$/', $text) === 1) {
            $text = str_replace(',', '', $text);
        } elseif ($hasComma) {
            $text = str_replace(',', '.', $text);
        }

        $text = preg_replace('/[^0-9.\-]/', '', $text) ?? '';

        return is_numeric($text) ? (float) $text : 0.0;
    }
}
