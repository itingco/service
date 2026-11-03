<?php

namespace Tests\Unit;

use App\Data\SalesByItemFilter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SalesByItemFilterTest extends TestCase
{
    public function test_it_derives_month_start_from_end_date(): void
    {
        $filter = SalesByItemFilter::from('all', '2026-07-22');

        self::assertSame('2026-07-01', $filter->startDate->format('Y-m-d'));
        self::assertSame('2026-07-22', $filter->endDate->format('Y-m-d'));
        self::assertSame(['INGCO', 'SMI'], $filter->sources());
    }

    public function test_it_rejects_unknown_database(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SalesByItemFilter::from('unknown', '2026-07-22');
    }
}
