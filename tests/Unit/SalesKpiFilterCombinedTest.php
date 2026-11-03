<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesKpi\SalesKpiFilter;
use PHPUnit\Framework\TestCase;

final class SalesKpiFilterCombinedTest extends TestCase
{
    public function test_filter_is_always_combined_and_query_has_no_database_key(): void
    {
        $filter = SalesKpiFilter::from(
            endDate: '2026-07-28',
            line: 'JAKARTA',
            discontinue: 'all',
            clearance: 'all',
            grade: 'ALL',
            search: '',
        );

        self::assertTrue($filter->isCombined());
        self::assertSame(['INGCO', 'SMI'], $filter->sources());
        self::assertSame(['AS_INGCO', 'AS_SMI'], $filter->databaseKeys());
        self::assertSame('GABUNGAN', $filter->databaseKey());
        self::assertSame('AS_INGCO + AS_SMI', $filter->databaseLabel());
        self::assertArrayNotHasKey('database', $filter->query());
    }
}
