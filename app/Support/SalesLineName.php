<?php

declare(strict_types=1);

namespace App\Support;

final class SalesLineName
{
    public const OTHERS = 'LAINNYA';

    public static function normalize(string $line): string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $line) ?? $line));
        $compact = preg_replace('/[\s\-_.]+/', '', $normalized) ?? $normalized;

        if (in_array($compact, ['LAINLAIN', 'LAINNYA'], true)) {
            return self::OTHERS;
        }

        return $normalized;
    }
}
