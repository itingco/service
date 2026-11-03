<?php

declare(strict_types=1);

namespace App\Data\SalesKpi;

use DateTimeImmutable;
use InvalidArgumentException;

final class SalesKpiFilter
{
    private const BOOLEAN_FILTERS = ['all', 'yes', 'no'];
    private const GRADES = ['ALL', 'A', 'B', 'C', 'D', 'E', 'N', 'BLANK'];

    public readonly string $database;

    public function __construct(
        public readonly DateTimeImmutable $selectedDate,
        public readonly ?string $line,
        public readonly string $discontinue,
        public readonly string $clearance,
        public readonly string $grade,
        public readonly string $search,
    ) {
        $this->database = 'combined';
    }

    public static function from(
        string $endDate,
        ?string $line = null,
        string $discontinue = 'all',
        string $clearance = 'all',
        string $grade = 'all',
        string $search = '',
    ): self {
        $discontinue = strtolower(trim($discontinue));
        $clearance = strtolower(trim($clearance));
        $grade = strtoupper(trim($grade));
        $line = trim((string) $line);

        $selectedDate = DateTimeImmutable::createFromFormat('!Y-m-d', trim($endDate));
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (
            $selectedDate === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $selectedDate->format('Y-m-d') !== trim($endDate)
        ) {
            throw new InvalidArgumentException('Tanggal akhir harus berformat Y-m-d.');
        }

        if (!in_array($discontinue, self::BOOLEAN_FILTERS, true)) {
            throw new InvalidArgumentException('Filter discontinue tidak valid.');
        }
        if (!in_array($clearance, self::BOOLEAN_FILTERS, true)) {
            throw new InvalidArgumentException('Filter Item Reject tidak valid.');
        }
        if (!in_array($grade, self::GRADES, true)) {
            throw new InvalidArgumentException('Filter grade tidak valid.');
        }

        return new self(
            selectedDate: $selectedDate,
            line: $line === '' ? null : $line,
            discontinue: $discontinue,
            clearance: $clearance,
            grade: $grade,
            search: trim($search),
        );
    }

    public function lineKey(): ?string
    {
        if ($this->line === null) {
            return null;
        }

        $line = trim($this->line);

        return strtoupper(preg_replace('/\s+/', ' ', $line) ?? $line);
    }

    public function isCombined(): bool
    {
        return true;
    }

    /** @return list<string> */
    public function sources(): array
    {
        return ['INGCO', 'SMI'];
    }

    /** @return list<string> */
    public function databaseKeys(): array
    {
        return ['AS_INGCO', 'AS_SMI'];
    }

    public function databaseKey(): string
    {
        return 'GABUNGAN';
    }

    public function databaseLabel(): string
    {
        return 'AS_INGCO + AS_SMI';
    }

    /** @return array<string,string> */
    public function query(): array
    {
        return [
            'end_date' => $this->selectedDate->format('Y-m-d'),
            'line' => $this->line ?? '',
            'discontinue' => $this->discontinue,
            'clearance' => $this->clearance,
            'grade' => $this->grade,
            'search' => $this->search,
        ];
    }
}
