<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\StockAgingDataSource;
use App\Exceptions\StockAgingDataException;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SqlServerStockAgingDataSource implements StockAgingDataSource
{
    private const CONNECTIONS = [
        'INGCO' => 'sqlsrv_ingco',
        'SMI' => 'sqlsrv_smi',
    ];

    public function fetch(string $source, DateTimeImmutable $date): array
    {
        $source = strtoupper(trim($source));
        $connectionName = self::CONNECTIONS[$source] ?? null;
        if ($connectionName === null) {
            throw StockAgingDataException::forSource($source, "Sumber database {$source} tidak dikenal.");
        }

        $procedure = (string) config('stock_aging.procedure', 'USP_Dashboard_SisaStokWarehouse');
        if (preg_match('/^[A-Za-z0-9_]+$/', $procedure) !== 1) {
            throw StockAgingDataException::forSource($source, 'Nama stored procedure Stock Aging tidak valid.');
        }

        try {
            $connection = DB::connection($connectionName);

            /** @var list<object> $rows */
            $rows = $connection->select(
                "EXEC dbo.{$procedure} @PerDate = ?",
                [$date->format('Y-m-d')],
            );

            return $this->enrichRows($connection, $rows, $source);
        } catch (Throwable $exception) {
            throw StockAgingDataException::forSource(
                $source,
                "Gagal mengambil sisa stok {$source}: {$exception->getMessage()}",
                $exception,
            );
        }
    }

    /**
     * Adds Brand and HasDiscountRule without requiring the existing stock procedure to be changed.
     * This keeps the patch compatible with the current AS_INGCO / AS_SMI database objects.
     *
     * @param list<object|array<string,mixed>> $rows
     * @return list<object|array<string,mixed>>
     */
    private function enrichRows(ConnectionInterface $connection, array $rows, string $source): array
    {
        if ($rows === []) {
            return $rows;
        }

        $itemIds = [];
        foreach ($rows as $row) {
            $id = (int) $this->rowValue($row, 'ItemID');
            if ($id > 0) {
                $itemIds[$id] = $id;
            }
        }

        if ($itemIds === []) {
            return $rows;
        }

        $ids = array_values($itemIds);
        $discountItems = $this->loadDiscountRuleItems($connection, $ids);
        $brands = $source === 'SMI' ? $this->loadBrands($connection, $ids) : [];

        foreach ($rows as $index => $row) {
            $itemId = (int) $this->rowValue($row, 'ItemID');
            $brand = trim((string) ($brands[$itemId] ?? ''));
            $hasDiscountRule = isset($discountItems[$itemId]) ? 1 : 0;

            if (is_array($row)) {
                $row['Brand'] = $brand;
                $row['HasDiscountRule'] = $hasDiscountRule;
                $row['PhysicalSource'] = $source;
                $rows[$index] = $row;
            } else {
                $row->Brand = $brand;
                $row->HasDiscountRule = $hasDiscountRule;
                $row->PhysicalSource = $source;
            }
        }

        return $rows;
    }

    /** @param list<int> $itemIds @return array<int,true> */
    private function loadDiscountRuleItems(ConnectionInterface $connection, array $itemIds): array
    {
        $columnRows = $connection->select(<<<'SQL'
SELECT TOP (1) c.name AS column_name
FROM sys.columns c
WHERE c.object_id = OBJECT_ID('dbo.SBS_DiscountRules')
  AND LOWER(REPLACE(REPLACE(c.name, '_', ''), ' ', '')) IN ('itemid', 'stockid', 'productid', 'inventoryid', 'inventoryitemid', 'stockitemid')
ORDER BY CASE LOWER(REPLACE(REPLACE(c.name, '_', ''), ' ', ''))
    WHEN 'itemid' THEN 1
    WHEN 'stockid' THEN 2
    WHEN 'stockitemid' THEN 3
    WHEN 'inventoryitemid' THEN 4
    WHEN 'inventoryid' THEN 5
    ELSE 6
END
SQL);

        $column = trim((string) ($columnRows[0]->column_name ?? ''));
        if ($column === '') {
            return [];
        }

        $result = [];
        $quotedColumn = $this->quoteIdentifier($column);

        foreach (array_chunk($itemIds, 1500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT DISTINCT CAST({$quotedColumn} AS INT) AS ItemID FROM dbo.SBS_DiscountRules WITH (NOLOCK) WHERE {$quotedColumn} IN ({$placeholders})";
            foreach ($connection->select($sql, $chunk) as $row) {
                $itemId = (int) ($row->ItemID ?? 0);
                if ($itemId > 0) {
                    $result[$itemId] = true;
                }
            }
        }

        return $result;
    }

    /** @param list<int> $itemIds @return array<int,string> */
    private function loadBrands(ConnectionInterface $connection, array $itemIds): array
    {
        $columns = $connection->select(<<<'SQL'
SELECT c.name AS column_name, TYPE_NAME(c.user_type_id) AS type_name
FROM sys.columns c
WHERE c.object_id = OBJECT_ID('dbo.IC_Items')
  AND LOWER(REPLACE(REPLACE(c.name, '_', ''), ' ', '')) IN ('brand', 'brandname', 'brandid')
SQL);

        $directBrandColumn = null;
        $brandIdColumn = null;
        foreach ($columns as $column) {
            $name = trim((string) ($column->column_name ?? ''));
            $canonical = strtolower(str_replace(['_', ' '], '', $name));
            $type = strtolower((string) ($column->type_name ?? ''));

            if (in_array($canonical, ['brand', 'brandname'], true)
                && in_array($type, ['varchar', 'nvarchar', 'char', 'nchar', 'text', 'ntext'], true)) {
                $directBrandColumn = $name;
            }
            if ($canonical === 'brandid') {
                $brandIdColumn = $name;
            }
        }

        if ($directBrandColumn !== null) {
            return $this->loadDirectBrandValues($connection, $itemIds, $directBrandColumn);
        }

        if ($brandIdColumn === null) {
            return [];
        }

        $brandLookup = $this->discoverBrandLookup($connection, $brandIdColumn);
        if ($brandLookup === null) {
            return [];
        }

        [$schema, $table, $idColumn, $nameColumn] = $brandLookup;
        $qSchema = $this->quoteIdentifier($schema);
        $qTable = $this->quoteIdentifier($table);
        $qBrandId = $this->quoteIdentifier($brandIdColumn);
        $qId = $this->quoteIdentifier($idColumn);
        $qName = $this->quoteIdentifier($nameColumn);
        $result = [];

        foreach (array_chunk($itemIds, 1500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT I.ItemID, CAST(B.{$qName} AS NVARCHAR(255)) AS Brand FROM dbo.IC_Items I WITH (NOLOCK) LEFT JOIN {$qSchema}.{$qTable} B WITH (NOLOCK) ON B.{$qId} = I.{$qBrandId} WHERE I.ItemID IN ({$placeholders})";
            foreach ($connection->select($sql, $chunk) as $row) {
                $itemId = (int) ($row->ItemID ?? 0);
                if ($itemId > 0) {
                    $result[$itemId] = trim((string) ($row->Brand ?? ''));
                }
            }
        }

        return $result;
    }

    /** @param list<int> $itemIds @return array<int,string> */
    private function loadDirectBrandValues(ConnectionInterface $connection, array $itemIds, string $column): array
    {
        $result = [];
        $quoted = $this->quoteIdentifier($column);

        foreach (array_chunk($itemIds, 1500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT ItemID, CAST({$quoted} AS NVARCHAR(255)) AS Brand FROM dbo.IC_Items WITH (NOLOCK) WHERE ItemID IN ({$placeholders})";
            foreach ($connection->select($sql, $chunk) as $row) {
                $itemId = (int) ($row->ItemID ?? 0);
                if ($itemId > 0) {
                    $result[$itemId] = trim((string) ($row->Brand ?? ''));
                }
            }
        }

        return $result;
    }

    /** @return array{0:string,1:string,2:string,3:string}|null */
    private function discoverBrandLookup(ConnectionInterface $connection, string $brandIdColumn): ?array
    {
        $fkSql = <<<'SQL'
SELECT TOP (1)
    SCHEMA_NAME(rt.schema_id) AS schema_name,
    rt.name AS table_name,
    rc.name AS id_column
FROM sys.foreign_key_columns fkc
INNER JOIN sys.columns pc ON pc.object_id = fkc.parent_object_id AND pc.column_id = fkc.parent_column_id
INNER JOIN sys.tables rt ON rt.object_id = fkc.referenced_object_id
INNER JOIN sys.columns rc ON rc.object_id = fkc.referenced_object_id AND rc.column_id = fkc.referenced_column_id
WHERE fkc.parent_object_id = OBJECT_ID('dbo.IC_Items')
  AND pc.name = ?
SQL;
        $fkRows = $connection->select($fkSql, [$brandIdColumn]);

        if ($fkRows !== []) {
            $schema = (string) ($fkRows[0]->schema_name ?? 'dbo');
            $table = (string) ($fkRows[0]->table_name ?? '');
            $idColumn = (string) ($fkRows[0]->id_column ?? '');
            $nameColumn = $this->findBrandNameColumn($connection, $schema, $table);
            if ($table !== '' && $idColumn !== '' && $nameColumn !== null) {
                return [$schema, $table, $idColumn, $nameColumn];
            }
        }

        $tables = $connection->select(<<<'SQL'
SELECT s.name AS schema_name, t.name AS table_name
FROM sys.tables t
INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
WHERE LOWER(t.name) LIKE '%brand%'
ORDER BY CASE WHEN LOWER(t.name) IN ('ic_brands', 'ic_itembrands', 'ic_brand') THEN 0 ELSE 1 END, t.name
SQL);

        foreach ($tables as $tableRow) {
            $schema = (string) ($tableRow->schema_name ?? 'dbo');
            $table = (string) ($tableRow->table_name ?? '');
            if ($table === '') {
                continue;
            }

            $columns = $connection->select(
                'SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)',
                [$schema.'.'.$table],
            );
            $names = array_map(static fn (object $row): string => (string) ($row->name ?? ''), $columns);
            $idColumn = $this->firstColumn($names, ['BrandID', 'ItemBrandID', 'ID']);
            $nameColumn = $this->firstColumn($names, ['BrandName', 'Name', 'Brand', 'Description', 'Code']);
            if ($idColumn !== null && $nameColumn !== null) {
                return [$schema, $table, $idColumn, $nameColumn];
            }
        }

        return null;
    }

    private function findBrandNameColumn(ConnectionInterface $connection, string $schema, string $table): ?string
    {
        $rows = $connection->select(
            'SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)',
            [$schema.'.'.$table],
        );
        $names = array_map(static fn (object $row): string => (string) ($row->name ?? ''), $rows);

        return $this->firstColumn($names, ['BrandName', 'Name', 'Brand', 'Description', 'Code']);
    }

    /** @param list<string> $columns @param list<string> $preferred */
    private function firstColumn(array $columns, array $preferred): ?string
    {
        foreach ($preferred as $wanted) {
            foreach ($columns as $column) {
                if (strcasecmp($column, $wanted) === 0) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '['.str_replace(']', ']]', $identifier).']';
    }

    private function rowValue(object|array $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->{$key} ?? null;
    }
}
