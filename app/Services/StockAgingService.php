<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SalesDataSource;
use App\Contracts\StockAgingDataSource;
use App\Data\StockAgingFilter;
use App\Data\StockAgingReport;
use App\Exceptions\SalesDataException;
use App\Exceptions\StockAgingDataException;
use App\Support\StockAgingRowNormalizer;
use App\Support\StockAgingSalesRowNormalizer;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class StockAgingService
{
    public function __construct(
        private readonly StockAgingDataSource $stockDataSource,
        private readonly SalesDataSource $salesDataSource,
        private readonly StockAgingRowNormalizer $stockNormalizer,
        private readonly StockAgingSalesRowNormalizer $salesNormalizer,
        private readonly StockAgingAggregator $aggregator,
        private readonly int $cacheSeconds = 0,
    ) {
    }

    public function generate(StockAgingFilter $filter): StockAgingReport
    {
        $combined = [];
        $failedSources = [];
        $firstException = null;

        foreach ($filter->sources() as $source) {
            try {
                $stockRows = $this->fetchStock($source, $filter);
                $salesRows = $this->fetchSales($source, $filter);

                $this->assertStockColumns($stockRows, $source);
                $this->assertSalesColumns($salesRows, $source);

                $salesByItemWarehouse = [];
                foreach ($salesRows as $salesRow) {
                    $normalizedSales = $this->salesNormalizer->normalize($salesRow);
                    $key = $this->matchKey($normalizedSales['item_id'], $normalizedSales['warehouse_id']);
                    $salesByItemWarehouse[$key] = ($salesByItemWarehouse[$key] ?? 0.0) + $normalizedSales['sales'];
                }

                foreach ($stockRows as $stockRow) {
                    $normalizedStock = $this->stockNormalizer->normalize($stockRow, $source);
                    if ($normalizedStock['item_id'] <= 0 || $normalizedStock['warehouse_id'] <= 0) {
                        continue;
                    }
                    if ((float) $normalizedStock['qty'] == 0.0) {
                        continue;
                    }

                    $key = $this->matchKey((int) $normalizedStock['item_id'], (int) $normalizedStock['warehouse_id']);
                    $normalizedStock['sales_mtd'] = (float) ($salesByItemWarehouse[$key] ?? 0.0);
                    $combined[] = $normalizedStock;
                }
            } catch (Throwable $exception) {
                $failedSources[] = $source;
                $firstException ??= $exception;
            }
        }

        if ($failedSources !== []) {
            if ($firstException instanceof StockAgingDataException && count(array_unique($failedSources)) === 1) {
                throw $firstException;
            }

            throw StockAgingDataException::forSources(
                array_values(array_unique($failedSources)),
                'Satu atau lebih database Stock Aging gagal dimuat.',
                $firstException,
            );
        }

        return $this->aggregator->aggregate($filter, $combined);
    }

    /** @return list<object|array<string,mixed>> */
    private function fetchStock(string $source, StockAgingFilter $filter): array
    {
        if ($this->cacheSeconds <= 0) {
            return $this->stockDataSource->fetch($source, $filter->selectedDate);
        }

        $key = 'stock-aging:stock:'.strtolower($source).':'.$filter->selectedDate->format('Y-m-d');

        return Cache::remember($key, $this->cacheSeconds, fn (): array => $this->stockDataSource->fetch($source, $filter->selectedDate));
    }

    /** @return list<object|array<string,mixed>> */
    private function fetchSales(string $source, StockAgingFilter $filter): array
    {
        $loader = function () use ($source, $filter): array {
            try {
                return $this->salesDataSource->fetch($source, $filter->mtdStartDate, $filter->selectedDate);
            } catch (SalesDataException $exception) {
                throw StockAgingDataException::forSource($source, $exception->getMessage(), $exception);
            }
        };

        if ($this->cacheSeconds <= 0) {
            return $loader();
        }

        $key = 'stock-aging:sales:'.strtolower($source).':'.$filter->mtdStartDate->format('Y-m-d').':'.$filter->selectedDate->format('Y-m-d');

        return Cache::remember($key, $this->cacheSeconds, $loader);
    }

    /** @param list<object|array<string,mixed>> $rows */
    private function assertStockColumns(array $rows, string $source): void
    {
        if ($rows === []) {
            return;
        }

        $missing = $this->stockNormalizer->missingRequiredColumns($rows[0]);
        if ($missing !== []) {
            throw StockAgingDataException::forSource(
                $source,
                'Kolom USP Stock Aging tidak lengkap: '.implode(', ', $missing).'.',
            );
        }
    }

    /** @param list<object|array<string,mixed>> $rows */
    private function assertSalesColumns(array $rows, string $source): void
    {
        if ($rows === []) {
            return;
        }

        $missing = $this->salesNormalizer->missingRequiredColumns($rows[0]);
        if ($missing !== []) {
            throw StockAgingDataException::forSource(
                $source,
                'fn_PenjualanLini belum memiliki kolom wajib Stock Aging: '.implode(', ', $missing).'.',
            );
        }
    }

    private function matchKey(int $itemId, int $warehouseId): string
    {
        return $itemId.'|'.$warehouseId;
    }
}
