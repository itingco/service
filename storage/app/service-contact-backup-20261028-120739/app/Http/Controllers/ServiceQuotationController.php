<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ServiceQuotations\ServiceDocTypeRepository;
use App\Services\ServiceQuotations\ServiceItemCatalog;
use App\Services\ServiceQuotations\ServicePartAvailability;
use App\Services\ServiceQuotations\ServiceQuotationErpGateway;
use App\Services\ServiceQuotations\ServiceQuotationRepository;
use App\Services\ServiceQuotations\ServiceTypeAddressRepository;
use App\Services\ServiceQuotations\ServiceTypeCustomerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ServiceQuotationController extends Controller
{
    public function index(Request $request, ServiceQuotationRepository $repo): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:all,draft,sent'],
            'source' => ['nullable', 'in:all,INGCO,SMI'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);
        $filters = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'status' => (string) ($filters['status'] ?? 'all'),
            'source' => (string) ($filters['source'] ?? 'all'),
            'per_page' => (int) ($filters['per_page'] ?? 20),
        ];
        $ready = $repo->ready();
        return view('service-quotations.index', [
            'rows' => $ready ? $repo->searchPaginated($filters) : null,
            'needsMigration' => !$ready,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, ServiceDocTypeRepository $types): View
    {
        $erpSource = strtoupper((string) old('erp_source', $request->query('source', 'INGCO')));
        if (!in_array($erpSource, ['INGCO', 'SMI'], true)) $erpSource = 'INGCO';

        $docTypes = [];
        $typeError = null;
        try {
            $docTypes = $types->all($erpSource);
            if ($docTypes === []) {
                $typeError = "Service Type AS_{$erpSource} belum ditemukan atau format kolom hasil USP belum dikenali. Periksa storage/logs/laravel.log.";
            }
        } catch (Throwable $e) {
            report($e);
            $typeError = "Service Type AS_{$erpSource} tidak dapat dimuat. Periksa koneksi dan hasil EXEC USP_HRM_DocTypes_QueryAll tanpa parameter.";
        }
        return view('service-quotations.create', compact('docTypes', 'typeError', 'erpSource'));
    }

    public function edit(Request $request, int $id, ServiceQuotationRepository $repo, ServiceDocTypeRepository $types): View
    {
        $quote = $repo->find($id);
        $header = $quote['header'];
        if ((string) $header->status !== 'draft') {
            abort(409, 'Penawaran yang sudah dikirim ke ERP tidak dapat diedit.');
        }
        $erpSource = strtoupper((string) old('erp_source', $header->erp_source));
        if (!in_array($erpSource, ['INGCO', 'SMI'], true)) $erpSource = 'INGCO';
        $docTypes = [];
        $typeError = null;
        try {
            $docTypes = $types->all($erpSource);
            if ($docTypes === []) $typeError = 'Service Type ERP tidak ditemukan.';
        } catch (Throwable $e) {
            report($e);
            $typeError = 'Service Type ERP gagal dibaca; periksa koneksi ERP.';
        }
        $editSeed = [
            'damaged' => ['code' => (string) $header->damaged_item_code, 'name' => (string) $header->damaged_item_name],
            'lines' => array_map(static fn (object $line): array => [
                'root_code' => (string) $line->requested_sparepart_code,
                'item_code' => (string) $line->sparepart_code,
                'item_name' => (string) $line->sparepart_name,
                'source' => (string) $line->source,
                'warehouse_id' => (int) $line->warehouse_id,
                'warehouse_code' => (string) $line->warehouse_code,
                'warehouse_name' => (string) $line->warehouse_name,
                'qty_available' => (float) $line->available_qty_at_quote,
                'qty' => (int) $line->qty,
                'retail_price' => (float) $line->unit_price,
                'depth' => (int) $line->similar_depth,
                'discount_type' => (string) ($line->discount_type ?? 'none'),
                'discount_value' => (float) ($line->discount_value ?? 0),
            ], $quote['lines']),
        ];
        $editHeader = $header;
        return view('service-quotations.create', compact('docTypes', 'typeError', 'erpSource', 'editHeader', 'editSeed'));
    }

    public function docTypes(Request $request, ServiceDocTypeRepository $types): JsonResponse
    {
        $data = $request->validate(['source' => ['required', 'in:INGCO,SMI']]);
        try {
            $docTypes = $types->all($data['source']);
            return response()->json([
                'source' => $data['source'],
                'items' => $docTypes,
                'message' => $docTypes === []
                    ? 'Tidak ditemukan Service Type yang dikenali. Periksa hasil USP_HRM_DocTypes_QueryAll pada database ini dan laravel.log.'
                    : null,
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'source' => $data['source'],
                'message' => "Gagal membaca Service Type dari AS_{$data['source']}. Periksa koneksi, hasil USP dan laravel.log.",
            ], 503);
        }
    }

    public function items(Request $request, ServiceItemCatalog $catalog): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'in:INGCO,SMI'],
            'q' => ['required', 'string', 'min:2', 'max:90'],
        ]);
        try {
            return response()->json(['items' => $catalog->items($data['source'], $data['q'])]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Pencarian item ERP gagal. Periksa koneksi.'], 503);
        }
    }

    public function parts(Request $request, ServiceItemCatalog $catalog): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'in:INGCO,SMI'],
            'code' => ['required', 'string', 'max:100'],
        ]);
        try {
            $item = $catalog->getItem($data['source'], $data['code']);
            $parts = $catalog->spareparts($data['source'], $data['code']);
            return response()->json(['item' => $item, 'parts' => $parts]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Gagal membaca katalog sparepart ERP.'], 503);
        }
    }

    public function availability(Request $request, ServicePartAvailability $parts): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        try {
            return response()->json(['items' => $parts->candidates($data['code'])]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Pengecekan stok / harga ERP gagal. Pastikan seluruh sumber data tersedia.'], 503);
        }
    }

    public function store(
        Request $request,
        ServiceItemCatalog $catalog,
        ServiceDocTypeRepository $types,
        ServicePartAvailability $parts,
        ServiceQuotationRepository $repo,
    ): RedirectResponse {
        return $this->saveDraft($request, $catalog, $types, $parts, $repo);
    }

    public function update(
        Request $request,
        int $id,
        ServiceItemCatalog $catalog,
        ServiceDocTypeRepository $types,
        ServicePartAvailability $parts,
        ServiceQuotationRepository $repo,
    ): RedirectResponse {
        return $this->saveDraft($request, $catalog, $types, $parts, $repo, $id);
    }

    private function saveDraft(
        Request $request,
        ServiceItemCatalog $catalog,
        ServiceDocTypeRepository $types,
        ServicePartAvailability $parts,
        ServiceQuotationRepository $repo,
        ?int $editingId = null,
    ): RedirectResponse {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:200'],
            'erp_source' => ['required', 'in:INGCO,SMI'],
            'doc_type_id' => ['required', 'string', 'max:100'],
            'damaged_item_code' => ['required', 'string', 'max:100'],
            'complaint' => ['nullable', 'string', 'max:3000'],
            'lines' => ['sometimes', 'array', 'max:30'],
            'lines.*.requested_code' => ['required', 'string', 'max:100'],
            'lines.*.item_code' => ['required', 'string', 'max:100'],
            'lines.*.source' => ['required', 'in:INGCO,SMI'],
            'lines.*.warehouse_id' => ['required', 'integer', 'min:1'],
            'lines.*.qty' => ['required', 'integer', 'gt:0'],
            'lines.*.discount_type' => ['nullable', 'in:none,percent,amount'],
            'lines.*.discount_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        if (!$repo->ready()) {
            throw ValidationException::withMessages(['lines' => 'Database draft DB_AppHub belum siap. Jalankan php INSTALL_SERVICE_APPHUB.php.']);
        }
        try {
            $docType = $types->find($data['doc_type_id'], $data['erp_source']);
            $damaged = $catalog->getItem($data['erp_source'], $data['damaged_item_code']);
            $parentCode = strtoupper(trim($damaged['code']));
            $allCandidates = [];
            $verified = [];
            $rows = array_values($data['lines'] ?? []);
            foreach ($rows as $i => $line) {
                $root = ServiceItemCatalog::validCode($line['requested_code']);
                if (!str_starts_with($root, $parentCode.'-SP-') || !preg_match('/^'.preg_quote($parentCode, '/').'-SP-[A-Z0-9._()-]+$/D', $root)) {
                    throw new RuntimeException('Baris '.($i + 1).': kode sparepart tidak sesuai kode barang induk.');
                }
                // Root component must be registered in selected ERP, not just a guessed suffix.
                $catalog->getItem($data['erp_source'], $root);
                $allCandidates[$root] ??= $parts->candidates($root);
                $picked = $parts->confirm($line, $allCandidates[$root]);
                // Qty selalu bilangan bulat; stok dicek ulang oleh confirm() dan total per gudang.
                $qty = (int) $line['qty'];
                $price = round((float) $picked['retail_price'], 2);
                $gross = round($qty * $price, 2);
                $discountType = (string) ($line['discount_type'] ?? 'none');
                $discountValue = round((float) ($line['discount_value'] ?? 0), 2);
                if ($discountType === 'percent' && $discountValue > 100) {
                    throw new RuntimeException('Diskon persen tidak boleh melebihi 100%.');
                }
                if ($discountType === 'none' && $discountValue != 0) {
                    throw new RuntimeException('Diskon tidak valid: pilih tipe diskon terlebih dahulu.');
                }
                // Diskon nominal berlaku pada subtotal BARIS (qty x harga).
                $discountAmount = match ($discountType) {
                    'percent' => round($gross * $discountValue / 100, 2),
                    'amount' => $discountValue,
                    default => 0.0,
                };
                if ($discountAmount > $gross) {
                    throw new RuntimeException('Diskon tidak boleh melebihi total harga sparepart.');
                }
                $verified[] = [
                    'requested_sparepart_code' => $root,
                    'sparepart_code' => $picked['item_code'],
                    'sparepart_name' => $picked['item_name'],
                    'source' => $picked['source'],
                    'warehouse_id' => $picked['warehouse_id'],
                    'warehouse_code' => $picked['warehouse_code'],
                    'warehouse_name' => $picked['warehouse_name'],
                    'available_qty_at_quote' => $picked['qty_available'],
                    'qty' => $qty,
                    'unit_price' => $price,
                    'line_total' => round($gross - $discountAmount, 2),
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount_amount' => $discountAmount,
                    'similar_depth' => $picked['depth'],
                ];
            }
            // Do not oversell the same warehouse/item when it is selected more than once.
            $totals = [];
            foreach ($verified as $line) {
                $key = $line['source'].'|'.$line['warehouse_id'].'|'.$line['sparepart_code'];
                $totals[$key] = ($totals[$key] ?? 0.0) + $line['qty'];
                if ($totals[$key] > $line['available_qty_at_quote']) {
                    throw new RuntimeException('Jumlah gabungan sparepart '.$line['sparepart_code'].' melebihi stok tersedia.');
                }
            }
            $headerData = [
                'customer_name' => trim($data['customer_name']),
                'erp_source' => $data['erp_source'],
                'doc_type_id' => $docType['id'],
                'doc_type_code' => $docType['code'],
                'doc_type_label' => $docType['label'],
                'damaged_item_code' => $damaged['code'],
                'damaged_item_name' => $damaged['name'],
                'complaint' => trim((string) ($data['complaint'] ?? '')),
                'created_by' => $request->attributes->get('dashboard_user')?->id,
            ];
            if ($editingId !== null) {
                $repo->updateDraft($editingId, $headerData, $verified);
                $id = $editingId;
            } else {
                $id = $repo->create($headerData, $verified);
            }
            return redirect()->route('service-quotations.show', $id)->with('status',
                $verified === [] ? 'Penerimaan service tersimpan sebagai draft tanpa sparepart. Tambahkan sparepart melalui Edit Draft setelah pemeriksaan.' : ($editingId !== null ? 'Draft berhasil diperbarui. Stok dan harga diverifikasi ulang.' : 'Draft penawaran berhasil dibuat. Harga dan stok sudah diverifikasi ulang dari ERP.'));
        } catch (Throwable $e) {
            if ($e instanceof ValidationException) throw $e;
            report($e);
            $message = $e instanceof RuntimeException || $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'Penyimpanan gagal akibat error database. Periksa laravel.log.';
            throw ValidationException::withMessages(['lines' => $message]);
        }
    }

    public function show(int $id, ServiceQuotationRepository $repo, ServiceQuotationErpGateway $erp, ServiceTypeCustomerRepository $customers): View
    {
        $quote = $repo->find($id);
        $header = $quote['header'];
        $mapping = $customers->mapping((string) $header->erp_source, (string) $header->doc_type_code);
        return view('service-quotations.show', [
            ...$quote,
            'erpEnabled' => $erp->enabled(),
            'erpCustomerMapping' => $mapping,
        ]);
    }

    public function print(int $id, ServiceQuotationRepository $repo, ServiceTypeAddressRepository $addresses): View
    {
        $quotation = $repo->find($id);
        $header = $quotation['header'];
        $serviceCenterAddress = $addresses->address((string) $header->erp_source, (string) $header->doc_type_code);

        return view('service-quotations.print', [
            ...$quotation,
            'serviceCenterAddress' => $serviceCenterAddress,
        ]);
    }

    public function printReceipt(int $id, ServiceQuotationRepository $repo, ServiceTypeAddressRepository $addresses): View
    {
        $quotation = $repo->find($id);
        $header = $quotation['header'];
        $serviceCenterAddress = $addresses->address((string) $header->erp_source, (string) $header->doc_type_code);

        return view('service-quotations.receipt', [
            ...$quotation,
            'serviceCenterAddress' => $serviceCenterAddress,
        ]);
    }

    public function serviceCenters(
        Request $request,
        ServiceDocTypeRepository $types,
        ServiceTypeAddressRepository $addresses,
    ): View {
        $input = $request->validate(['source' => ['nullable', 'in:INGCO,SMI']]);
        $source = (string) ($input['source'] ?? 'INGCO');
        $docTypes = [];
        $typeError = null;
        try {
            $docTypes = $types->all($source);
            if ($docTypes === []) $typeError = 'Tidak ada Service Type dari ERP ini.';
        } catch (Throwable $e) {
            report($e);
            $typeError = 'Gagal membaca Service Type dari ERP. Periksa koneksi / laravel.log.';
        }

        return view('service-quotations.service-centers', [
            'source' => $source,
            'docTypes' => $docTypes,
            'addresses' => $addresses->allForSource($source),
            'needsMigration' => !$addresses->ready(),
            'typeError' => $typeError,
        ]);
    }

    public function saveServiceCenter(
        Request $request,
        ServiceDocTypeRepository $types,
        ServiceTypeAddressRepository $addresses,
    ): RedirectResponse {
        $data = $request->validate([
            'erp_source' => ['required', 'in:INGCO,SMI'],
            'doc_type_code' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $validTypes = $types->all($data['erp_source']);
            $code = strtoupper(trim($data['doc_type_code']));
            $valid = collect($validTypes)->contains(static fn (array $type): bool =>
                strtoupper((string) $type['code']) === $code
            );
            if (!$valid) {
                throw ValidationException::withMessages([
                    'doc_type_code' => 'Service Type tidak ditemukan pada database ERP yang dipilih.',
                ]);
            }
            $addresses->save(
                $data['erp_source'], $code, (string) ($data['address'] ?? ''),
                $request->attributes->get('dashboard_user')?->id,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->withErrors([
                'address' => 'Gagal menyimpan alamat pada DB_AppHub. Periksa tabel master alamat dan laravel.log.',
            ]);
        }

        return redirect()->route('service-quotations.service-centers', ['source' => $data['erp_source']])
            ->with('status', 'Alamat Service Type '.$code.' berhasil disimpan pada DB_AppHub.');
    }

    /** Master Customer Service: one ERP customer account for each Service Type. */
    public function serviceCustomers(
        Request $request,
        ServiceDocTypeRepository $types,
        ServiceTypeCustomerRepository $customers,
    ): View {
        $data = $request->validate(['source' => ['nullable', 'in:INGCO,SMI']]);
        $source = (string) ($data['source'] ?? 'INGCO');
        $docTypes = [];
        $typeError = null;
        try {
            $docTypes = $types->all($source);
            if (!$docTypes) $typeError = 'Service Type pada database ini belum ditemukan.';
        } catch (Throwable $e) {
            report($e);
            $typeError = 'Tidak dapat membaca Service Type dari ERP. Periksa laravel.log.';
        }
        $needsMigration = !$customers->ready();
        return view('service-quotations.service-customers', [
            'source' => $source,
            'docTypes' => $docTypes,
            'typeError' => $typeError,
            'needsMigration' => $needsMigration,
            'mappings' => $needsMigration ? [] : $customers->allForSource($source),
        ]);
    }

    public function lookupServiceCustomers(Request $request, ServiceTypeCustomerRepository $customers): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'in:INGCO,SMI'],
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);
        try {
            return response()->json(['customers' => $customers->searchCustomers($data['source'], $data['q'])]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Pencarian AR_Customers gagal. Periksa koneksi ERP dan laravel.log.'], 503);
        }
    }

    public function saveServiceCustomer(
        Request $request,
        ServiceDocTypeRepository $types,
        ServiceTypeCustomerRepository $customers,
    ): RedirectResponse {
        $data = $request->validate([
            'erp_source' => ['required', 'in:INGCO,SMI'],
            'doc_type_code' => ['required', 'string', 'max:100'],
            'customer_id' => ['required', 'integer', 'min:1'],
        ]);
        $code = strtoupper(trim($data['doc_type_code']));
        try {
            $found = collect($types->all($data['erp_source']))->contains(static fn (array $row): bool =>
                strtoupper((string) $row['code']) === $code
            );
            if (!$found) throw new RuntimeException('Service Type tidak terdaftar pada ERP yang dipilih.');
            $customers->save(
                $data['erp_source'], $code, (int) $data['customer_id'],
                $request->attributes->get('dashboard_user')?->id
            );
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->withErrors(['customer_id' => $e instanceof RuntimeException
                ? $e->getMessage() : 'Gagal menyimpan mapping. Periksa laravel.log.']);
        }
        return redirect()->route('service-quotations.service-customers', ['source' => $data['erp_source']])
            ->with('status', 'CustomerID untuk '.$code.' berhasil disimpan di DB_AppHub.');
    }

    public function sendErp(Request $request, int $id, ServiceQuotationRepository $repo, ServiceQuotationErpGateway $erp): RedirectResponse
    {
        try {
            $docNo = $repo->sendUnderLock($id,
                static fn (object $header, array $lines): string => $erp->send($header, $lines)
            );
            return back()->with('status', 'Penawaran dikirim ke ERP dengan nomor '.$docNo);
        } catch (RuntimeException $e) {
            Log::warning('Service quotation ERP send blocked', ['quotation_id' => $id, 'reason' => $e->getMessage()]);
            return back()->withErrors(['erp' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['erp' => 'Kirim ERP gagal. Periksa log, tabel ERP, dan potensi transaksi yang sudah terbuat sebelum mencoba ulang.']);
        }
    }
}
