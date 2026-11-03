<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SalesDataSource;
use App\Services\SalesKpi\SalesKpiDataService;
use App\Services\SalesKpi\SalesKpiPeriodService;
use App\Support\SalesKpiRowNormalizer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SalesKpiDataServiceTest extends TestCase
{
    public function test_july_20_reuses_current_rows_for_growth_and_fetches_twice(): void
    {
        $fake = $this->recordingSource();
        $period = (new SalesKpiPeriodService())->make(new \DateTimeImmutable('2026-07-20'));

        $data = (new SalesKpiDataService($fake, new SalesKpiRowNormalizer()))->loadSource('INGCO', $period);

        self::assertCount(2, $fake->calls);
        self::assertSame(['INGCO', '2026-07-01', '2026-07-20'], $fake->calls[0]);
        self::assertSame(['INGCO', '2026-06-01', '2026-06-20'], $fake->calls[1]);
        self::assertSame($data->current, $data->growthCurrent);
        self::assertSame('AS_INGCO', $data->current[0]->database);
    }

    public function test_march_31_fetches_full_current_and_two_equal_day_growth_ranges(): void
    {
        $fake = $this->recordingSource();
        $period = (new SalesKpiPeriodService())->make(new \DateTimeImmutable('2026-03-31'));

        (new SalesKpiDataService($fake, new SalesKpiRowNormalizer()))->loadSource('INGCO', $period);

        self::assertCount(3, $fake->calls);
        self::assertSame(['INGCO', '2026-03-01', '2026-03-31'], $fake->calls[0]);
        self::assertSame(['INGCO', '2026-03-01', '2026-03-28'], $fake->calls[1]);
        self::assertSame(['INGCO', '2026-02-01', '2026-02-28'], $fake->calls[2]);
    }

    private function recordingSource(): SalesDataSource
    {
        return new class implements SalesDataSource {
            /** @var list<array{string,string,string}> */
            public array $calls = [];

            public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
            {
                $this->calls[] = [$source, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')];

                return [(object) [
                    'LiniPenjualan' => 'JAKARTA',
                    'LineTotal' => 100,
                    'Discontinue' => 0,
                    'StatusClearanceSale' => 'BUKAN CLEARANCE SALE',
                    'Grade' => 'A',
                ]];
            }
        };
    }
}
