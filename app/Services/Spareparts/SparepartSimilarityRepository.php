<?php

declare(strict_types=1);

namespace App\Services\Spareparts;

use Illuminate\Support\Facades\DB;

/**
 * Searches both sides of ItemSimilar (directional master data) for sparepart
 * availability. A 2-step chain is labelled as indirect; operators should
 * verify cross-compatibility before using a chained alternative.
 */
final class SparepartSimilarityRepository
{
    /** @return array<string,int> Code => distance from requested code */
    public function alternatives(string $itemCode): array
    {
        $itemCode = SparepartCode::normalize($itemCode);
        $connection = DB::connection((string) config('product_strategy.master_connection', 'sqlsrv_apphub'));
        $table = (string) config('product_strategy.similar_table', 'dbo.ItemSimilar');
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
}
