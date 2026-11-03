<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\SalesDashboardDetailPage;
use InvalidArgumentException;

final class SalesDashboardDetailQueryService
{
    private const ORDER_COLUMNS = [
        'database', 'invoice_date', 'invoice_number', 'line', 'customer_code',
        'customer_name', 'sales_person_name', 'item_code', 'item_name', 'grade',
        'quantity', 'line_total', 'line_contribution',
    ];

    /**
     * @param list<array<string,mixed>> $rows
     */
    public function paginate(
        array $rows,
        string $search,
        string $orderColumn,
        string $orderDirection,
        int $start,
        int $length,
    ): SalesDashboardDetailPage {
        $orderColumn = strtolower(trim($orderColumn));
        $orderDirection = strtolower(trim($orderDirection));

        if (!in_array($orderColumn, self::ORDER_COLUMNS, true)) {
            throw new InvalidArgumentException('Kolom pengurutan data mentah tidak valid.');
        }
        if (!in_array($orderDirection, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Arah pengurutan data mentah tidak valid.');
        }
        if ($start < 0 || $length < 10 || $length > 100) {
            throw new InvalidArgumentException('Parameter pagination data mentah tidak valid.');
        }

        $recordsTotal = count($rows);
        $needle = $this->normalizeSearch($search);
        $filtered = array_values(array_filter($rows, function (array $row) use ($needle): bool {
            if ($needle === '') {
                return true;
            }

            $haystack = $this->normalizeSearch(implode(' ', [
                $row['database'] ?? '',
                $row['invoice_date'] ?? '',
                $row['invoice_number'] ?? '',
                $row['line'] ?? '',
                $row['customer_code'] ?? '',
                $row['customer_name'] ?? '',
                $row['sales_person_name'] ?? '',
                $row['item_code'] ?? '',
                $row['item_name'] ?? '',
                $row['grade'] ?? '',
                $row['status_item'] ?? '',
                $row['clearance_status'] ?? '',
                $row['remarks'] ?? '',
            ]));

            return str_contains($haystack, $needle);
        }));

        usort($filtered, static function (array $left, array $right) use ($orderColumn, $orderDirection): int {
            $leftValue = $left[$orderColumn] ?? '';
            $rightValue = $right[$orderColumn] ?? '';
            $numeric = in_array($orderColumn, ['quantity', 'line_total', 'line_contribution'], true);
            $comparison = $numeric
                ? ((float) $leftValue <=> (float) $rightValue)
                : strnatcasecmp((string) $leftValue, (string) $rightValue);

            return $orderDirection === 'desc' ? -$comparison : $comparison;
        });

        return new SalesDashboardDetailPage(
            recordsTotal: $recordsTotal,
            recordsFiltered: count($filtered),
            rows: array_slice($filtered, $start, $length),
        );
    }

    private function normalizeSearch(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }
}
