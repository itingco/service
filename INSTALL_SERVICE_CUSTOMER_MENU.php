<?php

declare(strict_types=1);

/** Adds Master Customer Service beside Master Alamat in the existing layout (idempotent). */
if (PHP_SAPI !== 'cli') exit('CLI only');
$file = __DIR__.'/resources/views/layouts/app.blade.php';
if (!is_file($file)) {
    fwrite(STDERR, "Tidak menemukan resources/views/layouts/app.blade.php.\n");
    exit(1);
}
$s = file_get_contents($file);
if ($s === false) exit("Tidak bisa membaca layout.\n");
if (str_contains($s, "route('service-quotations.service-customers')")) {
    echo "Menu Master Customer Service sudah ada.\n";
    exit(0);
}
$menu = <<<'BLADE'
                @if ($dashboardMenuAccess['service_quotations'] ?? false)
                    <a href="{{ route('service-quotations.service-customers') }}" title="Master Customer Service" class="sidebar-link {{ request()->routeIs('service-quotations.service-customers*') ? 'active' : '' }}"><i class="bi bi-person-vcard sidebar-icon"></i><span class="nav-label">Master Customer Service</span></a>
                @endif
BLADE;
// Locate the existing Master Alamat link, then insert AFTER the closing @endif.
$addressMarker = "route('service-quotations.service-centers')";
$pos = strpos($s, $addressMarker);
if ($pos === false) {
    fwrite(STDERR, "Master Alamat belum ada pada sidebar. Jalankan php INSTALL_SERVICE_MASTER_MENU.php dari patch sebelumnya, lalu ulangi installer ini.\n");
    exit(2);
}
$endIfPos = strpos($s, '@endif', $pos);
if ($endIfPos === false) {
    fwrite(STDERR, "Tidak menemukan penutup kondisi menu Master Alamat. Layout tidak diubah.\n");
    exit(2);
}
$insertAt = $endIfPos + strlen('@endif');
$s = substr($s, 0, $insertAt)."\n".$menu.substr($s, $insertAt);
// Exclude master menus from the Penawaran Service report active state.
$s = preg_replace(
    "/\\(request\\(\\)->routeIs\\('service-quotations\\.\\*'\\) && !request\\(\\)->routeIs\\('service-quotations\\.service-centers\\*'\\)\\)/",
    "(request()->routeIs('service-quotations.*') && !request()->routeIs('service-quotations.service-centers*') && !request()->routeIs('service-quotations.service-customers*'))",
    $s
);
$backup = $file.'.bak-customer-service-'.date('Ymd-His');
if (!copy($file, $backup)) exit("Gagal membuat backup. Layout tidak diubah.\n");
if (file_put_contents($file, $s, LOCK_EX) === false) {
    copy($backup, $file);
    exit("Gagal menyimpan layout. Backup dipulihkan.\n");
}
echo "OK: menu Master Customer Service ditambahkan ke MASTER DATA.\nBackup: {$backup}\n";
