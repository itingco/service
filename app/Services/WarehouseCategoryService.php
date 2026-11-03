<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class WarehouseCategoryService
{
    private function table(string $name): \Illuminate\Database\Query\Builder
    {
        return DB::connection('sqlsrv_apphub')->table($name);
    }

    /**
     * Optional master metadata. Do not affect the legacy ERP stock calculation
     * if DB_AppHub has not been initialized yet.
     */
    public function available(): bool
    {
        try {
            return Schema::connection('sqlsrv_apphub')->hasTable('StockWarehouseCategories')
                && Schema::connection('sqlsrv_apphub')->hasTable('StockWarehouseCategoryMembers');
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }

    public function categories(): array
    {
        if (!$this->available()) {
            return [];
        }
        return $this->table('dbo.StockWarehouseCategories')->orderBy('CategoryName')->get()->map(fn ($r) => (array)$r)->all();
    }

    public function members(): array
    {
        if (!$this->available()) {
            return [];
        }
        return $this->table('dbo.StockWarehouseCategoryMembers')->get()->map(fn ($r) => (array)$r)->all();
    }

    public function warehouses(): array
    {
        $result = [];
        foreach (['INGCO' => 'sqlsrv_ingco', 'SMI' => 'sqlsrv_smi'] as $source => $connection) {
            foreach (DB::connection($connection)->select('SELECT WarehouseID, WarehouseCode, Name FROM dbo.IC_Warehouses ORDER BY WarehouseCode') as $r) {
                $result[] = [
                    'key' => $source.'|'.(int)$r->WarehouseID,
                    'source' => $source,
                    'id' => (int)$r->WarehouseID,
                    'name' => trim((string)$r->WarehouseCode).' - '.trim((string)$r->Name),
                ];
            }
        }
        return $result;
    }

    public function create(string $name, array $keys): void
    {
        DB::connection('sqlsrv_apphub')->transaction(function () use ($name, $keys): void {
            $id = $this->table('dbo.StockWarehouseCategories')->insertGetId(['CategoryName' => $name], 'CategoryID');
            $this->syncMembers((int)$id, $keys);
        });
    }

    public function update(int $id, string $name, array $keys): void
    {
        DB::connection('sqlsrv_apphub')->transaction(function () use ($id, $name, $keys): void {
            $this->table('dbo.StockWarehouseCategories')->where('CategoryID', $id)->update(['CategoryName' => $name]);
            $this->table('dbo.StockWarehouseCategoryMembers')->where('CategoryID', $id)->delete();
            $this->syncMembers($id, $keys);
        });
    }

    private function syncMembers(int $id, array $keys): void
    {
        $rows = [];
        foreach (array_unique($keys) as $key) {
            if (!is_string($key) || !preg_match('/^(INGCO|SMI)\|([1-9][0-9]*)$/D', $key, $m)) continue;
            $rows[] = ['CategoryID' => $id, 'SourceCode' => $m[1], 'WarehouseID' => (int)$m[2]];
        }
        if ($rows !== []) $this->table('dbo.StockWarehouseCategoryMembers')->insert($rows);
    }

    public function delete(int $id): void
    {
        $this->table('dbo.StockWarehouseCategories')->where('CategoryID', $id)->delete();
    }

    public function filterRows(array $rows, array $categoryIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $categoryIds), fn($n) => $n > 0)));
        if ($ids === [] || !$this->available()) return $rows;
        $allowed = [];
        foreach ($this->table('dbo.StockWarehouseCategoryMembers')->whereIn('CategoryID', $ids)->get() as $member) {
            $allowed[strtoupper((string)$member->SourceCode).'|'.(int)$member->WarehouseID] = true;
        }
        return array_values(array_filter($rows, static fn (array $r) => isset($allowed[strtoupper((string)($r['physical_source'] ?? $r['source'] ?? '')).'|'.(int)($r['warehouse_id'] ?? 0)])));
    }
}
