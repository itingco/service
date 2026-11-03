<?php

declare(strict_types=1);

namespace App\Services\ProductStrategy;

use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Pagination SQL Server pada master DB_AppHub, bukan get() seluruh tabel.
 * Tidak mengubah mekanisme import/export dan service pencarian sparepart.
 */
final class ProductStrategyMasterPagination
{
    public function productFocus(string $search, int $perPage): LengthAwarePaginator
    {
        $table = (string) config('product_strategy.focus_table', 'dbo.ItemFocus');
        $query = $this->master()->table($table);
        $search = trim($search);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(static function (Builder $builder) use ($like): void {
                $builder->where('ItemCode', 'like', $like)
                    ->orWhere('ItemName', 'like', $like)
                    ->orWhere('ItemFocusType', 'like', $like);
            });
        }

        // Urutan deterministik (termasuk jika nama/kategori duplikat).
        $page = $query->orderBy('ItemFocusType')
            ->orderBy('ItemCode')
            ->orderBy('id')
            ->paginate($this->pageSize($perPage), ['id', 'ItemCode', 'ItemName', 'ItemFocusType', 'Updated']);

        $records = $page->items();
        $missing = [];
        foreach ($records as $record) {
            if (trim((string) ($record->ItemName ?? '')) === '') {
                $missing[] = $this->code((string) ($record->ItemCode ?? ''));
            }
        }
        $fallback = $this->analyticsNames($missing);

        return $page->through(function (object $record) use ($fallback): array {
            $code = $this->code((string) ($record->ItemCode ?? ''));
            $category = strtoupper(trim((string) ($record->ItemFocusType ?? '')));
            $rank = preg_match('/(\d+)/', $category, $match) === 1 ? max(1, (int) $match[1]) : 999999;
            $date = $record->Updated ?? null;
            return [
                'id' => (int) ($record->id ?? 0),
                'item_code' => $code,
                'item_name' => trim((string) ($record->ItemName ?? '')) ?: ($fallback[$code] ?? ''),
                'focus_category' => $category,
                'focus_rank' => $rank,
                'updated_at' => $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : ($date === null ? null : (string) $date),
            ];
        });
    }

    public function similarItems(string $search, int $perPage): LengthAwarePaginator
    {
        $similarTable = (string) config('product_strategy.similar_table', 'dbo.ItemSimilar');
        $focusTable = (string) config('product_strategy.focus_table', 'dbo.ItemFocus');
        $query = $this->master()->table($similarTable.' as sim');
        $search = trim($search);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(static function (Builder $builder) use ($like, $focusTable): void {
                $builder->where('sim.ItemCode', 'like', $like)
                    ->orWhere('sim.SimilarItemCode', 'like', $like)
                    ->orWhereExists(static function (Builder $sub) use ($focusTable, $like): void {
                        $sub->selectRaw('1')
                            ->from($focusTable.' as focused')
                            ->where('focused.ItemName', 'like', $like)
                            ->where(static function (Builder $match): void {
                                $match->whereColumn('focused.ItemCode', 'sim.ItemCode')
                                    ->orWhereColumn('focused.ItemCode', 'sim.SimilarItemCode');
                            });
                    });
            });
        }

        $page = $query->orderBy('sim.ItemCode')
            ->orderBy('sim.SimilarItemCode')
            ->orderBy('sim.id')
            ->paginate($this->pageSize($perPage), [
                'sim.id as id', 'sim.ItemCode as ItemCode', 'sim.SimilarItemCode as SimilarItemCode',
            ]);

        $codes = [];
        foreach ($page->items() as $record) {
            $codes[] = $this->code((string) ($record->ItemCode ?? ''));
            $codes[] = $this->code((string) ($record->SimilarItemCode ?? ''));
        }
        $names = $this->names($codes);

        return $page->through(function (object $record) use ($names): array {
            $code = $this->code((string) ($record->ItemCode ?? ''));
            $similar = $this->code((string) ($record->SimilarItemCode ?? ''));
            return [
                'id' => (int) ($record->id ?? 0),
                'item_code' => $code,
                'item_name' => $names[$code] ?? '',
                'similar_item_code' => $similar,
                'similar_item_name' => $names[$similar] ?? '',
                'updated_at' => null,
            ];
        });
    }

    private function names(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map($this->code(...), $codes))));
        if ($codes === []) {
            return [];
        }
        $focusTable = (string) config('product_strategy.focus_table', 'dbo.ItemFocus');
        $names = [];
        foreach (array_chunk($codes, 400) as $chunk) {
            $rows = $this->master()->table($focusTable)
                ->whereIn('ItemCode', $chunk)
                ->whereNotNull('ItemName')
                ->where('ItemName', '<>', '')
                ->get(['ItemCode', 'ItemName']);
            foreach ($rows as $record) {
                $code = $this->code((string) ($record->ItemCode ?? ''));
                $name = trim((string) ($record->ItemName ?? ''));
                if ($code !== '' && $name !== '') {
                    $names[$code] = $name;
                }
            }
        }
        $missing = array_values(array_diff($codes, array_keys($names)));
        return $names + $this->analyticsNames($missing);
    }

    private function analyticsNames(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map($this->code(...), $codes))));
        if ($codes === []) {
            return [];
        }
        $db = DB::connection((string) config('product_strategy.analytics_connection', 'sqlite_targets'));
        $table = (string) config('product_strategy.analytics_table', 'business_analytics_monthly_sales');
        $names = [];
        foreach (array_chunk($codes, 400) as $chunk) {
            $rows = $db->table($table)
                ->whereIn('item_code', $chunk)
                ->where('item_name', '<>', '')
                ->selectRaw('item_code, MAX(item_name) AS item_name')
                ->groupBy('item_code')
                ->get();
            foreach ($rows as $record) {
                $code = $this->code((string) ($record->item_code ?? ''));
                $name = trim((string) ($record->item_name ?? ''));
                if ($code !== '' && $name !== '') {
                    $names[$code] = $name;
                }
            }
        }
        return $names;
    }

    private function master(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection((string) config('product_strategy.master_connection', 'sqlsrv_apphub'));
    }

    private function pageSize(int $size): int
    {
        return in_array($size, [25, 50, 100], true) ? $size : 50;
    }

    private function code(string $code): string
    {
        return strtoupper(trim($code));
    }
}
