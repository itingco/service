<?php

declare(strict_types=1);

namespace App\Services\Spareparts;

final class SparepartCode
{
    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function parent(string $code): ?string
    {
        $code = self::normalize($code);
        if (preg_match('/^(.+)-SP-([0-9]+)$/i', $code, $matches) !== 1) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : null;
    }
}
