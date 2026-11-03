<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SalesKpi\SalesKpiPeriodService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SalesKpiPeriodServiceTest extends TestCase
{
    public function test_march_31_uses_full_month_for_non_growth_and_equal_days_for_growth(): void
    {
        $period = (new SalesKpiPeriodService())->make(new DateTimeImmutable('2026-03-31'));

        self::assertSame('2026-03-01', $period->currentStart->format('Y-m-d'));
        self::assertSame('2026-03-31', $period->currentEnd->format('Y-m-d'));
        self::assertSame('2026-03-01', $period->growthCurrentStart->format('Y-m-d'));
        self::assertSame('2026-03-28', $period->growthCurrentEnd->format('Y-m-d'));
        self::assertSame('2026-02-01', $period->growthPreviousStart->format('Y-m-d'));
        self::assertSame('2026-02-28', $period->growthPreviousEnd->format('Y-m-d'));
    }

    public function test_equal_length_month_reuses_selected_day(): void
    {
        $period = (new SalesKpiPeriodService())->make(new DateTimeImmutable('2026-07-20'));

        self::assertSame('2026-07-20', $period->growthCurrentEnd->format('Y-m-d'));
        self::assertSame('2026-06-20', $period->growthPreviousEnd->format('Y-m-d'));
        self::assertFalse($period->requiresSeparateGrowthCurrentQuery());
    }
}
