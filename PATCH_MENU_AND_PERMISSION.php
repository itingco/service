<?php
/**
 * One-time, idempotent local-only patch for EXISTING sidebar/config files.
 * Used because those files may contain changes outside this module.
 * php PATCH_MENU_AND_PERMISSION.php
 */
declare(strict_types=1);

$root = __DIR__;
$changes = [
    'config/dashboard_security.php' => [
        "        'sparepart_search' => ['label' => 'Pencarian Sparepart', 'route' => 'spareparts.search', 'group' => 'Laporan'],",
        "        'sparepart_search' => ['label' => 'Pencarian Sparepart', 'route' => 'spareparts.search', 'group' => 'Laporan'],\n        'service_quotations' => ['label' => 'Penawaran Service', 'route' => 'service-quotations.index', 'group' => 'Laporan'],",
        "'service_quotations' => ['label'",
    ],
    'resources/views/layouts/app.blade.php' => [
        "            {{-- Pencarian Sparepart: menu laporan, bukan master data --}}",
        "            @if (\$dashboardMenuAccess['service_quotations'] ?? false)\n                <a href=\"{{ route('service-quotations.index') }}\" title=\"Penawaran Service\" class=\"sidebar-link {{ request()->routeIs('service-quotations.*') ? 'active' : '' }}\"><i class=\"bi bi-clipboard-check sidebar-icon\"></i><span class=\"nav-label\">Penawaran Service</span></a>\n            @endif\n            {{-- Pencarian Sparepart: menu laporan, bukan master data --}}",
        "route('service-quotations.index')",
    ],
];

$failed = false;
foreach ($changes as $file => [$find, $replace, $already]) {
    $path = $root.'/'.$file;
    if (!is_file($path)) { fwrite(STDERR, "ERROR file tidak ditemukan: {$file}\n"); $failed = true; continue; }
    $content = file_get_contents($path);
    if (str_contains($content, $already)) { echo "Sudah terpasang: {$file}\n"; continue; }
    if (substr_count($content, $find) !== 1) { fwrite(STDERR, "ERROR marker tidak sesuai; tidak mengubah: {$file}\n"); $failed=true; continue; }
    $backup = $path.'.backup-before-service-quotation';
    if (!is_file($backup) && !copy($path, $backup)) { fwrite(STDERR, "ERROR gagal backup: {$file}\n"); $failed = true; continue; }
    $new = str_replace($find, $replace, $content);
    if ($file === 'config/dashboard_security.php') {
        $auditNeedle = "        'spareparts.search' => 'search_spareparts',";
        $auditBlock = $auditNeedle."\n".
            "        'service-quotations.index' => 'view_service_quotations',\n".
            "        'service-quotations.create' => 'create_service_quotation_form',\n".
            "        'service-quotations.store' => 'create_service_quotation',\n".
            "        'service-quotations.show' => 'view_service_quotation',\n".
            "        'service-quotations.print' => 'print_service_quotation',\n".
            "        'service-quotations.send-erp' => 'send_service_quotation_erp',";
        if (substr_count($new, $auditNeedle) === 1 && !str_contains($new, "'service-quotations.send-erp'")) {
            $new = str_replace($auditNeedle, $auditBlock, $new);
        }
    }
    if (file_put_contents($path, $new, LOCK_EX) === false) { fwrite(STDERR, "ERROR gagal tulis: {$file}\n"); $failed = true; continue; }
    echo "OK {$file}; backup tersedia.\n";
}
exit($failed ? 1 : 0);
