<?php
/** Run: php apply_serial_patch.php from Laravel root, after copying this package. */
$root = __DIR__;
$changes = [
 'app/Http/Controllers/ServiceQuotationController.php' => [
  ["'customer_phone' => ['nullable', 'string', 'max:50'],", "'customer_phone' => ['nullable', 'string', 'max:50'],\n            'serial_number' => ['nullable', 'string', 'max:150'],\n            'service_description' => ['nullable', 'string', 'max:1000'],\n            'accessories' => ['nullable', 'string', 'max:1000'],\n            'service_status' => ['nullable', 'string', 'max:60'],"],
  ["'customer_phone' => trim((string) (\$data['customer_phone'] ?? '')),", "'customer_phone' => trim((string) (\$data['customer_phone'] ?? '')),\n                'serial_number' => trim((string) (\$data['serial_number'] ?? '')),\n                'service_description' => trim((string) (\$data['service_description'] ?? '')),\n                'accessories' => trim((string) (\$data['accessories'] ?? '')),\n                'service_status' => trim((string) (\$data['service_status'] ?? '')),"],
 ],
 'app/Services/ServiceQuotations/ServiceQuotationErpGateway.php' => [
  ["'CustomerPhone' => (string) (\$header->customer_phone ?? ''),", "'CustomerPhone' => (string) (\$header->customer_phone ?? ''),\n                'SerialNumber' => (string) (\$header->serial_number ?? ''),"],
 ],
 'resources/views/service-quotations/create.blade.php' => [
  ['<label class="form-label fw-bold">Alamat Customer</label>', '<label class="form-label fw-bold">Alamat Customer</label>'],
 ],
 'resources/views/service-quotations/show.blade.php' => [
  ["<div class=\"text-muted small\">Customer</div><strong>{{ \$header->customer_name }}</strong>", "<div class=\"text-muted small\">Customer</div><strong>{{ \$header->customer_name }}</strong><div class=\"small\">Serial Number: {{ \$header->serial_number ?: '—' }}</div><div class=\"small\">Accessories: {{ \$header->accessories ?: '—' }}</div><div class=\"small\">Service Status: {{ \$header->service_status ?: '—' }}</div>"],
 ],
];
// Inject new fields in the existing customer form without removing previous fields or discount behavior.
$createFile = $root.'/resources/views/service-quotations/create.blade.php';
if (is_file($createFile)) {
 $content=file_get_contents($createFile);
 if (strpos($content,'name="serial_number"')===false) {
  $needle='<div class="col-md-3"><label class="form-label fw-bold">Database Barang Rusak *';
  $fields='<div class="col-md-6"><label class="form-label fw-bold">Serial Number</label><input class="form-control" name="serial_number" maxlength="150" value="{{ old(\'serial_number\', $editHeader->serial_number ?? \'\') }}" placeholder="Serial number unit"></div>\n    <div class="col-md-6"><label class="form-label fw-bold">Description</label><input class="form-control" name="service_description" maxlength="1000" value="{{ old(\'service_description\', $editHeader->service_description ?? \'\') }}" placeholder="Deskripsi kondisi unit"></div>\n    <div class="col-md-6"><label class="form-label fw-bold">Accessories</label><input class="form-control" name="accessories" maxlength="1000" value="{{ old(\'accessories\', $editHeader->accessories ?? \'\') }}" placeholder="Kelengkapan yang diterima"></div>\n    <div class="col-md-6"><label class="form-label fw-bold">Service Status</label><select class="form-select" name="service_status">@foreach([\'\'=>\'Pilih Status\',\'Pending\'=>\'Pending\',\'On Progress\'=>\'On Progress\',\'Pass\'=>\'Pass\',\'Fail\'=>\'Fail\'] as $value=>$label)<option value="{{ $value }}" @selected(old(\'service_status\', $editHeader->service_status ?? \'\') === $value)>{{ $label }}</option>@endforeach</select></div>\n    ';
  if (substr_count($content,$needle)!==1) {fwrite(STDERR,"Form berbeda: lokasi field tidak ditemukan. Tidak ada file diubah.\n");exit(1);}
  $changes['resources/views/service-quotations/create.blade.php']=[[$needle,$fields.$needle]];
 } else unset($changes['resources/views/service-quotations/create.blade.php']);
}
$updates=[];
foreach ($changes as $relative=>$pairs) {
 $path=$root.'/'.$relative;
 if(!is_file($path)){fwrite(STDERR,"File tidak ditemukan: $relative\n");exit(1);}
 $source=file_get_contents($path);$out=$source;
 foreach($pairs as [$before,$after]) {
  if ($before===$after) continue;
  if(strpos($out,$after)!==false) continue;
  if(substr_count($out,$before)!==1){fwrite(STDERR,"Tidak cocok atau ambigu: $relative [".substr($before,0,55)."]\n");exit(1);}
  $out=str_replace($before,$after,$out);
 }
 if($out!==$source)$updates[$relative]=$out;
}
// Additional ERP validation without relying on an exact existing column list.
$gatewayRel = 'app/Services/ServiceQuotations/ServiceQuotationErpGateway.php';
$gateway = $updates[$gatewayRel] ?? file_get_contents($root.'/'.$gatewayRel);
$check = '$this->assertHas($headerCols, [\'SerialNumber\'], self::TABLE_HEADER);';
if (strpos($gateway, $check) === false) {
    $pattern = '/(\$this->assertHas\(\$headerCols,\s*\[[^;]*?\],\s*self::TABLE_HEADER\);)/s';
    if (preg_match_all($pattern, $gateway) !== 1) {
        fwrite(STDERR, "Pengecekan kolom ERP tidak cocok. Tidak ada file diubah.\n");
        exit(1);
    }
    $gateway = preg_replace($pattern, '$1'."\n        ".$check, $gateway, 1);
    $updates[$gatewayRel] = $gateway;
}
foreach($updates as $rel=>$output){$path=$root.'/'.$rel;$backup=$path.'.backup-before-serial-'.date('YmdHis');copy($path,$backup);file_put_contents($path,$output);echo "OK: $rel (backup: ".basename($backup).")\n";}
echo "Patch PHP selesai. File receipt.blade.php dan migration harus disalin mengikuti hierarchy Laravel.\n";
