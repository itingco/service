<?php

declare(strict_types=1);

namespace App\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final class ThreeMonthSalesReviewFilter
{
    private const DATABASES = ['all', 'ingco', 'smi'];
    private const ITEM_STATUSES = ['all', 'normal', 'discontinued', 'discontinued_sale', 'clearance', 'reject'];

    public function __construct(
        public readonly string $database,
        public readonly string $itemStatus,
        public readonly DateTimeImmutable $selectedDate,
        public readonly DateTimeImmutable $periodStartDate,
        public readonly DateTimeImmutable $firstMonthStartDate,
        public readonly DateTimeImmutable $firstMonthEndDate,
        public readonly DateTimeImmutable $secondMonthStartDate,
        public readonly DateTimeImmutable $secondMonthEndDate,
        public readonly DateTimeImmutable $currentMonthStartDate,
        public readonly DateTimeImmutable $currentMonthEndDate,
        public readonly DateTimeImmutable $firstComparableEndDate,
        public readonly DateTimeImmutable $secondComparableEndDate,
        public readonly bool $currentMonthComplete,
    ) {
    }

    public static function from(string $database, string $endDate, string $itemStatus = 'all'): self
    {
        $database = strtolower(trim($database));
        if (!in_array($database, self::DATABASES, true)) {
            throw new InvalidArgumentException('Database harus all, ingco, atau smi.');
        }

        $itemStatus = strtolower(trim($itemStatus));
        if (array_diff(explode(',', $itemStatus), self::ITEM_STATUSES) !== [] || $itemStatus === '') {
            throw new InvalidArgumentException('Status item tidak valid.');
        }

        $selectedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $selectedDate === false ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException('Tanggal laporan harus menggunakan format YYYY-MM-DD.');
        }

        $currentStart = $selectedDate->modify('first day of this month');
        $currentFullEnd = $currentStart->modify('last day of this month');
        $secondStart = $currentStart->modify('-1 month');
        $firstStart = $currentStart->modify('-2 months');
        $firstEnd = $firstStart->modify('last day of this month');
        $secondEnd = $secondStart->modify('last day of this month');

        $currentMonthComplete = $selectedDate->format('Y-m-d') === $currentFullEnd->format('Y-m-d');
        $selectedDay = (int) $selectedDate->format('j');

        $firstComparableEnd = $currentMonthComplete
            ? $firstEnd
            : self::comparableEnd($firstStart, $selectedDay);
        $secondComparableEnd = $currentMonthComplete
            ? $secondEnd
            : self::comparableEnd($secondStart, $selectedDay);

        return new self(
            database: $database,
            itemStatus: $itemStatus,
            selectedDate: $selectedDate,
            periodStartDate: $firstStart,
            firstMonthStartDate: $firstStart,
            firstMonthEndDate: $firstEnd,
            secondMonthStartDate: $secondStart,
            secondMonthEndDate: $secondEnd,
            currentMonthStartDate: $currentStart,
            currentMonthEndDate: $selectedDate,
            firstComparableEndDate: $firstComparableEnd,
            secondComparableEndDate: $secondComparableEnd,
            currentMonthComplete: $currentMonthComplete,
        );
    }

    public function salesDashboardFilter(): SalesDashboardFilter
    {
        return SalesDashboardFilter::from(
            $this->database,
            $this->selectedDate->format('Y-m-d'),
            $this->itemStatus,
        );
    }

    public function databaseLabel(): string
    {
        return match ($this->database) {
            'ingco' => 'INGCO',
            'smi' => 'SMI',
            default => 'INGCO & SMI',
        };
    }

    public function itemStatusLabel(): string
    {
        // MULTISTATUS-FILTER
        $selected = explode(',', $this->itemStatus);
        if (in_array('all', $selected, true)) return 'Semua Status';
        $labels = ['normal'=>'Normal','discontinued'=>'Discontinue','discontinued_sale'=>'Discontinue + Sale','clearance'=>'Item Reject','reject'=>'Item Reject'];
        return implode(' + ', array_values(array_unique(array_map(static fn (string $key): string => $labels[$key] ?? $key, $selected))));
    }

    public function firstMonthLabel(): string
    {
        return self::monthLabel($this->firstMonthStartDate);
    }

    public function secondMonthLabel(): string
    {
        return self::monthLabel($this->secondMonthStartDate);
    }

    public function currentMonthLabel(): string
    {
        return self::monthLabel($this->currentMonthStartDate).($this->currentMonthComplete ? '' : ' MTD');
    }

    public function comparisonNote(): string
    {
        if ($this->currentMonthComplete) {
            return 'Growth membandingkan bulan penuh dengan bulan sebelumnya.';
        }

        return sprintf(
            'Growth bulan berjalan memakai pembanding tanggal 01-%s s/d %s-%s agar perbandingan adil.',
            $this->secondMonthStartDate->format('m-Y'),
            $this->secondComparableEndDate->format('d'),
            $this->secondComparableEndDate->format('m-Y'),
        );
    }

    private static function comparableEnd(DateTimeImmutable $monthStart, int $day): DateTimeImmutable
    {
        $maxDay = (int) $monthStart->modify('last day of this month')->format('j');
        $day = min(max(1, $day), $maxDay);

        return $monthStart->setDate(
            (int) $monthStart->format('Y'),
            (int) $monthStart->format('n'),
            $day,
        );
    }

    private static function monthLabel(DateTimeImmutable $date): string
    {
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        return $months[(int) $date->format('n')].' '.$date->format('Y');
    }
}
