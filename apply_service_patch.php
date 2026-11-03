<?php
// Run from project root: php apply_service_patch.php
$root = getcwd();
$base = __DIR__;
$edits = [
'app/Http/Controllers/ServiceQuotationController.php' => [
["'lines' => ['required', 'array', 'min:1', 'max:30'],", "'lines' => ['sometimes', 'array', 'max:30'],"],
["\$rows = array_values(\$data['lines']);", "\$rows = array_values(\$data['lines'] ?? []);"],
["\$editingId !== null ? 'Draft berhasil diperbarui. Stok dan harga diverifikasi ulang.' : 'Draft penawaran berhasil dibuat. Harga dan stok sudah diverifikasi ulang dari ERP.'", "\$verified === [] ? 'Penerimaan service tersimpan sebagai draft tanpa sparepart. Tambahkan sparepart melalui Edit Draft setelah pemeriksaan.' : (\$editingId !== null ? 'Draft berhasil diperbarui. Stok dan harga diverifikasi ulang.' : 'Draft penawaran berhasil dibuat. Harga dan stok sudah diverifikasi ulang dari ERP.')"],
["    public function serviceCenters(\n", "    public function printReceipt(int \$id, ServiceQuotationRepository \$repo, ServiceTypeAddressRepository \$addresses): View\n    {\n        \$quotation = \$repo->find(\$id);\n        \$header = \$quotation['header'];\n        \$serviceCenterAddress = \$addresses->address((string) \$header->erp_source, (string) \$header->doc_type_code);\n\n        return view('service-quotations.receipt', [\n            ...\$quotation,\n            'serviceCenterAddress' => \$serviceCenterAddress,\n        ]);\n    }\n\n    public function serviceCenters(\n"],
],
'app/Services/ServiceQuotations/ServiceQuotationRepository.php' => [
["    public function create(array \$data, array \$lines): int\n    {\n        if (\$lines === []) throw new RuntimeException('Minimal satu sparepart wajib dipilih.');", "    public function create(array \$data, array \$lines): int\n    {"],
["    public function updateDraft(int \$id, array \$data, array \$lines): void\n    {\n        if (\$lines === []) throw new RuntimeException('Minimal satu sparepart wajib dipilih.');", "    public function updateDraft(int \$id, array \$data, array \$lines): void\n    {"],
["            \$docNo = \$sender(\$header, \$lines);", "            if (\$lines === []) {\n                throw new RuntimeException('Tambahkan minimal satu sparepart sebelum penawaran dikirim ke ERP.');\n            }\n            \$docNo = \$sender(\$header, \$lines);"],
],
'resources/views/service-quotations/create.blade.php' => [
["    if (!lines.length){e.preventDefault();alert('Tambahkan minimal satu sparepart.');return;}\n", ""],
['<div id="hiddenLines"></div>', '<div id="hiddenLines"></div><div class="alert alert-info mt-3 mb-0 small">Penerimaan pertama boleh disimpan tanpa sparepart. Setelah pemeriksaan teknisi, buka <strong>Edit Draft</strong> untuk melengkapi sparepart sebelum dikirim ke ERP.</div>'],
],
'resources/views/service-quotations/show.blade.php' => [
['    <a href="{{ route(\'service-quotations.print\', $header->id) }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i> Preview & Cetak</a>', '    <a href="{{ route(\'service-quotations.print\', $header->id) }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i> Cetak Penawaran A4</a>' . "\n" . '    <a href="{{ route(\'service-quotations.print-receipt\', $header->id) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-receipt me-1"></i> Cetak Struk Service 80mm</a>'],
['<button type="submit" class="btn btn-report" @disabled(!$erpEnabled || !$erpCustomerMapping)', '<button type="submit" class="btn btn-report" @disabled(!$erpEnabled || !$erpCustomerMapping || count($lines) === 0)'],
["@if(session('status'))", "@if(count(\$lines) === 0 && \$header->status === 'draft')<div class=\"alert alert-info\">Penerimaan awal tersimpan tanpa sparepart. Gunakan <strong>Edit Draft</strong> untuk melengkapi sparepart. Pengiriman ERP baru dapat dilakukan setelah ada sparepart.</div>@endif\n@if(session('status'))"],
],
'routes/service_quotations.php' => [
["        Route::post('/{id}/send-erp'", "        Route::get('/{id}/print-receipt', [ServiceQuotationController::class, 'printReceipt'])->whereNumber('id')->name('print-receipt');\n        Route::post('/{id}/send-erp'"],
],
];
$modified = [];
foreach ($edits as $name => $replacements) {
 $file = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
 if (!is_file($file)) {fwrite(STDERR,"ERROR: Missing $name\n");exit(1);}
 $content = file_get_contents($file);
 foreach ($replacements as [$old,$new]) {
   $count=substr_count($content,$old);
   if($count!==1){fwrite(STDERR,"ERROR: $name expected 1 match, got $count for ".substr($old,0,70)."\nNo files changed.\n");exit(1);}
   $content=str_replace($old,$new,$content);
 }
 $modified[$file]=$content;
}
$receipt=$base.'/resources/views/service-quotations/receipt.blade.php';
if(!is_file($receipt)){fwrite(STDERR,"ERROR: Missing receipt template\n");exit(1);}
$backup=$root.'/service_patch_backup_'.date('Ymd_His');
foreach($modified as $file=>$data){$rel=substr($file,strlen($root)+1);$dest=$backup.'/'.$rel;if(!is_dir(dirname($dest)))mkdir(dirname($dest),0777,true);copy($file,$dest);}
foreach($modified as $file=>$data){file_put_contents($file,$data);echo "Updated: ".substr($file,strlen($root)+1)."\n";}
$target=$root.'/resources/views/service-quotations/receipt.blade.php';
if(is_file($target))copy($target,$backup.'/receipt_original.blade.php');
copy($receipt,$target);
echo "Added: resources/views/service-quotations/receipt.blade.php\nBackup: $backup\nSuccess. Run php artisan optimize:clear\n";
