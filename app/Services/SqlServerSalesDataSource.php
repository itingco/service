<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SalesDataSource;
use App\Exceptions\SalesDataException;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SqlServerSalesDataSource implements SalesDataSource
{
    private const CONNECTIONS = [
        'INGCO' => 'sqlsrv_ingco',
        'SMI' => 'sqlsrv_smi',
    ];

    public function fetch(string $source, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $source = strtoupper(trim($source));
        $connection = self::CONNECTIONS[$source] ?? null;

        if ($connection === null) {
            throw new SalesDataException($source, "Sumber database {$source} tidak dikenal.");
        }

        try {
            /** @var list<object> $rows */
            $rows = DB::connection($connection)->select(
                'SELECT * FROM dbo.fn_PenjualanLini(?, ?)',
                [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]
            );

            return $rows;
        } catch (Throwable $e) {
            throw new SalesDataException(
                $source,
                "Gagal mengambil data {$source}: {$e->getMessage()}",
                $e
            );
        }
    }
}
