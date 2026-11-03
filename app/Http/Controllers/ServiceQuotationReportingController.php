<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ServiceQuotations\ServiceQuotationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class ServiceQuotationReportingController extends Controller
{
    private function db(): \Illuminate\Database\ConnectionInterface
    {
        $db = DB::connection('sqlsrv_apphub');
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp((string) ($db->selectOne('SELECT DB_NAME() AS db_name')->db_name ?? ''), 'DB_AppHub') !== 0) {
            throw new \RuntimeException('Operasi laporan hanya diperbolehkan pada DB_AppHub.');
        }
        return $db;
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:120', 'status' => 'nullable|in:all,pending,approved,cancel',
            'source' => 'nullable|in:all,INGCO,SMI', 'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'per_page' => 'nullable|integer|in:10,20,50',
        ]);
        return ['q' => trim((string) ($data['q'] ?? '')), 'status' => $data['status'] ?? 'all',
            'source' => $data['source'] ?? 'all', 'date_from' => $data['date_from'] ?? '',
            'date_to' => $data['date_to'] ?? '', 'per_page' => (int) ($data['per_page'] ?? 20)];
    }

    private function quotationQuery(array $f): \Illuminate\Database\Query\Builder
    {
        $query = $this->db()->table('service_quotations');
        if ($f['q'] !== '') {
            $escaped = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $f['q']);
            $query->where(function ($q) use ($escaped): void {
                foreach (['quote_no', 'erp_doc_no', 'customer_name', 'damaged_item_code', 'damaged_item_name', 'doc_type_code'] as $column) {
                    $q->orWhere($column, 'like', '%'.$escaped.'%');
                }
            });
        }
        if ($f['status'] !== 'all') $query->where('approval_status', $f['status']);
        if ($f['source'] !== 'all') $query->where('erp_source', $f['source']);
        if ($f['date_from'] !== '') $query->where('created_at', '>=', $f['date_from'].' 00:00:00');
        if ($f['date_to'] !== '') $query->where('created_at', '<', \Illuminate\Support\Carbon::parse($f['date_to'])->addDay()->format('Y-m-d').' 00:00:00');
        return $query;
    }

    public function index(Request $request, ServiceQuotationRepository $repo): View
    {
        $f = $this->filters($request);
        $ready = $repo->ready() && Schema::connection('sqlsrv_apphub')->hasColumn('service_quotations', 'approval_status');
        return view('service-quotations.index', [
            'rows' => $ready ? $this->quotationQuery($f)->orderByDesc('id')->paginate($f['per_page'])->withQueryString() : null,
            'needsMigration' => !$ready, 'filters' => $f,
        ]);
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['approval_status' => 'required|in:pending,approved,cancel']);
        $this->db()->transaction(function () use ($id, $data): void {
            $db = $this->db();
            $row = $db->table('service_quotations')->where('id', $id)->lockForUpdate()->first();
            if (!$row) abort(404);
            if ((string) $row->status === 'sent' && $data['approval_status'] === 'cancel') {
                throw ValidationException::withMessages(['approval_status' => 'Penawaran yang sudah dikirim ke ERP tidak dapat dibatalkan dari sini.']);
            }
            $db->table('service_quotations')->where('id', $id)->update(['approval_status' => $data['approval_status'], 'updated_at' => now()]);
        });
        return back()->with('status', 'Status persetujuan penawaran berhasil diperbarui.');
    }

    private function exportRows(\Illuminate\Database\Query\Builder $query, string $filename, array $headers, callable $map): StreamedResponse
    {
        return response()->streamDownload(static function () use ($query, $headers, $map): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            foreach ($query->cursor() as $row) fputcsv($output, $map($row));
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function export(Request $request): StreamedResponse
    {
        $f = $this->filters($request);
        $query = $this->quotationQuery($f)->orderByDesc('id');
        return $this->exportRows($query, 'penawaran-service-'.now()->format('Ymd-His').'.csv',
            ['Tanggal','No Penawaran','No ERP','Customer','Barang Rusak','Service Type','Sumber','Total','Approval','ERP Status'],
            static fn ($r) => [$r->created_at,$r->quote_no,$r->erp_doc_no,$r->customer_name,$r->damaged_item_name,$r->doc_type_label,$r->erp_source,$r->subtotal,$r->approval_status,$r->status]);
    }

    private function partFilters(Request $request): array
    {
        $data = $request->validate([
            'date_from' => 'required|date_format:Y-m-d', 'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'q' => 'nullable|string|max:120', 'source' => 'nullable|in:all,INGCO,SMI',
            'status' => 'nullable|in:all,pending,approved,cancel',
        ]);
        return ['date_from' => $data['date_from'], 'date_to' => $data['date_to'], 'q' => trim((string) ($data['q'] ?? '')),
            'source' => $data['source'] ?? 'all', 'status' => $data['status'] ?? 'all'];
    }

    private function partQuery(array $f): \Illuminate\Database\Query\Builder
    {
        $q = $this->db()->table('service_quotation_lines as l')
            ->join('service_quotations as h', 'h.id', '=', 'l.service_quotation_id')
            ->select('h.id as quotation_id', 'h.created_at as quotation_date', 'h.quote_no', 'h.erp_doc_no', 'h.customer_name',
                'h.approval_status', 'h.status as erp_status', 'h.erp_source', 'l.sparepart_code', 'l.sparepart_name',
                'l.source as part_source', 'l.warehouse_name', 'l.qty', 'l.unit_price', 'l.line_total');
        $q->where('h.created_at', '>=', $f['date_from'].' 00:00:00')
          ->where('h.created_at', '<', \Illuminate\Support\Carbon::parse($f['date_to'])->addDay()->format('Y-m-d').' 00:00:00');
        if ($f['source'] !== 'all') $q->where('l.source', $f['source']);
        if ($f['status'] !== 'all') $q->where('h.approval_status', $f['status']);
        if ($f['q'] !== '') {
            $term = '%'.str_replace(['[','%','_'],['[[]','[%]','[_]'],$f['q']).'%';
            $q->where(static function ($query) use ($term): void {
                foreach (['l.sparepart_code','l.sparepart_name','h.quote_no','h.erp_doc_no','h.customer_name'] as $column)
                    $query->orWhere($column, 'like', $term);
            });
        }
        return $q->orderBy('h.created_at')->orderBy('h.id')->orderBy('l.line_no');
    }

    public function parts(Request $request): View
    {
        $request->mergeIfMissing(['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->toDateString()]);
        $f = $this->partFilters($request);
        return view('service-quotations.sparepart-report', ['filters' => $f, 'rows' => $this->partQuery($f)->paginate(50)->withQueryString()]);
    }

    public function partsExport(Request $request): StreamedResponse
    {
        $f = $this->partFilters($request);
        return $this->exportRows($this->partQuery($f), 'laporan-sparepart-'.now()->format('Ymd-His').'.csv',
            ['Tanggal','Nomor Penawaran','Nomor ERP','Customer','Kode Sparepart','Nama Sparepart','Sumber','Gudang','Qty','Harga','Nilai','Approval','ERP Status'],
            static fn ($r) => [$r->quotation_date,$r->quote_no,$r->erp_doc_no,$r->customer_name,$r->sparepart_code,$r->sparepart_name,$r->part_source,$r->warehouse_name,$r->qty,$r->unit_price,$r->line_total,$r->approval_status,$r->erp_status]);
    }
}
