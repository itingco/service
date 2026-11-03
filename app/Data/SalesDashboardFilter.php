<?php

declare(strict_types=1);

namespace App\Data;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;

final class SalesDashboardFilter
{
    private const DATABASES = ['all', 'ingco', 'wadfow', 'smi'];
    private const ITEM_STATUSES = ['all', 'normal', 'discontinued', 'discontinued_sale', 'clearance', 'reject'];

    public function __construct(
        public readonly string $database,
        public readonly string $itemStatus,
        public readonly DateTimeImmutable $selectedDate,
        public readonly DateTimeImmutable $queryStartDate,
        public readonly DateTimeImmutable $currentStartDate,
        public readonly DateTimeImmutable $currentEndDate,
        public readonly DateTimeImmutable $previousStartDate,
        public readonly DateTimeImmutable $previousEndDate,
        public readonly DateTimeImmutable $previousDayDate,
        public readonly int $totalSalesDays,
        public readonly int $elapsedDays,
        public readonly int $remainingSalesDays,
    ) {
    }

    public static function from(string $database, string $endDate, string $itemStatus = 'all'): self
    {
        $database = strtolower(trim($database));
        if (!in_array($database, self::DATABASES, true)) {
            throw new InvalidArgumentException('Database harus all, ingco, wadfow, atau smi.');
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
        $previousStart = $currentStart->modify('-1 month');
        $previousMonthEnd = $previousStart->modify('last day of this month');
        $comparableDay = min((int) $selectedDate->format('j'), (int) $previousMonthEnd->format('j'));
        $previousEnd = $previousStart->setDate(
            (int) $previousStart->format('Y'),
            (int) $previousStart->format('n'),
            $comparableDay,
        );

        $totalSalesDays = self::countNonSundayDays(
            $currentStart,
            $currentStart->modify('last day of this month'),
        );
        $elapsedDays = max(1, (int) $selectedDate->format('j') - 1);
        $remainingSalesDays = max(1, $totalSalesDays - $elapsedDays);

        return new self(
            database: $database,
            itemStatus: $itemStatus,
            selectedDate: $selectedDate,
            queryStartDate: $previousStart,
            currentStartDate: $currentStart,
            currentEndDate: $selectedDate,
            previousStartDate: $previousStart,
            previousEndDate: $previousEnd,
            previousDayDate: $selectedDate->modify('-1 day'),
            totalSalesDays: $totalSalesDays,
            elapsedDays: $elapsedDays,
            remainingSalesDays: $remainingSalesDays,
        );
    }

    /** @return list<string> */
    public function sources(): array
    {
        return match ($this->database) {
            'ingco' => ['INGCO'],
            'smi', 'wadfow' => ['SMI'],
            default => ['INGCO', 'SMI'],
        };
    }

    public function databaseLabel(): string
    {
        return match ($this->database) {
            'ingco' => 'INGCO',
            'smi' => 'SMI',
            'wadfow' => 'WADFOW',
            default => 'INGCO + WADFOW + SMI',
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

    public function matchesItemStatus(bool $discontinued, bool $clearance): bool
    {
        $chosen = explode(',', $this->itemStatus);
        if (in_array('all', $chosen, true)) return true;
        $status = $clearance ? 'clearance' : ($discontinued ? 'discontinued' : 'normal');
        return in_array($status, $chosen, true) || ($status === 'clearance' && in_array('reject', $chosen, true));
    }

    public function matchesCategory(string $status): bool
    {
        $chosen = explode(',', $this->itemStatus);
        if (in_array('all', $chosen, true)) return true;
        $status = match (strtoupper(trim($status))) {
            'NORMAL' => 'normal',
            'DISCONTINUE', 'DISCONTINUED' => 'discontinued',
            'DISCONTINUE + SALE' => 'discontinued_sale',
            'ITEM REJECT' => 'clearance',
            default => '',
        };
        return in_array($status, $chosen, true) || ($status === 'clearance' && in_array('reject', $chosen, true));
    }

    public function matchesBusiness(string $business): bool
    {
        return $this->database === 'all' || strtoupper($this->database) === strtoupper($business);
    }

    private static function countNonSundayDays(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $endExclusive = $end->modify('+1 day');
        $period = new DatePeriod($start, new DateInterval('P1D'), $endExclusive);
        $count = 0;

        foreach ($period as $date) {
            if ((int) $date->format('w') !== 0) {
                $count++;
            }
        }

        return max(1, $count);
    }
}
