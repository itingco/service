<?php

declare(strict_types=1);

namespace App\Services\ServiceQuotations;

use App\Services\Spareparts\SparepartCode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ServiceItemCatalog
{
    private function connection(string $source): string
    {
        return match (strtoupper($source)) {
            'INGCO' => 'sqlsrv_ingco', 'SMI' => 'sqlsrv_smi',
            default => throw new InvalidArgumentException('Database ERP tidak valid.'),
        };
    }

    public static function validCode(string $code): string
    {
        $code = SparepartCode::normalize($code);
        if (!preg_match('~^[A-Z0-9._()/ -]{1,100}$~D', $code)) {
            throw new InvalidArgumentException('Kode item tidak valid.');
        }
        return $code;
    }

    /** @return list<array{code:string,name:string}> */
    public function items(string $source, string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $query = mb_substr($query, 0, 90);
        $db = DB::connection($this->connection($source));
        $pattern = '%'.self::likeEscape(mb_strtoupper($query)).'%';
        $rows = $db->select(
            "SELECT TOP (30) ItemCode, ItemName FROM dbo.IC_Items
             WHERE (UPPER(ItemCode) LIKE ? ESCAPE '\\' OR UPPER(ItemName) LIKE ? ESCAPE '\\')
               AND UPPER(ItemCode) NOT LIKE '%-SP-%'
             ORDER BY CASE WHEN UPPER(ItemCode) = ? THEN 0 ELSE 1 END, ItemCode",
            [$pattern, $pattern, mb_strtoupper($query)],
        );
        return array_map(static fn (object $row): array => [
            'code' => (string) $row->ItemCode, 'name' => (string) $row->ItemName,
        ], $rows);
    }

    /** @return array{code:string,name:string} */
    public function getItem(string $source, string $code): array
    {
        $code = self::validCode($code);
        $row = DB::connection($this->connection($source))->selectOne(
            'SELECT TOP (1) ItemCode, ItemName FROM dbo.IC_Items WHERE UPPER(LTRIM(RTRIM(ItemCode))) = ?',
            [$code],
        );
        if ($row === null) throw new InvalidArgumentException('Item rusak tidak ditemukan pada database ERP terpilih.');
        return ['code' => trim((string) $row->ItemCode), 'name' => trim((string) $row->ItemName)];
    }

    /** @return list<array{code:string,name:string}> */
    public function spareparts(string $source, string $damagedCode): array
    {
        $damagedCode = self::validCode($damagedCode);
        $db = DB::connection($this->connection($source));
        // ONLY parts of this exact parent (KODE-SP-1, KODE-SP-2, etc).
        $pattern = self::likeEscape($damagedCode).'-SP-%';
        $rows = $db->select(
            "SELECT TOP (100) ItemCode, ItemName FROM dbo.IC_Items
             WHERE UPPER(LTRIM(RTRIM(ItemCode))) LIKE ? ESCAPE '\\'
             ORDER BY ItemCode",
            [$pattern],
        );
        return array_map(static fn (object $row): array => [
            'code' => (string) $row->ItemCode, 'name' => (string) $row->ItemName,
        ], $rows);
    }

    private static function likeEscape(string $query): string
    {
        return str_replace(['\\', '%', '_', '['], ['\\\\', '\\%', '\\_', '\\['], $query);
    }
}
