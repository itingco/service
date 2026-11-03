<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SalesDataException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/** Single classification policy for sales rows; physical SQL connections are unchanged. */
final class SalesDashboardClassificationService
{
    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    public function classify(array $rows, string $source): array
    {
        if ($rows === []) return [];
        $source = strtoupper($source);
        $codes = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['item_code'] ?? '')));
            if ($code !== '') $codes[$code] = $code;
        }
        if ($codes === []) {
            throw new SalesDataException($source, 'Hasil fn_PenjualanLini tidak memiliki ItemCode untuk klasifikasi brand dan status.');
        }
        $conn = DB::connection($source === 'INGCO' ? 'sqlsrv_ingco' : 'sqlsrv_smi');
        $meta = $this->fetchMeta($conn, array_values($codes), $source);
        foreach ($rows as &$row) {
            $code = strtoupper(trim((string) ($row['item_code'] ?? '')));
            if ($code === '' || !isset($meta[$code])) {
                throw new SalesDataException($source, "ItemCode {$code} tidak ditemukan di IC_Items; klasifikasi tidak dapat dipastikan.");
            }
            $item = $meta[$code];
            $brand = trim((string) $item['brand']);
            $business = $source === 'INGCO' ? 'INGCO' : (str_contains(strtoupper($brand), 'WADFOW') ? 'WADFOW' : 'SMI');
            $discontinued = (bool) $item['discontinued'];
            $hasRule = (bool) $item['has_rule'];
            $reject = (bool) ($row['clearance'] ?? false);
            $status = $reject ? 'ITEM REJECT' : ($discontinued ? ($hasRule ? 'DISCONTINUE + SALE' : 'DISCONTINUE') : 'NORMAL');
            $row['physical_source'] = $source;
            $row['source'] = $business;
            $row['database'] = $business;
            $row['brand'] = $brand;
            $row['discontinued'] = $discontinued;
            $row['has_discount_rule'] = $hasRule;
            $row['item_status_category'] = $status;
            $row['status_item'] = $status;
            // Retain legacy clearance flag for the raw detail/export UI.
        }
        unset($row);
        return $rows;
    }

    /** @param list<string> $codes @return array<string,array{brand:string,discontinued:bool,has_rule:bool}> */
    private function fetchMeta(ConnectionInterface $conn, array $codes, string $source): array
    {
        $columns = $this->columns($conn, 'IC_Items');
        foreach (['itemid', 'itemcode', 'discontinue'] as $required) {
            if (!isset($columns[$required])) throw new SalesDataException($source, "IC_Items tidak memiliki kolom {$required}.");
        }
        $id = $this->q($columns['itemid']);
        $code = $this->q($columns['itemcode']);
        $discontinue = $this->q($columns['discontinue']);
        $brandExpression = "CAST('' AS NVARCHAR(255))";
        $join = '';
        if ($source === 'SMI') {
            $brandCol = $columns['brandname'] ?? $columns['brand'] ?? null;
            if ($brandCol) {
                $brandExpression = 'CAST(I.'.$this->q($brandCol).' AS NVARCHAR(255))';
            } else {
                $brandId = $columns['brandid'] ?? null;
                $lookup = $brandId ? $this->findBrandLookup($conn) : null;
                if (!$lookup) throw new SalesDataException($source, 'Kolom/relasi Brand IC_Items tidak ditemukan pada AS_SMI. Harap verifikasi master Brand.');
                [$table, $lookupId, $lookupName] = $lookup;
                $join = ' LEFT JOIN '.$table.' B ON B.'.$this->q($lookupId).' = I.'.$this->q($brandId);
                $brandExpression = 'CAST(B.'.$this->q($lookupName).' AS NVARCHAR(255))';
            }
        }
        $result = [];
        $ids = [];
        foreach (array_chunk($codes, 400) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT I.{$id} AS ItemID, I.{$code} AS ItemCode, I.{$discontinue} AS IsDiscontinue, {$brandExpression} AS Brand FROM dbo.IC_Items I {$join} WHERE UPPER(LTRIM(RTRIM(I.{$code}))) IN ({$ph})";
            foreach ($conn->select($sql, $chunk) as $record) {
                $key = strtoupper(trim((string) $record->ItemCode));
                $itemId = (int) $record->ItemID;
                $result[$key] = ['brand'=>(string) ($record->Brand ?? ''), 'discontinued'=>(bool) ($record->IsDiscontinue ?? false), 'has_rule'=>false];
                $ids[$itemId] = $key;
            }
        }
        if ($ids === []) return $result;
        $rules = $this->columns($conn, 'SBS_DiscountRules');
        $ruleColumn = null;
        foreach (['itemid','stockitemid','inventoryitemid','stockid','productid'] as $candidate) {
            if (isset($rules[$candidate])) { $ruleColumn = $rules[$candidate]; break; }
        }
        if (!$ruleColumn) throw new SalesDataException($source, 'SBS_DiscountRules tidak ditemukan atau tidak memiliki field ItemID/StockID yang dikenali.');
        $qRule = $this->q($ruleColumn);
        foreach (array_chunk(array_keys($ids), 400) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            foreach ($conn->select("SELECT DISTINCT {$qRule} AS ItemID FROM dbo.SBS_DiscountRules WHERE {$qRule} IN ({$ph})", $chunk) as $record) {
                $key = $ids[(int) $record->ItemID] ?? null;
                if ($key !== null) $result[$key]['has_rule'] = true;
            }
        }
        return $result;
    }

    /** @return array<string,string> */
    private function columns(ConnectionInterface $conn, string $table): array
    {
        $rows = $conn->select('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)', ['dbo.'.$table]);
        $result = [];
        foreach ($rows as $row) {
            $name = (string) $row->name;
            $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $name) ?? '');
            $result[$key] = $name;
        }
        return $result;
    }

    /** @return array{string,string,string}|null */
    private function findBrandLookup(ConnectionInterface $conn): ?array
    {
        // Prefer known brand master tables and check their real columns first.
        foreach (['IC_Brands','IC_ItemBrands','MA_Brands','Brands'] as $table) {
            $cols = $this->columns($conn, $table);
            $brandId = $cols['brandid'] ?? null;
            $brandName = $cols['brandname'] ?? $cols['name'] ?? null;
            if ($brandId && $brandName) return ['dbo.'.$this->q($table), $brandId, $brandName];
        }
        return null;
    }

    private function q(string $identifier): string
    {
        return '['.str_replace(']', ']]', $identifier).']';
    }
}
