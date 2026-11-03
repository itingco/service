<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SalesDataSource;
use App\Data\SalesKpi\SalesKpiFilter;
use App\Exceptions\SalesDataException;
use App\Exceptions\SalesKpiDataException;
use App\Services\SalesKpi\SalesKpiCombinedDataService;
use App\Services\SalesKpi\SalesKpiDataService;
use App\Services\SalesKpi\SalesKpiPeriodService;
use App\Support\SalesKpiRowNormalizer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SalesKpiCombinedDataServiceTest extends TestCase
{
    public function test_combined_mode_loads_and_tags_both_sources(): void
    {
        $source = new class implements SalesDataSource {
            public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
            {
                return [[
                    'LiniPenjualan' => 'JAKARTA', 'LineTotal' => 100,
                    'Discontinue' => 0, 'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
                    'Grade' => 'A',
                ]];
            }
        };
        $service = new SalesKpiCombinedDataService(new SalesKpiDataService($source, new SalesKpiRowNormalizer()));
        $filter = SalesKpiFilter::from('2026-07-20');
        $period = (new SalesKpiPeriodService())->make($filter->selectedDate);

        $dataset = $service->load($filter, $period);

        self::assertSame(['AS_INGCO', 'AS_SMI'], array_values(array_unique(array_map(
            static fn ($row): string => $row->database,
            $dataset->current,
        ))));
    }

    public function test_failure_of_one_source_aborts_combined_mode_and_names_it(): void
    {
        $source = new class implements SalesDataSource {
            public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
            {
                if ($source === 'SMI') {
                    throw new SalesDataException('SMI', 'connection failed');
                }

                return [];
            }
        };
        $service = new SalesKpiCombinedDataService(new SalesKpiDataService($source, new SalesKpiRowNormalizer()));
        $filter = SalesKpiFilter::from('2026-07-20');
        $period = (new SalesKpiPeriodService())->make($filter->selectedDate);

        try {
            $service->load($filter, $period);
            self::fail('Expected SalesKpiDataException.');
        } catch (SalesKpiDataException $exception) {
            self::assertSame(['SMI'], $exception->failedSources());
        }
    }
}
