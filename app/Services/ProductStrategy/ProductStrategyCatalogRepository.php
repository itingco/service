<?php

declare(strict_types=1);

namespace App\Services\ProductStrategy;

use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final class ProductStrategyCatalogRepository
{
    /** @return list<array<string,mixed>> */
    public function productFocusRows(string $search = ''): array
    {
        $rawRows = $this->masterConnection()
            ->table($this->focusTable())
            ->select([
                'id',
                'ItemCode',
                'ItemName',
                'ItemFocusType',
                'Updated',
            ])
            ->get();

        $missingNameCodes = [];
        foreach ($rawRows as $row) {
            $itemCode = strtoupper(trim((string) ($row->ItemCode ?? '')));
            $itemName = trim((string) ($row->ItemName ?? ''));

            if ($itemCode !== '' && $itemName === '') {
                $missingNameCodes[] = $itemCode;
            }
        }

        $fallbackNames = $this->analyticsItemNames($missingNameCodes);
        $rows = [];

        foreach ($rawRows as $row) {
            $itemCode = strtoupper(trim((string) ($row->ItemCode ?? '')));
            $itemName = trim((string) ($row->ItemName ?? ''));
            $focusCategory = strtoupper(trim((string) ($row->ItemFocusType ?? '')));

            if ($itemName === '') {
                $itemName = $fallbackNames[$itemCode] ?? '';
            }

            $rows[] = [
                'id' => (int) ($row->id ?? 0),
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'focus_category' => $focusCategory,
                'focus_rank' => $this->focusRank($focusCategory),
                'updated_at' => $this->dateValue($row->Updated ?? null),
            ];
        }

        $rows = $this->filterRows($rows, $search, [
            'item_code',
            'item_name',
            'focus_category',
        ]);

        usort($rows, static function (array $a, array $b): int {
            return [
                (int) ($a['focus_rank'] ?? 999999),
                (string) ($a['focus_category'] ?? ''),
                (string) ($a['item_code'] ?? ''),
            ] <=> [
                (int) ($b['focus_rank'] ?? 999999),
                (string) ($b['focus_category'] ?? ''),
                (string) ($b['item_code'] ?? ''),
            ];
        });

        return $rows;
    }

    public function saveProductFocus(string $itemCode, string $itemName, string $focusCategory): void
    {
        $itemCode = strtoupper(trim($itemCode));
        $itemName = trim($itemName);
        $focusCategory = strtoupper(trim($focusCategory));

        if ($itemCode === '' || $focusCategory === '') {
            return;
        }

        if ($itemName === '') {
            $itemName = $this->itemName($itemCode);
        }

        $this->masterConnection()
            ->table($this->focusTable())
            ->updateOrInsert(
                ['ItemCode' => $itemCode],
                [
                    'ItemName' => $itemName !== '' ? $itemName : null,
                    'ItemFocusType' => $focusCategory,
                    'Updated' => now(),
                ],
            );
    }

    /**
     * Replace seluruh Product Focus dengan isi Excel yang sudah dinormalisasi.
     * Nama dari Excel diprioritaskan. Jika nama kosong, nama existing di ItemFocus
     * atau cache Business Analytics dipakai sebagai fallback.
     *
     * @param list<array{item_code:string,item_name?:string,focus_category:string}> $rows
     */
    public function replaceProductFocus(array $rows): int
    {
        $codes = [];
        foreach ($rows as $row) {
            $itemCode = strtoupper(trim((string) ($row['item_code'] ?? '')));
            if ($itemCode !== '') {
                $codes[] = $itemCode;
            }
        }

        // Ambil fallback sebelum tabel dihapus agar nama existing tidak hilang saat
        // Excel sengaja membiarkan kolom item_name kosong.
        $fallbackNames = $this->itemNames(array_values(array_unique($codes)));

        return $this->masterConnection()->transaction(function () use ($rows, $fallbackNames): int {
            $table = $this->masterConnection()->table($this->focusTable());
            $table->delete();

            $payload = [];
            $updatedAt = now();

            foreach ($rows as $row) {
                $itemCode = strtoupper(trim((string) ($row['item_code'] ?? '')));
                $itemName = trim((string) ($row['item_name'] ?? ''));
                $focusCategory = strtoupper(trim((string) ($row['focus_category'] ?? '')));

                if ($itemCode === '' || $focusCategory === '') {
                    continue;
                }

                if ($itemName === '') {
                    $itemName = $fallbackNames[$itemCode] ?? '';
                }

                $payload[] = [
                    'ItemCode' => $itemCode,
                    'ItemName' => $itemName !== '' ? $itemName : null,
                    'ItemFocusType' => $focusCategory,
                    'Updated' => $updatedAt,
                ];
            }

            foreach (array_chunk($payload, 500) as $chunk) {
                $table->insert($chunk);
            }

            return count($payload);
        });
    }

    public function deleteProductFocus(int $id): void
    {
        $this->masterConnection()
            ->table($this->focusTable())
            ->where('id', $id)
            ->delete();
    }

    /** @return array<string,array{focus_category:string,focus_rank:int}> */
    public function focusMap(): array
    {
        $map = [];

        foreach ($this->productFocusRows() as $row) {
            $map[strtoupper((string) $row['item_code'])] = [
                'focus_category' => (string) $row['focus_category'],
                'focus_rank' => (int) $row['focus_rank'],
            ];
        }

        return $map;
    }

    /** @return list<array<string,mixed>> */
    public function similarItemRows(string $search = ''): array
    {
        $rawRows = $this->masterConnection()
            ->table($this->similarTable())
            ->select([
                'id',
                'ItemCode',
                'SimilarItemCode',
            ])
            ->get();

        $codes = [];
        foreach ($rawRows as $row) {
            $codes[] = strtoupper(trim((string) ($row->ItemCode ?? '')));
            $codes[] = strtoupper(trim((string) ($row->SimilarItemCode ?? '')));
        }
        $codes = array_values(array_unique(array_filter($codes)));

        // Nama yang sudah tersimpan di ItemFocus ikut dapat dipakai oleh Similar Items,
        // lalu sisanya tetap fallback ke cache analytics.
        $names = $this->itemNames($codes);
        $rows = [];

        foreach ($rawRows as $row) {
            $itemCode = strtoupper(trim((string) ($row->ItemCode ?? '')));
            $similarItemCode = strtoupper(trim((string) ($row->SimilarItemCode ?? '')));

            $rows[] = [
                'id' => (int) ($row->id ?? 0),
                'item_code' => $itemCode,
                'item_name' => $names[$itemCode] ?? '',
                'similar_item_code' => $similarItemCode,
                'similar_item_name' => $names[$similarItemCode] ?? '',
                'updated_at' => null,
            ];
        }

        $rows = $this->filterRows($rows, $search, [
            'item_code',
            'item_name',
            'similar_item_code',
            'similar_item_name',
        ]);

        usort($rows, static function (array $a, array $b): int {
            return [
                (string) ($a['item_code'] ?? ''),
                (string) ($a['similar_item_code'] ?? ''),
            ] <=> [
                (string) ($b['item_code'] ?? ''),
                (string) ($b['similar_item_code'] ?? ''),
            ];
        });

        return $rows;
    }

    public function saveSimilarItem(string $itemCode, string $itemName, string $similarItemCode, string $similarItemName): void
    {
        $itemCode = strtoupper(trim($itemCode));
        $similarItemCode = strtoupper(trim($similarItemCode));

        if ($itemCode === '' || $similarItemCode === '' || $itemCode === $similarItemCode) {
            return;
        }

        $table = $this->masterConnection()->table($this->similarTable());
        $exists = (clone $table)
            ->where('ItemCode', $itemCode)
            ->where('SimilarItemCode', $similarItemCode)
            ->exists();

        if (!$exists) {
            $table->insert([
                'ItemCode' => $itemCode,
                'SimilarItemCode' => $similarItemCode,
            ]);
        }
    }

    /**
     * Replace seluruh Similar Items dengan isi Excel yang sudah dinormalisasi.
     *
     * @param list<array{item_code:string,similar_item_code:string}> $rows
     */
    public function replaceSimilarItems(array $rows): int
    {
        return $this->masterConnection()->transaction(function () use ($rows): int {
            $table = $this->masterConnection()->table($this->similarTable());
            $table->delete();

            $payload = [];

            foreach ($rows as $row) {
                $itemCode = strtoupper(trim((string) ($row['item_code'] ?? '')));
                $similarItemCode = strtoupper(trim((string) ($row['similar_item_code'] ?? '')));

                if ($itemCode === '' || $similarItemCode === '' || $itemCode === $similarItemCode) {
                    continue;
                }

                $payload[] = [
                    'ItemCode' => $itemCode,
                    'SimilarItemCode' => $similarItemCode,
                ];
            }

            foreach (array_chunk($payload, 500) as $chunk) {
                $table->insert($chunk);
            }

            return count($payload);
        });
    }

    public function deleteSimilarItem(int $id): void
    {
        $this->masterConnection()
            ->table($this->similarTable())
            ->where('id', $id)
            ->delete();
    }

    /** @return list<array<string,mixed>> */
    public function similarFor(string $itemCode): array
    {
        $itemCode = strtoupper(trim($itemCode));
        if ($itemCode === '') {
            return [];
        }

        $rawRows = $this->masterConnection()
            ->table($this->similarTable())
            ->where('ItemCode', $itemCode)
            ->select([
                'id',
                'ItemCode',
                'SimilarItemCode',
            ])
            ->orderBy('SimilarItemCode')
            ->get();

        $codes = [$itemCode];
        foreach ($rawRows as $row) {
            $codes[] = strtoupper(trim((string) ($row->SimilarItemCode ?? '')));
        }

        $names = $this->itemNames(array_values(array_unique(array_filter($codes))));

        return $rawRows
            ->map(static function (object $row) use ($names): array {
                $code = strtoupper(trim((string) ($row->ItemCode ?? '')));
                $similarCode = strtoupper(trim((string) ($row->SimilarItemCode ?? '')));

                return [
                    'id' => (int) ($row->id ?? 0),
                    'item_code' => $code,
                    'item_name' => $names[$code] ?? '',
                    'similar_item_code' => $similarCode,
                    'similar_item_name' => $names[$similarCode] ?? '',
                    'updated_at' => null,
                ];
            })
            ->all();
    }

    public function itemName(string $itemCode): string
    {
        $itemCode = strtoupper(trim($itemCode));
        if ($itemCode === '') {
            return '';
        }

        $names = $this->itemNames([$itemCode]);

        return $names[$itemCode] ?? '';
    }

    private function masterConnection(): ConnectionInterface
    {
        return DB::connection((string) config('product_strategy.master_connection', 'sqlsrv_apphub'));
    }

    private function focusTable(): string
    {
        return (string) config('product_strategy.focus_table', 'dbo.ItemFocus');
    }

    private function similarTable(): string
    {
        return (string) config('product_strategy.similar_table', 'dbo.ItemSimmilar');
    }

    /**
     * Ambil nama item dengan prioritas:
     * 1. DB_AppHub.dbo.ItemFocus.ItemName
     * 2. cache Business Analytics
     *
     * @param list<string> $itemCodes
     * @return array<string,string>
     */
    private function itemNames(array $itemCodes): array
    {
        $itemCodes = $this->normalizeItemCodes($itemCodes);
        if ($itemCodes === []) {
            return [];
        }

        $result = [];

        foreach (array_chunk($itemCodes, 500) as $chunk) {
            $rows = $this->masterConnection()
                ->table($this->focusTable())
                ->whereIn('ItemCode', $chunk)
                ->whereNotNull('ItemName')
                ->where('ItemName', '<>', '')
                ->select(['ItemCode', 'ItemName'])
                ->get();

            foreach ($rows as $row) {
                $code = strtoupper(trim((string) ($row->ItemCode ?? '')));
                $name = trim((string) ($row->ItemName ?? ''));

                if ($code !== '' && $name !== '') {
                    $result[$code] = $name;
                }
            }
        }

        $missingCodes = array_values(array_filter(
            $itemCodes,
            static fn (string $code): bool => !isset($result[$code]),
        ));

        if ($missingCodes !== []) {
            foreach ($this->analyticsItemNames($missingCodes) as $code => $name) {
                if (!isset($result[$code])) {
                    $result[$code] = $name;
                }
            }
        }

        return $result;
    }

    /** @param list<string> $itemCodes @return array<string,string> */
    private function analyticsItemNames(array $itemCodes): array
    {
        $itemCodes = $this->normalizeItemCodes($itemCodes);
        if ($itemCodes === []) {
            return [];
        }

        $connection = DB::connection((string) config('product_strategy.analytics_connection', 'sqlite_targets'));
        $table = (string) config('product_strategy.analytics_table', 'business_analytics_monthly_sales');
        $result = [];

        foreach (array_chunk($itemCodes, 500) as $chunk) {
            $rows = $connection
                ->table($table)
                ->whereIn('item_code', $chunk)
                ->where('item_name', '<>', '')
                ->selectRaw('item_code, MAX(item_name) AS item_name')
                ->groupBy('item_code')
                ->get();

            foreach ($rows as $row) {
                $code = strtoupper(trim((string) ($row->item_code ?? '')));
                $name = trim((string) ($row->item_name ?? ''));

                if ($code !== '' && $name !== '') {
                    $result[$code] = $name;
                }
            }
        }

        return $result;
    }

    /** @param list<string> $itemCodes @return list<string> */
    private function normalizeItemCodes(array $itemCodes): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            $itemCodes,
        ))));
    }

    private function focusRank(string $focusCategory): int
    {
        if (preg_match('/(\d+)/', $focusCategory, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return 999999;
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $columns @return list<array<string,mixed>> */
    private function filterRows(array $rows, string $search, array $columns): array
    {
        $search = strtoupper(trim($search));
        if ($search === '') {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($search, $columns): bool {
            foreach ($columns as $column) {
                if (str_contains(strtoupper((string) ($row[$column] ?? '')), $search)) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }
}
