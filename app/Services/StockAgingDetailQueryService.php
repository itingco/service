<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;

final class StockAgingDetailQueryService
{
    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public function filterAll(
        array $rows,
        string $source,
        int $warehouseId,
        string $bucket,
    ): array {
        $filtered = array_values(array_filter(
            $rows,
            static fn (array $row): bool => strtoupper((string) $row['source']) === strtoupper($source)
                && (int) $row['warehouse_id'] === $warehouseId
                && (string) $row['bucket_key'] === $bucket,
        ));

        usort($filtered, static function (array $a, array $b): int {
            $codeCompare = strcasecmp((string) $a['item_code'], (string) $b['item_code']);
            if ($codeCompare !== 0) {
                return $codeCompare;
            }

            return (int) $b['age'] <=> (int) $a['age'];
        });

        return $filtered;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return LengthAwarePaginator<int,array<string,mixed>>
     */
    public function paginate(
        array $rows,
        string $source,
        int $warehouseId,
        string $bucket,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $filtered = $this->filterAll($rows, $source, $warehouseId, $bucket);

        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        return new LengthAwarePaginator(
            items: array_slice($filtered, $offset, $perPage),
            total: count($filtered),
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => url()->current()],
        );
    }
}
