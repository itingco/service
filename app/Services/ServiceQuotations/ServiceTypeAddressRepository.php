<?php

declare(strict_types=1);

namespace App\Services\ServiceQuotations;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Alamat cetak berdasarkan kombinasi sumber ERP + Service Type.
 * Data HANYA disimpan pada DB_AppHub; master AS_INGCO / AS_SMI tidak diubah.
 */
final class ServiceTypeAddressRepository
{
    private function database(): ConnectionInterface
    {
        $db = DB::connection('sqlsrv_apphub');
        if ($db->getDriverName() !== 'sqlsrv') {
            throw new RuntimeException('Master alamat hanya dapat disimpan pada SQL Server DB_AppHub.');
        }
        $active = (string) ($db->selectOne('SELECT DB_NAME() AS ActiveDatabase')->ActiveDatabase ?? '');
        if (strcasecmp($active, 'DB_AppHub') !== 0) {
            throw new RuntimeException('Koneksi sqlsrv_apphub mengarah ke '.$active.', bukan DB_AppHub. Operasi dibatalkan.');
        }
        return $db;
    }

    public function ready(): bool
    {
        $this->database();
        return Schema::connection('sqlsrv_apphub')->hasTable('service_type_addresses');
    }

    /** @return array<string,string> Map kode Service Type => alamat (boleh multiline) */
    public function allForSource(string $source): array
    {
        $source = $this->source($source);
        if (!$this->ready()) return [];
        return $this->database()->table('service_type_addresses')
            ->where('erp_source', $source)
            ->get(['doc_type_code', 'address'])
            ->mapWithKeys(static fn (object $row): array => [strtoupper((string) $row->doc_type_code) => (string) $row->address])
            ->all();
    }

    public function address(string $source, string $docTypeCode): string
    {
        $source = $this->source($source);
        if (!$this->ready()) return '';
        return (string) ($this->database()->table('service_type_addresses')
            ->where('erp_source', $source)
            ->where('doc_type_code', strtoupper(trim($docTypeCode)))
            ->value('address') ?? '');
    }

    public function save(string $source, string $docTypeCode, string $address, ?int $userId): void
    {
        $source = $this->source($source);
        $docTypeCode = strtoupper(trim($docTypeCode));
        if (!preg_match('/^[A-Z0-9._-]{1,100}$/D', $docTypeCode)) {
            throw new RuntimeException('Kode Service Type tidak valid.');
        }
        if (!$this->ready()) {
            throw new RuntimeException('Tabel master alamat belum dibuat di DB_AppHub. Jalankan installer Master Alamat.');
        }
        $now = now();
        $values = [
            'address' => trim(str_replace(["\r\n", "\r"], "\n", $address)),
            'updated_by' => $userId,
            'updated_at' => $now,
        ];
        $db = $this->database();
        $query = $db->table('service_type_addresses')
            ->where('erp_source', $source)->where('doc_type_code', $docTypeCode);
        if ($query->exists()) {
            $query->update($values);
        } else {
            $db->table('service_type_addresses')->insert([
                'erp_source' => $source, 'doc_type_code' => $docTypeCode,
                ...$values, 'created_at' => $now,
            ]);
        }
    }

    private function source(string $source): string
    {
        $source = strtoupper(trim($source));
        if (!in_array($source, ['INGCO', 'SMI'], true)) {
            throw new RuntimeException('Sumber ERP master alamat tidak valid.');
        }
        return $source;
    }
}
