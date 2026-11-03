<?php

declare(strict_types=1);

namespace App\Services\ServiceQuotations;

use RuntimeException;

/** Mirrors an existing ERP document numbering series per Service Type. */
final class ServiceErpNumbering
{
    /** @return array{prefix:string,date:string,width:int} */
    public static function parse(string $lastNumber, string $serviceType): array
    {
        $serviceType = strtoupper(trim($serviceType));
        $pattern = '~^([A-Z0-9_-]+)/'.preg_quote($serviceType, '~').'/([0-9]{6})/([0-9]+)$~iD';
        if (preg_match($pattern, trim($lastNumber), $m) !== 1) {
            throw new RuntimeException('Format nomor ERP untuk Service Type '.$serviceType.' belum dapat dikenali: '.$lastNumber.'. Pengiriman dibatalkan.');
        }
        return ['prefix' => strtoupper($m[1]), 'date' => $m[2], 'width' => strlen($m[3])];
    }

    /** Date in WIB, used only if the ERP's operational rule requires a fresh date. */
    public static function dateForSend(?\DateTimeInterface $sendTime = null): string
    {
        $local = $sendTime === null
            ? new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta'))
            : \DateTimeImmutable::createFromInterface($sendTime)->setTimezone(new \DateTimeZone('Asia/Jakarta'));
        return $local->format('ymd');
    }

    /**
     * ERP number date modes:
     *  - last_erp  : reuse the YYMMDD part from the latest ERP ServiceNumber.
     *  - send_date : use date of the new transmission in Asia/Jakarta.
     *
     * This does not affect ReceivedDate or CreateDate in the ERP document.
     * @param array{prefix:string,date:string,width:int} $lastFormat
     */
    public static function numberDate(array $lastFormat, string $mode, ?\DateTimeInterface $sendTime = null): string
    {
        return match ($mode) {
            'last_erp' => $lastFormat['date'],
            'send_date' => self::dateForSend($sendTime),
            default => throw new RuntimeException('SERVICE_ERP_NUMBER_DATE_MODE tidak dikenal. Gunakan last_erp atau send_date.'),
        };
    }

    /** Exact prefix for one Service Type and one calendar month (YYMM). */
    public static function monthPrefix(string $prefix, string $serviceType, string $dateYYMMDD): string
    {
        if (preg_match('/^[0-9]{6}$/D', $dateYYMMDD) !== 1
            || preg_match('/^[A-Z0-9_-]+$/Di', $prefix) !== 1
            || preg_match('/^[A-Z0-9-]+$/Di', $serviceType) !== 1) {
            throw new RuntimeException('Parameter periode penomoran Service ERP tidak valid.');
        }

        return strtoupper($prefix).'/'.strtoupper($serviceType).'/'.substr($dateYYMMDD, 0, 4);
    }

    public static function nextCounter(int ...$observedCounters): int
    {
        foreach ($observedCounters as $counter) {
            if ($counter < 0) throw new RuntimeException('Counter ERP tidak valid.');
        }
        return max([0, ...$observedCounters]) + 1;
    }

    public static function make(string $prefix, string $serviceType, string $dateYYMMDD, int $counter, int $width): string
    {
        if ($counter < 1 || $width < 1 || $width > 12 || preg_match('/^[0-9]{6}$/D', $dateYYMMDD) !== 1
            || preg_match('/^[A-Z0-9_-]+$/Di', $prefix) !== 1
            || preg_match('/^[A-Z0-9-]+$/Di', $serviceType) !== 1) {
            throw new RuntimeException('Parameter penomoran Service ERP tidak valid.');
        }
        return strtoupper($prefix).'/'.strtoupper($serviceType).'/'.$dateYYMMDD.'/'.str_pad((string) $counter, $width, '0', STR_PAD_LEFT);
    }
}
