<?php

declare(strict_types=1);

namespace App\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final class StockAgingFilter
{
    private const ITEM_STATUSES = ['all', 'normal', 'discontinued', 'discontinued_sale', 'clearance', 'reject'];
    private const DATABASES = ['all', 'ingco', 'wadfow', 'smi'];

    public function __construct(
        public readonly DateTimeImmutable $selectedDate,
        public readonly DateTimeImmutable $mtdStartDate,
        public readonly string $itemStatus,
        public readonly string $database,
    ) {
    }

    public static function from(string $date, string $itemStatus = 'all', string $database = 'all'): self
    {
        $selectedDate = DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
        $errors = DateTimeImmutable::getLastErrors();
        if ($selectedDate === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Tanggal laporan harus menggunakan format YYYY-MM-DD.');
        }

        $itemStatus = strtolower(trim($itemStatus));
        if (array_diff(explode(',', $itemStatus), self::ITEM_STATUSES) !== [] || $itemStatus === '') {
            throw new InvalidArgumentException('Status item tidak valid.');
        }

        $database = strtolower(trim($database));
        if (!in_array($database, self::DATABASES, true)) {
            throw new InvalidArgumentException('Pilihan database tidak valid.');
        }

        return new self(
            selectedDate: $selectedDate,
            mtdStartDate: $selectedDate->modify('first day of this month'),
            itemStatus: $itemStatus,
            database: $database,
        );
    }

    /**
     * Physical SQL Server sources that must be queried.
     * WADFOW and SMI both live in AS_SMI and are split after the rows are read.
     *
     * @return list<string>
     */
    public function sources(): array
    {
        return match ($this->database) {
            'ingco' => ['INGCO'],
            'wadfow', 'smi' => ['SMI'],
            default => ['INGCO', 'SMI'],
        };
    }

    public function matchesDatabase(string $logicalSource): bool
    {
        $logicalSource = strtolower(trim($logicalSource));

        return $this->database === 'all' || $this->database === $logicalSource;
    }

    public function matchesItemStatus(bool $discontinued, bool $hasDiscountRule, bool $itemReject): bool
    {
        $chosen = explode(',', $this->itemStatus);
        if (in_array('all', $chosen, true)) return true;
        $status = $itemReject ? 'reject' : ($discontinued ? ($hasDiscountRule ? 'discontinued_sale' : 'discontinued') : 'normal');
        return in_array($status, $chosen, true) || ($status === 'reject' && in_array('clearance', $chosen, true));
    }

    public function itemStatusLabel(): string
    {
        // MULTISTATUS-FILTER
        $selected = explode(',', $this->itemStatus);
        if (in_array('all', $selected, true)) return 'Semua Status';
        $labels = ['normal'=>'Normal','discontinued'=>'Discontinue','discontinued_sale'=>'Discontinue + Sale','clearance'=>'Item Reject','reject'=>'Item Reject'];
        return implode(' + ', array_values(array_unique(array_map(static fn (string $key): string => $labels[$key] ?? $key, $selected))));
    }

    public function databaseLabel(): string
    {
        return match ($this->database) {
            'ingco' => 'INGCO',
            'wadfow' => 'WADFOW',
            'smi' => 'SMI',
            default => 'INGCO + WADFOW + SMI',
        };
    }

    /** @return array{date:string,item_status:string,database:string} */
    public function query(): array
    {
        return [
            'date' => $this->selectedDate->format('Y-m-d'),
            'item_status' => $this->itemStatus,
            'database' => $this->database,
        ];
    }
}
