<?php
/**
 * Jalankan di root Laravel: php apply_customer_contact_patch.php
 * Mengubah file lama dengan anchor unik dan backup otomatis, tanpa mereset fitur diskon.
 */
declare(strict_types=1);
$root = getcwd();
if (!is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: Jalankan dari root Laravel (folder yang berisi artisan).\n"); exit(1);
}
$changes = [];
$transform = function (string $path, callable $change) use ($root, &$changes): void {
    $full = $root.'/'.str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($full)) throw new RuntimeException("File tidak ditemukan: {$path}");
    $before = file_get_contents($full);
    $after = $change($before);
    if ($before !== $after) $changes[$path] = [$full, $before, $after];
};
$replaceOnce = static function (string $body, string $old, string $new, string $label): string {
    if (str_contains($body, $new)) return $body;
    $count = substr_count($body, $old);
    if ($count !== 1) throw new RuntimeException("Anchor {$label} harus muncul satu kali (ditemukan {$count}). Tidak ada file diubah.");
    return str_replace($old, $new, $body);
};
try {
    $transform('app/Http/Controllers/ServiceQuotationController.php', static function (string $s) use ($replaceOnce): string {
        $s = $replaceOnce($s,
            "            'customer_name' => ['required', 'string', 'max:200'],",
            "            'customer_name' => ['required', 'string', 'max:200'],\n            'customer_phone' => ['nullable', 'string', 'max:50'],\n            'customer_address' => ['nullable', 'string', 'max:500'],",
            'controller validation');
        $s = $replaceOnce($s,
            "                'customer_name' => trim(\$data['customer_name']),",
            "                'customer_name' => trim(\$data['customer_name']),\n                'customer_phone' => trim((string) (\$data['customer_phone'] ?? '')),\n                'customer_address' => trim((string) (\$data['customer_address'] ?? '')),",
            'controller headerData');
        return $s;
    });
    $transform('resources/views/service-quotations/create.blade.php', static function (string $s) use ($replaceOnce): string {
        $anchor = '    <div class="col-md-6"><label class="form-label fw-bold">Nama Customer *</label>';
        $pos = strpos($s, $anchor);
        if ($pos === false || substr_count($s, $anchor) !== 1) throw new RuntimeException('Tidak menemukan field Nama Customer pada form.');
        if (str_contains($s, 'name="customer_phone"')) return $s;
        $next = strpos($s, "\n", $pos);
        $fields = <<<'BLADE'
    <div class="col-md-6"><label class="form-label fw-bold">Telepon Customer</label><input class="form-control" name="customer_phone" type="tel" maxlength="50" value="{{ old('customer_phone', $editHeader->customer_phone ?? '') }}" placeholder="Contoh: 081234567890"></div>
    <div class="col-12"><label class="form-label fw-bold">Alamat Customer</label><textarea class="form-control" name="customer_address" rows="2" maxlength="500" placeholder="Alamat pelanggan">{{ old('customer_address', $editHeader->customer_address ?? '') }}</textarea></div>
BLADE;
        return substr($s, 0, $next+1).$fields."\n".substr($s, $next+1);
    });
    $transform('app/Services/ServiceQuotations/ServiceQuotationErpGateway.php', static function (string $s) use ($replaceOnce): string {
        $s = $replaceOnce($s,
            "'ServiceType', 'Remarks'], self::TABLE_HEADER);",
            "'ServiceType', 'Remarks', 'CustomerPhone', 'CustomerAddress'], self::TABLE_HEADER);",
            'ERP column check');
        $s = $replaceOnce($s,
            "                'CustomerName' => (string) \$header->customer_name,",
            "                'CustomerName' => (string) \$header->customer_name,\n                'CustomerPhone' => (string) (\$header->customer_phone ?? ''),\n                'CustomerAddress' => (string) (\$header->customer_address ?? ''),",
            'ERP row');
        return $s;
    });
    $transform('resources/views/service-quotations/show.blade.php', static function (string $s) use ($replaceOnce): string {
        if (str_contains($s, 'customer_phone ??')) return $s;
        return $replaceOnce($s,
            '<div class="text-muted small">Customer</div><strong>{{ $header->customer_name }}</strong></div>',
            '<div class="text-muted small">Customer</div><strong>{{ $header->customer_name }}</strong><div class="small mt-1">Telepon: {{ $header->customer_phone ?: \'—\' }}</div><div class="small">Alamat: {{ $header->customer_address ?: \'—\' }}</div></div>',
            'show customer');
    });
    $transform('resources/views/service-quotations/print.blade.php', static function (string $s) use ($replaceOnce): string {
        if (str_contains($s, 'customer_phone ??')) return $s;
        return $replaceOnce($s,
            '<div><strong>Kepada:</strong> {{ $header->customer_name }}</div>',
            '<div><strong>Kepada:</strong> {{ $header->customer_name }}<br><strong>Telepon:</strong> {{ $header->customer_phone ?? \'—\' }}<br><strong>Alamat:</strong> {{ $header->customer_address ?? \'—\' }}</div>',
            'A4 customer');
    });
    // Receipt template from previous patch is optional; add customer contact if recognizable.
    $receipt = $root.'/resources/views/service-quotations/receipt.blade.php';
    if (is_file($receipt)) {
        $transform('resources/views/service-quotations/receipt.blade.php', static function (string $s): string {
            if (str_contains($s, 'customer_phone ??')) return $s;
            $anchor = '{{ $header->customer_name }}';
            if (substr_count($s, $anchor) !== 1) return $s;
            return str_replace($anchor, $anchor."\n<div>Telp: {{ \$header->customer_phone ?? '—' }}</div><div>Alamat: {{ \$header->customer_address ?? '—' }}</div>", $s);
        });
    }
    if (!$changes) { echo "File sudah terpasang; tidak ada perubahan.\n"; exit(0); }
    $backupRoot = $root.'/storage/app/service-contact-backup-'.date('Ymd-His');
    foreach ($changes as $path => [$full, $before, $after]) {
        $backup = $backupRoot.'/'.$path;
        if (!is_dir(dirname($backup))) mkdir(dirname($backup), 0775, true);
        if (file_put_contents($backup, $before) === false) throw new RuntimeException('Gagal backup: '.$path);
    }
    foreach ($changes as $path => [$full, $before, $after]) {
        if (file_put_contents($full, $after) === false) throw new RuntimeException('Gagal menulis: '.$path);
        echo "UPDATED {$path}\n";
    }
    echo "Backup: {$backupRoot}\nSelesai. Selanjutnya jalankan migration KHUSUS sesuai README.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "GAGAL: ".$e->getMessage()."\nFile tidak diubah jika gagal pada tahap pemeriksaan.\n"); exit(1);
}
