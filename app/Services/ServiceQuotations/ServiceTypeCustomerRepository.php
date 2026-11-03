<?php

declare(strict_types=1);

namespace App\Services\ServiceQuotations;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * Maps one ERP CustomerID to each (ERP source, Service Type) pair.
 * All mapping writes are restricted to DB_AppHub; ERP customer masters are READ ONLY.
 */
final class ServiceTypeCustomerRepository
{
    private function appHub(): ConnectionInterface
    {
        $db = DB::connection('sqlsrv_apphub');
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp((string) ($db->selectOne('SELECT DB_NAME() AS db_name')->db_name ?? ''), 'DB_AppHub') !== 0) {
            throw new RuntimeException('STOP: sqlsrv_apphub harus mengarah ke DB_AppHub.');
        }
        return $db;
    }

    private function erp(string $source): ConnectionInterface
    {
        $source = $this->source($source);
        $db = DB::connection(ServiceDocTypeRepository::sourceConnection($source));
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp((string) ($db->selectOne('SELECT DB_NAME() AS db_name')->db_name ?? ''), 'AS_'.$source) !== 0) {
            throw new RuntimeException('Koneksi ERP tidak mengarah ke AS_'.$source.'.');
        }
        return $db;
    }

    private function source(string $source): string
    {
        $source = strtoupper(trim($source));
        if (!in_array($source, ['INGCO', 'SMI'], true)) {
            throw new InvalidArgumentException('Database ERP hanya INGCO atau SMI.');
        }
        return $source;
    }

    private function serviceType(string $code): string
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z0-9._-]{1,100}$/D', $code) !== 1) {
            throw new InvalidArgumentException('Kode Service Type tidak valid.');
        }
        return $code;
    }

    public function ready(): bool
    {
        $this->appHub();
        return Schema::connection('sqlsrv_apphub')->hasTable('service_type_customers');
    }

    /** @return array<string,array{customer_id:int,customer_code:string,customer_name:string}> */
    public function allForSource(string $source): array
    {
        $source = $this->source($source);
        if (!$this->ready()) return [];
        $result = [];
        foreach ($this->appHub()->table('service_type_customers')->where('erp_source', $source)->get() as $row) {
            $result[strtoupper((string) $row->doc_type_code)] = [
                'customer_id' => (int) $row->customer_id,
                'customer_code' => (string) ($row->customer_code ?? ''),
                'customer_name' => (string) ($row->customer_name ?? ''),
            ];
        }
        return $result;
    }

    /** @return array{customer_id:int,customer_code:string,customer_name:string}|null */
    public function mapping(string $source, string $serviceType): ?array
    {
        $source = $this->source($source);
        $serviceType = $this->serviceType($serviceType);
        if (!$this->ready()) return null;
        $row = $this->appHub()->table('service_type_customers')
            ->where('erp_source', $source)->where('doc_type_code', $serviceType)->first();
        if (!$row) return null;
        return [
            'customer_id' => (int) $row->customer_id,
            'customer_code' => (string) ($row->customer_code ?? ''),
            'customer_name' => (string) ($row->customer_name ?? ''),
        ];
    }

    /**
     * Read-only lookup against AR_Customers in the chosen ERP source.
     * @return list<array{customer_id:int,customer_code:string,customer_name:string}>
     */
    public function searchCustomers(string $source, string $search): array
    {
        $search = trim($search);
        if (mb_strlen($search) < 2 || mb_strlen($search) > 80) {
            throw new InvalidArgumentException('Ketik minimal 2 dan maksimal 80 karakter untuk mencari customer ERP.');
        }
        $escaped = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $search);
        $query = $this->erp($source)->table('dbo.AR_Customers')
            ->select(['CustomerID', 'CustomerCode', 'CustomerName'])
            ->where(function ($q) use ($escaped, $search): void {
                $q->where('CustomerName', 'like', '%'.$escaped.'%')
                  ->orWhere('CustomerCode', 'like', '%'.$escaped.'%');
                if (ctype_digit($search)) $q->orWhere('CustomerID', '=', (int) $search);
            })->orderBy('CustomerName')->limit(30);
        return $query->get()->map(static fn (object $c): array => [
            'customer_id' => (int) $c->CustomerID,
            'customer_code' => trim((string) ($c->CustomerCode ?? '')),
            'customer_name' => trim((string) ($c->CustomerName ?? '')),
        ])->all();
    }

    /** Read and verify ERP customer ID against the selected database. */
    public function findErpCustomer(string $source, int $id): array
    {
        if ($id <= 0) throw new InvalidArgumentException('CustomerID harus lebih besar dari 0.');
        $row = $this->erp($source)->table('dbo.AR_Customers')
            ->where('CustomerID', $id)
            ->first(['CustomerID', 'CustomerCode', 'CustomerName']);
        if (!$row) throw new RuntimeException('CustomerID '.$id.' tidak ada pada AR_Customers AS_'.$this->source($source).'.');
        return [
            'customer_id' => (int) $row->CustomerID,
            'customer_code' => trim((string) ($row->CustomerCode ?? '')),
            'customer_name' => trim((string) ($row->CustomerName ?? '')),
        ];
    }

    public function save(string $source, string $serviceType, int $customerId, ?int $userId): void
    {
        $source = $this->source($source);
        $serviceType = $this->serviceType($serviceType);
        if (!$this->ready()) throw new RuntimeException('Tabel service_type_customers belum dibuat di DB_AppHub.');
        $customer = $this->findErpCustomer($source, $customerId);
        $now = now();
        $db = $this->appHub();
        $db->table('service_type_customers')->updateOrInsert(
            ['erp_source' => $source, 'doc_type_code' => $serviceType],
            [
                'customer_id' => $customer['customer_id'],
                'customer_code' => $customer['customer_code'],
                'customer_name' => $customer['customer_name'],
                'updated_by' => $userId,
                'updated_at' => $now,
                // SQL Server uses a default for created_at; this is updated on each save for simplicity.
            ]
        );
    }

    /** Resolve a mapping, never infer CustomerID from free-form quotation customer name. */
    public function customerIdForService(string $source, string $serviceType): int
    {
        $mapping = $this->mapping($source, $serviceType);
        if ($mapping === null) {
            throw new RuntimeException('Master Customer Service belum diatur untuk AS_'.$this->source($source).' / '.$this->serviceType($serviceType).'. Isi melalui menu Master Data > Master Customer Service sebelum Kirim ke ERP.');
        }
        // Validate again: mapping might point to a customer since deleted in ERP.
        $this->findErpCustomer($source, $mapping['customer_id']);
        return $mapping['customer_id'];
    }
}
