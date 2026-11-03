<?php

declare(strict_types=1);

namespace App\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final class SalesByItemFilter
{
    private const DATABASES = ['all', 'ingco', 'smi'];

    public function __construct(
        public readonly string $database,
        public readonly DateTimeImmutable $startDate,
        public readonly DateTimeImmutable $endDate,
    ) {
    }

    public static function from(string $database, string $endDate): self
    {
        $database = strtolower(trim($database));
        if (!in_array($database, self::DATABASES, true)) {
            throw new InvalidArgumentException('Database harus all, ingco, atau smi.');
        }

        $parsedEndDate = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $parsedEndDate === false ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException('Tanggal akhir harus menggunakan format YYYY-MM-DD.');
        }

        $startDate = $parsedEndDate->modify('first day of this month');

        return new self($database, $startDate, $parsedEndDate);
    }

    /** @return list<string> */
    public function sources(): array
    {
        return match ($this->database) {
            'ingco' => ['INGCO'],
            'smi' => ['SMI'],
            default => ['INGCO', 'SMI'],
        };
    }

    public function databaseLabel(): string
    {
        return match ($this->database) {
            'ingco' => 'INGCO',
            'smi' => 'SMI',
            default => 'SEMUA DATABASE',
        };
    }
}
