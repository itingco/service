<?php

declare(strict_types=1);

namespace App\Services\ServiceQuotations;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ServiceQuotationRepository
{
    private bool $verifiedAppHub = false;

    /**
     * DRAFT storage hanya di DB_AppHub. Validasi database aktif untuk
     * mencegah salah konfigurasi koneksi mengarah ke AS_INGCO/AS_SMI.
     */
    private function db(): ConnectionInterface
    {
        $name = (string) config('service_quotations.local_connection', 'sqlsrv_apphub');
        if ($name !== 'sqlsrv_apphub') {
            throw new RuntimeException('Penyimpanan penawaran hanya diizinkan memakai koneksi sqlsrv_apphub.');
        }
        $db = DB::connection($name);
        if (!$this->verifiedAppHub) {
            if ($db->getDriverName() !== 'sqlsrv') {
                throw new RuntimeException('Koneksi sqlsrv_apphub bukan SQL Server.');
            }
            $row = $db->selectOne('SELECT DB_NAME() AS CurrentDatabase');
            $activeDatabase = (string) ($row->CurrentDatabase ?? '');
            if (strcasecmp($activeDatabase, 'DB_AppHub') !== 0) {
                throw new RuntimeException('Koneksi sqlsrv_apphub mengarah ke '.$activeDatabase.', bukan DB_AppHub. Operasi dibatalkan.');
            }
            $this->verifiedAppHub = true;
        }
        return $db;
    }

    public function ready(): bool
    {
        $this->db(); // Fail-closed jika koneksi ternyata salah database.
        return Schema::connection('sqlsrv_apphub')->hasTable('service_quotation_counters')
            && Schema::connection('sqlsrv_apphub')->hasTable('service_quotations')
            && Schema::connection('sqlsrv_apphub')->hasTable('service_quotation_lines');
    }

    /** @return array{header:object,lines:array<int,object>} */
    public function find(int $id): array
    {
        $header = $this->db()->table('service_quotations')->where('id', $id)->first();
        if ($header === null) abort(404);
        return [
            'header' => $header,
            'lines' => $this->db()->table('service_quotation_lines')
                ->where('service_quotation_id', $id)->orderBy('line_no')->get()->all(),
        ];
    }

