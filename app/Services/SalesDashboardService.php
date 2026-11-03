<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SalesDataSource;
use App\Data\SalesDashboardFilter;
use App\Data\SalesDashboardReport;
use App\Exceptions\SalesDataException;
use App\Support\SalesDashboardRowNormalizer;
use DateTimeImmutable;
use Throwable;

final class SalesDashboardService
{
    public function __construct(
        private readonly SalesDataSource $dataSource,
        private readonly SalesDashboardRowNormalizer $normalizer,
        private readonly SalesDashboardAggregator $aggregator,
        private readonly SalesDashboardClassificationService $classifier,
    ) {
    }

    public function generate(SalesDashboardFilter $filter): SalesDashboardReport
    {
        $periodRows = [
            'dtd' => [],
            'ldtd' => [],
            'mtd' => [],
            'lmtd' => [],
        ];

        foreach ($filter->sources() as $source) {
            $ranges = [
                'dtd' => [$filter->selectedDate, $filter->selectedDate],
                'ldtd' => [$filter->previousDayDate, $filter->previousDayDate],
                'mtd' => [$filter->currentStartDate, $filter->currentEndDate],
                'lmtd' => [$filter->previousStartDate, $filter->previousEndDate],
            ];

            foreach ($ranges as $period => [$startDate, $endDate]) {
                $rows = \App\Support\CachedSalesQuery::fetch($this->dataSource, $source, $startDate, $endDate);
                $periodRows[$period] = [
                    ...$periodRows[$period],
                    ...$this->normalizeRows($rows, $source, $filter),
                ];
            }
        }

        return $this->aggregator->aggregate($filter, $periodRows);
    }


    /** @return list<array<string,mixed>> */
    public function details(SalesDashboardFilter $filter): array
    {
        return $this->detailsForPeriod(
            $filter,
            $filter->currentStartDate,
            $filter->currentEndDate,
        );
    }

    /** @return list<array<string,mixed>> */
    public function detailsForPeriod(
        SalesDashboardFilter $filter,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
    ): array {
        $rows = [];
        foreach ($filter->sources() as $source) {
            $fetched = \App\Support\CachedSalesQuery::fetch($this->dataSource, $source, $startDate, $endDate);
            $rows = [
                ...$rows,
                ...$this->normalizeRows($fetched, $source, $filter),
            ];
        }

        return $this->aggregator->prepareDetailRows($rows);
    }

    /**
     * @param list<object|array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function normalizeRows(array $rows, string $source, SalesDashboardFilter $filter): array
    {
        if ($rows === []) {
            return [];
        }

        $missing = $this->normalizer->missingRequiredColumns($rows[0]);
        $missingStatus = []; // Status discontinue now comes from IC_Items metadata.
        $missing = array_values(array_unique([...$missing, ...$missingStatus]));

        if ($missing !== []) {
            $columns = implode(', ', array_keys((array) $rows[0]));
            throw new SalesDataException(
                $source,
                'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. '.
                'Kolom function yang tersedia: '.$columns,
            );
        }

        $normalizedRows = [];
        foreach ($rows as $row) {
            try {
                $normalized = $this->normalizer->normalize($row, $source);
            } catch (Throwable $exception) {
                throw new SalesDataException($source, $exception->getMessage(), $exception);
            }

            $normalizedRows[] = $normalized;
        }

        $classified = $this->classifier->classify($normalizedRows, $source);
        return array_values(array_filter($classified, static fn (array $row): bool =>
            $filter->matchesBusiness((string) $row['database']) &&
            $filter->matchesCategory((string) $row['item_status_category'])
        ));
    }
}
