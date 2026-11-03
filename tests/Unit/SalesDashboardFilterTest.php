<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\SalesDashboardFilter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SalesDashboardFilterTest extends TestCase
{
    public function test_it_builds_current_and_previous_comparable_periods(): void
    {
        $filter = SalesDashboardFilter::from('all', '2026-07-22', 'clearance');

        self::assertSame('2026-06-01', $filter->queryStartDate->format('Y-m-d'));
        self::assertSame('2026-07-01', $filter->currentStartDate->format('Y-m-d'));
        self::assertSame('2026-06-22', $filter->previousEndDate->format('Y-m-d'));
        self::assertSame(27, $filter->totalSalesDays);
        self::assertSame(21, $filter->elapsedDays);
        self::assertSame(6, $filter->remainingSalesDays);
        self::assertSame(['INGCO', 'SMI'], $filter->sources());
    }

    public function test_it_rejects_an_unknown_item_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SalesDashboardFilter::from('all', '2026-07-22', 'invalid');
    }
}
