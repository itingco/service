<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SalesDataSource;
use App\Data\SalesByItemFilter;
use App\Data\SalesByItemReport;
use App\Exceptions\SalesDataException;
use App\Support\SalesRowNormalizer;

class SalesByItemService
{
    public function __construct(
        private readonly SalesDataSource $dataSource,
        private readonly SalesRowNormalizer $normalizer,
        private readonly SalesByItemAggregator $aggregator,
    ) {
    }

    public function generate(SalesByItemFilter $filter): SalesByItemReport
    {
        $transactions = [];

        foreach ($filter->sources() as $source) {
            $rows = $this->dataSource->fetch($source, $filter->startDate, $filter->endDate);

            if ($rows !== []) {
                $missing = $this->normalizer->missingRequiredColumns($rows[0]);
                if ($missing !== []) {
                    $columns = implode(', ', array_keys((array) $rows[0]));
                    throw new SalesDataException(
                        $source,
                        'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. '.
                        'Kolom function yang tersedia: '.$columns
                    );
                }
            }

            foreach ($rows as $row) {
                $normalized = $this->normalizer->normalize($row, $source);
                if ($normalized !== null) {
                    $transactions[] = $normalized;
                }
            }
        }

        return $this->aggregator->aggregate($filter, $transactions);
    }
}
