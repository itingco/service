<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\SalesDataSource;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/** Cache original SQL source rows without changing business math or filtering. */
final class CachedSalesQuery
{
    public static function fetch(SalesDataSource $dataSource, string $source, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $source = strtoupper(trim($source));
        if (!in_array($source, ['INGCO', 'SMI'], true)) {
            return $dataSource->fetch($source, $start, $end);
        }
        $ttl = $end->format('Y-m-d') >= now()->format('Y-m-d') ? 120 : 3600;
        $key = 'sql-sales:v1:'.$source.':'.$start->format('Ymd').':'.$end->format('Ymd');
        return Cache::remember($key, $ttl, static fn (): array => $dataSource->fetch($source, $start, $end));
    }
}
