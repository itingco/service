<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Data\SalesKpi\SalesKpiDetailPage;
use App\Data\SalesKpi\SalesKpiTransaction;
use InvalidArgumentException;

final class SalesKpiDetailQueryService
{
    private const ORDER_ACCESSORS = [
        'database' => 'database',
        'invoice_date' => 'invoiceDate',
        'invoice_number' => 'invoiceNumber',
        'line' => 'line',
        'item_code' => 'itemCode',
        'item_name' => 'itemName',
        'grade' => 'grade',
        'quantity' => 'quantity',
        'line_total' => 'lineTotal',
    ];

    /** @param list<SalesKpiTransaction> $rows */
    public function paginate(
        array $rows,
        string $detailType,
        string $search,
        string $orderColumn,
        string $orderDirection,
        int $start,
        int $length,
    ): SalesKpiDetailPage {
        $detailType = $this->validatedDetailType($detailType);
        $orderColumn = strtolower(trim($orderColumn));
        $orderDirection = strtolower(trim($orderDirection));

        if (!array_key_exists($orderColumn, self::ORDER_ACCESSORS)) {
            throw new InvalidArgumentException('Kolom pengurutan detail KPI tidak valid.');
        }
        if (!in_array($orderDirection, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Arah pengurutan detail KPI tidak valid.');
        }
        if ($start < 0 || $length < 1 || $length > 100) {
            throw new InvalidArgumentException('Parameter pagination detail KPI tidak valid.');
        }

        $recordsTotal = count($rows);
        $filtered = $this->filteredRows($rows, $detailType, $search);
        $property = self::ORDER_ACCESSORS[$orderColumn];
        usort($filtered, static function (SalesKpiTransaction $left, SalesKpiTransaction $right) use ($property, $orderDirection): int {
            $leftValue = $left->{$property};
            $rightValue = $right->{$property};
            $comparison = is_float($leftValue) || is_int($leftValue)
                ? ((float) $leftValue <=> (float) $rightValue)
                : strnatcasecmp((string) $leftValue, (string) $rightValue);

            return $orderDirection === 'desc' ? -$comparison : $comparison;
        });

        return new SalesKpiDetailPage(
            recordsTotal: $recordsTotal,
            recordsFiltered: count($filtered),
            rows: array_map($this->mapRow(...), array_slice($filtered, $start, $length)),
        );
    }

    /**
     * @param list<SalesKpiTransaction> $rows
     * @return list<SalesKpiTransaction>
     */
    public function filterAll(array $rows, string $detailType, string $search = ''): array
    {
        return $this->filteredRows($rows, $this->validatedDetailType($detailType), $search);
    }

    /**
     * @param list<SalesKpiTransaction> $rows
     * @return list<SalesKpiTransaction>
     */
    private function filteredRows(array $rows, string $detailType, string $search): array
    {
        $needle = $this->normalizeSearch($search);

        return array_values(array_filter($rows, function (SalesKpiTransaction $row) use ($detailType, $needle): bool {
            if ($detailType === 'risk' && !$row->discontinued && !$row->clearance) {
                return false;
            }
            if ($detailType === 'grade' && !in_array($row->grade, ['A', 'B'], true)) {
                return false;
            }
            if ($needle === '') {
                return true;
            }

            $haystack = $this->normalizeSearch(implode(' ', [
                $row->database,
                $row->invoiceDate,
                $row->invoiceNumber,
                $row->line,
                $row->itemCode,
                $row->itemName,
                $row->grade,
            ]));

            return str_contains($haystack, $needle);
        }));
    }

    /** @return array<string,string|float|bool> */
    private function mapRow(SalesKpiTransaction $row): array
    {
        return [
            'database' => $row->database,
            'invoice_date' => $row->invoiceDate,
            'invoice_number' => $row->invoiceNumber,
            'line' => $row->line,
            'item_code' => $row->itemCode,
            'item_name' => $row->itemName,
            'grade' => $row->grade,
            'discontinued' => $row->discontinued,
            'clearance' => $row->clearance,
            'clearance_status' => $row->clearanceStatus,
            'quantity' => $row->quantity,
            'line_total' => $row->lineTotal,
        ];
    }

    private function validatedDetailType(string $detailType): string
    {
        $detailType = strtolower(trim($detailType));
        if (!in_array($detailType, ['all', 'risk', 'grade'], true)) {
            throw new InvalidArgumentException('Jenis detail KPI tidak valid.');
        }

        return $detailType;
    }

    private function normalizeSearch(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }
}
