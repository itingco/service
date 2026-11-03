<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Contracts\SalesDataSource;
use App\Data\SalesKpi\SalesKpiDataset;
use App\Data\SalesKpi\SalesKpiPeriod;
use App\Data\SalesKpi\SalesKpiTransaction;
use App\Exceptions\SalesKpiDataException;
use App\Support\SalesKpiRowNormalizer;
use DateTimeImmutable;
use Throwable;

final class SalesKpiDataService
{
    public function __construct(
        private readonly SalesDataSource $dataSource,
        private readonly SalesKpiRowNormalizer $normalizer,
    ) {
    }

    public function loadSource(string $source, SalesKpiPeriod $period): SalesKpiDataset
    {
        $source = strtoupper(trim($source));
        if (!in_array($source, ['INGCO', 'SMI'], true)) {
            throw SalesKpiDataException::forSource($source, 'Sumber database KPI tidak valid.');
        }


        try {
            $current = $this->loadRange(
                $source,
                $period->currentStart,
                $period->currentEnd,
            );

            $growthCurrent = $period->requiresSeparateGrowthCurrentQuery()
                ? $this->loadRange(
                    $source,
                    $period->growthCurrentStart,
                    $period->growthCurrentEnd,
                )
                : $current;

            $growthPrevious = $this->loadRange(
                $source,
                $period->growthPreviousStart,
                $period->growthPreviousEnd,
            );

            return new SalesKpiDataset($current, $growthCurrent, $growthPrevious);
        } catch (SalesKpiDataException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw SalesKpiDataException::forSource(
                $source,
                'Function dbo.fn_PenjualanLini tidak dapat dijalankan untuk periode yang dipilih.',
                $exception,
            );
        }
    }

    /** @return list<SalesKpiTransaction> */
    public function loadRange(
        string $source,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
    ): array {
        $source = strtoupper(trim($source));
        if (!in_array($source, ['INGCO', 'SMI'], true)) {
            throw SalesKpiDataException::forSource($source, 'Sumber database KPI tidak valid.');
        }

        try {
            return $this->fetchAndNormalize($source, 'AS_'.$source, $startDate, $endDate);
        } catch (SalesKpiDataException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw SalesKpiDataException::forSource(
                $source,
                'Function dbo.fn_PenjualanLini tidak dapat dijalankan untuk periode yang dipilih.',
                $exception,
            );
        }
    }

    /** @return list<SalesKpiTransaction> */
    private function fetchAndNormalize(
        string $source,
        string $databaseKey,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
    ): array {
        $rawRows = $this->dataSource->fetch($source, $startDate, $endDate);
        if ($rawRows === []) {
            return [];
        }

        $missing = $this->normalizer->missingRequiredColumns($rawRows[0]);
        if ($missing !== []) {
            throw SalesKpiDataException::forSource(
                $source,
                'Kolom '.implode(', ', $missing).' tidak ditemukan pada hasil fn_PenjualanLini.',
            );
        }

        $normalized = [];
        foreach ($rawRows as $row) {
            try {
                $normalized[] = $this->normalizer->normalize($row, $databaseKey);
            } catch (Throwable $exception) {
                throw SalesKpiDataException::forSource(
                    $source,
                    $exception->getMessage(),
                    $exception,
                );
            }
        }

        return $normalized;
    }
}
