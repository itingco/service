<?php

declare(strict_types=1);

namespace App\Services\Spareparts;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Search both directions of the Similar Items master table.
 * Supports the existing DB_AppHub table spelling (ItemSimmilar)
 * as well as the historical ItemSimilar spelling.
 */
final class SparepartSimilarityRepository
{
    /** @return array<string,int> Code => distance from requested code */
    public function alternatives(string $itemCode): array
    {
        $itemCode = SparepartCode::normalize($itemCode);
        $connection = DB::connection((string) config('product_strategy.master_connection', 'sqlsrv_apphub'));
        $table = $this->resolveTable($connection);
        $limit = max(1, min(200, (int) config('sparepart_search.max_alternatives', 80)));
        $maxDepth = max(1, min(3, (int) config('sparepart_search.similar_depth', 2)));
        $visited = [$itemCode => 0];
        $frontier = [$itemCode];

        for ($depth = 1; $depth <= $maxDepth && $frontier !== [] && count($visited) <= $limit; $depth++) {
            $next = [];
            foreach (array_chunk($frontier, 80) as $chunk) {
                $rows = $connection->table($table)
                    ->where(function ($query) use ($chunk): void {
                        $query->whereIn('ItemCode', $chunk)
                            ->orWhereIn('SimilarItemCode', $chunk);
                    })
                    ->select(['ItemCode', 'SimilarItemCode'])
                    ->get();

                foreach ($rows as $row) {
                    $left = SparepartCode::normalize((string) ($row->ItemCode ?? ''));
                    $right = SparepartCode::normalize((string) ($row->SimilarItemCode ?? ''));
                    foreach ([[$left, $right], [$right, $left]] as [$from, $to]) {
                        if ($to === '' || !in_array($from, $chunk, true) || isset($visited[$to])) {
                            continue;
                        }
                        if (count($visited) > $limit) {
                            break;
                        }
                        $visited[$to] = $depth;
                        $next[$to] = true;
                    }
                }
            }
            $frontier = array_keys($next);
        }

        unset($visited[$itemCode]);
        ksort($visited, SORT_NATURAL);

        return $visited;
    }

    /**
     * Resolve only known, permitted schema/table identifiers. Do not build a SQL
     * identifier from unvalidated environment values. Fail closed on DB errors.
     */
    private function resolveTable(ConnectionInterface $connection): string
    {
        $configured = trim((string) config('product_strategy.similar_table', 'dbo.ItemSimmilar'));
        $candidates = array_values(array_unique([
            $configured,
            'dbo.ItemSimmilar',
            'dbo.ItemSimilar',
        ]));

        foreach ($candidates as $candidate) {
            if (preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\.)?[A-Za-z_][A-Za-z0-9_]*$/D', $candidate) !== 1) {
                continue;
            }

            $parts = explode('.', $candidate, 2);
            $schema = count($parts) === 2 ? $parts[0] : 'dbo';
            $name = count($parts) === 2 ? $parts[1] : $parts[0];

            // sys.tables inspection also distinguishes a missing table from a
            // connection/permission failure, which must never be hidden.
            $found = $connection->selectOne(
                'SELECT TOP (1) 1 AS Found FROM sys.tables AS t '
                .'INNER JOIN sys.schemas AS s ON s.schema_id = t.schema_id '
                .'WHERE s.name = ? AND t.name = ?',
                [$schema, $name],
            );

            if ($found !== null) {
                return $schema.'.'.$name;
            }
        }

        throw new RuntimeException(
            'Tabel Similar Items tidak ditemukan pada koneksi '
            .(string) config('product_strategy.master_connection', 'sqlsrv_apphub')
            .'. Diperiksa: '.implode(', ', $candidates)
        );
    }
}