    /**
     * Server-side search + pagination: no fixed TOP 100 limit. Query only DB_AppHub.
     *
     * @param array{q?:string,status?:string,source?:string,per_page?:int} $filters
     */
    public function searchPaginated(array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->db()->table('service_quotations');
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            // SQL Server LIKE special chars are treated as ordinary search text.
            // Bindings protect values from SQL injection.
            $escaped = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $search);
            $term = '%'.$escaped.'%';
            $query->where(function ($query) use ($term): void {
                foreach ([
                    'quote_no', 'erp_doc_no', 'customer_name', 'damaged_item_code',
                    'damaged_item_name', 'doc_type_code', 'doc_type_label',
                ] as $column) {
                    $query->orWhere($column, 'like', $term);
                }
            });
        }
        $status = (string) ($filters['status'] ?? 'all');
        if (in_array($status, ['draft', 'sent'], true)) {
            $query->where('status', $status);
        }
        $source = (string) ($filters['source'] ?? 'all');
        if (in_array($source, ['INGCO', 'SMI'], true)) {
            $query->where('erp_source', $source);
        }
        $perPage = (int) ($filters['per_page'] ?? 20);
        return $query->orderByDesc('id')
            ->paginate(in_array($perPage, [10, 20, 50], true) ? $perPage : 20)
            ->withQueryString();
    }

    /** @return \Illuminate\Support\Collection<int,object> */
    public function latest(): \Illuminate\Support\Collection
    {
        return $this->db()->table('service_quotations')->orderByDesc('id')->limit(100)->get();
    }

    /** @param array<string,mixed> $data @param list<array<string,mixed>> $lines */
    public function create(array $data, array $lines): int
    {
        if ($lines === []) throw new RuntimeException('Minimal satu sparepart wajib dipilih.');
        return $this->db()->transaction(function () use ($data, $lines): int {
            $db = $this->db();
            $now = now();
            $quoteNo = $this->nextDraftNumber($db, $data, $now);
            $subtotal = array_sum(array_map(static fn (array $line): float => (float) $line['line_total'], $lines));
            $id = (int) $db->table('service_quotations')->insertGetId([
                ...$data,
                'quote_no' => $quoteNo, 'subtotal' => round($subtotal, 2),
                'status' => 'draft', 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($lines as $index => $line) {
                $db->table('service_quotation_lines')->insert([
                    ...$line, 'service_quotation_id' => $id, 'line_no' => $index + 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            return $id;
        }, 3);
    }


    /** @param array<string,mixed> $data @param list<array<string,mixed>> $lines */
    public function updateDraft(int $id, array $data, array $lines): void
    {
        if ($lines === []) throw new RuntimeException('Minimal satu sparepart wajib dipilih.');
        $this->db()->transaction(function () use ($id, $data, $lines): void {
            $db = $this->db();
            $current = $db->table('service_quotations')->where('id', $id)->lockForUpdate()->first();
            if ($current === null) abort(404);
            if ((string) $current->status !== 'draft') {
                throw new RuntimeException('Penawaran sudah dikirim ke ERP, tidak boleh diubah.');
            }
            $now = now();
            // Keep the original creator. No separate updated_by column exists in this schema.
            unset($data['created_by']);
            $newCode = strtoupper(trim((string) $data['doc_type_code']));
            $newSource = strtoupper(trim((string) $data['erp_source']));
            $changedSeries = $newSource !== strtoupper((string) $current->erp_source)
                || $newCode !== strtoupper((string) $current->doc_type_code);
            // Jika tipe/database berubah, gunakan nomor draft dari seri baru. Nomor lama tidak didaur ulang.
            $quoteNo = $changedSeries ? $this->nextDraftNumber($db, $data, $now) : (string) $current->quote_no;
            $subtotal = round(array_sum(array_map(static fn (array $line): float => (float) $line['line_total'], $lines)), 2);
            $db->table('service_quotations')->where('id', $id)->where('status', 'draft')->update([
                ...$data,
                'quote_no' => $quoteNo,
                'subtotal' => $subtotal,
                'updated_at' => $now,
            ]);
            $db->table('service_quotation_lines')->where('service_quotation_id', $id)->delete();
            foreach ($lines as $index => $line) {
                $db->table('service_quotation_lines')->insert([
                    ...$line, 'service_quotation_id' => $id, 'line_no' => $index + 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }, 1);
    }

    /**
     * Lock row DB_AppHub until ERP response and local status have both been handled.
     * Prevents simultaneous edit and send of the same draft from this application.
     * @param callable(object,list<object>):string $sender
     */
    public function sendUnderLock(int $id, callable $sender): string
    {
        return $this->db()->transaction(function () use ($id, $sender): string {
            $db = $this->db();
            $header = $db->table('service_quotations')->where('id', $id)->lockForUpdate()->first();
            if ($header === null) abort(404);
            if ((string) $header->status === 'sent') {
                if (empty($header->erp_doc_no)) throw new RuntimeException('Status terkirim tetapi nomor ERP kosong. Hubungi administrator.');
                return (string) $header->erp_doc_no;
            }
            if ((string) $header->status !== 'draft') {
                throw new RuntimeException('Status penawaran tidak dapat dikirim.');
            }
            $lines = $db->table('service_quotation_lines')->where('service_quotation_id', $id)
                ->orderBy('line_no')->get()->all();
            $docNo = $sender($header, $lines);
            if ($docNo === '') throw new RuntimeException('Nomor ERP kosong. Status draft tidak diubah.');
            $db->table('service_quotations')->where('id', $id)->where('status', 'draft')->update([
                'status' => 'sent', 'erp_doc_no' => $docNo, 'erp_sent_at' => now(), 'updated_at' => now(),
            ]);
            return $docNo;
        }, 1);
    }

    /** @param array<string,mixed> $data */
    private function nextDraftNumber(ConnectionInterface $db, array $data, mixed $now): string
    {
        // Pisahkan nomor draft per ERP, Service Type, dan tahun. meskipun kode Service Type sama.
        $source = strtoupper(trim((string) $data['erp_source']));
        if (!in_array($source, ['INGCO', 'SMI'], true)) {
            throw new RuntimeException('Database barang rusak untuk nomor penawaran tidak valid.');
        }
        $rawSeries = $source.'-'.strtoupper(trim((string) $data['doc_type_code']));
        $series = strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', $rawSeries)) ?: 'SV';
        if (strlen($series) > 72) {
            $series = substr($series, 0, 63).'-'.substr(hash('sha256', $rawSeries), 0, 8);
        }
        $series .= '-'.$now->format('Y');
        // Lock transaksi antar request. Nomor hanya dikelola pada DB_AppHub;
        // sama sekali tidak mencari/mengubah nomor service ERP di AS_INGCO/AS_SMI.
        $lock = $db->selectOne(
            "DECLARE @result INT; EXEC @result = sys.sp_getapplock
                @Resource = ?, @LockMode = 'Exclusive',
                @LockOwner = 'Transaction', @LockTimeout = 10000;
             SELECT @result AS lock_result",
            ['service_quote_draft:'.$series]
        );
        if ($lock === null || (int) ($lock->lock_result ?? -999) < 0) {
            throw new RuntimeException('Gagal mengunci urutan nomor draft service. Coba beberapa saat lagi.');
        }
        if (!$db->table('service_quotation_counters')->where('series', $series)->exists()) {
            $db->table('service_quotation_counters')->insert([
                'series' => $series, 'last_number' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $db->table('service_quotation_counters')->where('series', $series)
            ->increment('last_number', 1, ['updated_at' => $now]);
        $sequence = (int) $db->table('service_quotation_counters')
            ->where('series', $series)->value('last_number');
        $quoteNo = 'QSV-'.$series.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        return $quoteNo;
    }

    public function markSent(int $id, string $docNo): void
    {
        $this->db()->table('service_quotations')->where('id', $id)->where('status', 'draft')->update([
            'status' => 'sent', 'erp_doc_no' => $docNo, 'erp_sent_at' => now(), 'updated_at' => now(),
        ]);
    }
}
