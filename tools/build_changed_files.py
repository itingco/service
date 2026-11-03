#!/usr/bin/env python3
"""Produce the 5 modified Laravel files from the repo's checked-out originals.
Run from root of the exact ingco-it/sales-dashboard repository (main 2026-10-27).
Does not overwrite original files; writes output to service_patch_output/.
"""
from pathlib import Path
import shutil

root=Path.cwd()
out=root/'service_patch_output'
source=Path(__file__).resolve().parent.parent

def patch(path, transforms):
    p=root/path
    if not p.exists(): raise SystemExit(f'Missing project file: {p}')
    body=p.read_text(encoding='utf-8')
    for old,new in transforms:
        count=body.count(old)
        if count!=1: raise SystemExit(f'Stopped; {path}: expected one occurrence, found {count} for {old[:70]!r}')
        body=body.replace(old,new,1)
    dst=out/path;dst.parent.mkdir(parents=True,exist_ok=True);dst.write_text(body,encoding='utf-8')
    print('Modified:',path)

patch('app/Http/Controllers/ServiceQuotationController.php',[
 ("'lines' => ['required', 'array', 'min:1', 'max:30'],","'lines' => ['sometimes', 'array', 'max:30'],"),
 ("$rows = array_values($data['lines']);","$rows = array_values($data['lines'] ?? []);"),
 ("$editingId !== null ? 'Draft berhasil diperbarui. Stok dan harga diverifikasi ulang.' : 'Draft penawaran berhasil dibuat. Harga dan stok sudah diverifikasi ulang dari ERP.'","$verified === [] ? 'Penerimaan service tersimpan sebagai draft tanpa sparepart. Tambahkan sparepart melalui Edit Draft setelah pemeriksaan.' : ($editingId !== null ? 'Draft berhasil diperbarui. Stok dan harga diverifikasi ulang.' : 'Draft penawaran berhasil dibuat. Harga dan stok sudah diverifikasi ulang dari ERP.')"),
 ("    public function serviceCenters(\n", "    public function printReceipt(int $id, ServiceQuotationRepository $repo, ServiceTypeAddressRepository $addresses): View\n    {\n        $quotation = $repo->find($id);\n        $header = $quotation['header'];\n        $serviceCenterAddress = $addresses->address((string) $header->erp_source, (string) $header->doc_type_code);\n\n        return view('service-quotations.receipt', [\n            ...$quotation,\n            'serviceCenterAddress' => $serviceCenterAddress,\n        ]);\n    }\n\n    public function serviceCenters(\n"),
])
patch('app/Services/ServiceQuotations/ServiceQuotationRepository.php',[
 ("    public function create(array $data, array $lines): int\n    {\n        if ($lines === []) throw new RuntimeException('Minimal satu sparepart wajib dipilih.');", "    public function create(array $data, array $lines): int\n    {"),
 ("    public function updateDraft(int $id, array $data, array $lines): void\n    {\n        if ($lines === []) throw new RuntimeException('Minimal satu sparepart wajib dipilih.');", "    public function updateDraft(int $id, array $data, array $lines): void\n    {"),
 ("            $docNo = $sender($header, $lines);", "            if ($lines === []) {\n                throw new RuntimeException('Tambahkan minimal satu sparepart sebelum penawaran dikirim ke ERP.');\n            }\n            $docNo = $sender($header, $lines);"),
])
patch('resources/views/service-quotations/create.blade.php',[
 ("    if (!lines.length){e.preventDefault();alert('Tambahkan minimal satu sparepart.');return;}\n", ""),
 ("<div id=\"hiddenLines\"></div>", "<div id=\"hiddenLines\"></div><div class=\"alert alert-info mt-3 mb-0 small\">Penerimaan pertama boleh disimpan tanpa sparepart. Setelah pemeriksaan teknisi, buka <strong>Edit Draft</strong> untuk melengkapi sparepart sebelum dikirim ke ERP.</div>"),
])
patch('resources/views/service-quotations/show.blade.php',[
 ("    <a href=\"{{ route('service-quotations.print', $header->id) }}\" target=\"_blank\" rel=\"noopener\" class=\"btn btn-outline-primary\"><i class=\"bi bi-printer me-1\"></i> Preview & Cetak</a>", "    <a href=\"{{ route('service-quotations.print', $header->id) }}\" target=\"_blank\" rel=\"noopener\" class=\"btn btn-outline-primary\"><i class=\"bi bi-printer me-1\"></i> Cetak Penawaran A4</a>\n    <a href=\"{{ route('service-quotations.print-receipt', $header->id) }}\" target=\"_blank\" rel=\"noopener\" class=\"btn btn-outline-secondary\"><i class=\"bi bi-receipt me-1\"></i> Cetak Struk Service 80mm</a>"),
 ("<button type=\"submit\" class=\"btn btn-report\" @disabled(!$erpEnabled || !$erpCustomerMapping)","<button type=\"submit\" class=\"btn btn-report\" @disabled(!$erpEnabled || !$erpCustomerMapping || count($lines) === 0)"),
 ("@if(session('status'))", "@if(count($lines) === 0 && $header->status === 'draft')<div class=\"alert alert-info\">Penerimaan awal tersimpan tanpa sparepart. Gunakan <strong>Edit Draft</strong> untuk melengkapi sparepart. Pengiriman ERP baru dapat dilakukan setelah ada sparepart.</div>@endif\n@if(session('status'))"),
])
patch('routes/service_quotations.php',[
 ("        Route::post('/{id}/send-erp'", "        Route::get('/{id}/print-receipt', [ServiceQuotationController::class, 'printReceipt'])->whereNumber('id')->name('print-receipt');\n        Route::post('/{id}/send-erp'"),
])
new=out/'resources/views/service-quotations/receipt.blade.php';new.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(source/'resources/views/service-quotations/receipt.blade.php',new)
print('Added: resources/views/service-quotations/receipt.blade.php')
print('Success:',out)
