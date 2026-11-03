<?php

declare(strict_types=1);

namespace App\Services\SalesKpi;

use App\Data\SalesKpi\SalesKpiPeriod;
use DateTimeImmutable;

final class SalesKpiPeriodService
{
    public function make(DateTimeImmutable $selectedDate): SalesKpiPeriod
    {
        $currentStart = $selectedDate->modify('first day of this month');
        $previousStart = $currentStart->modify('-1 month');
        $previousEndOfMonth = $previousStart->modify('last day of this month');
        $comparableDay = min(
            (int) $selectedDate->format('j'),
            (int) $previousEndOfMonth->format('j'),
        );

        $growthCurrentEnd = $currentStart->setDate(
            (int) $currentStart->format('Y'),
            (int) $currentStart->format('n'),
            $comparableDay,
        );
        $growthPreviousEnd = $previousStart->setDate(
            (int) $previousStart->format('Y'),
            (int) $previousStart->format('n'),
            $comparableDay,
        );

        return new SalesKpiPeriod(
            currentStart: $currentStart,
            currentEnd: $selectedDate,
            growthCurrentStart: $currentStart,
            growthCurrentEnd: $growthCurrentEnd,
            growthPreviousStart: $previousStart,
            growthPreviousEnd: $growthPreviousEnd,
        );
    }
}
