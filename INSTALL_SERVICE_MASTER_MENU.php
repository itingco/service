<?php

declare(strict_types=1);

/**
 * One-time, idempotent sidebar adjustment. Only edits Blade layout on the local
 * filesystem; no database writes and no ERP table/record changes.
 *
 * Usage: php INSTALL_SERVICE_MASTER_MENU.php
 */
$root = __DIR__;
$file = $root.'/resources/views/layouts/app.blade.php';
if (!is_file($file)) {
    fwrite(STDERR, "Tidak menemukan resources/views/layouts/app.blade.php. Jalankan dari root Laravel.\n");
    exit(1);
}
$content = file_get_contents($file);
if ($content === false) {
    fwrite(STDERR, "Layout tidak dapat dibaca.\n");
    exit(1);
}

// This existing permission is used in the module already; only the placement
// of the Master Alamat link changes. Regular service quotations remain a report.
$conditionOld = "@if ((\$dashboardMenuAccess['product_focus'] ?? false) || (\$dashboardMenuAccess['similar_items'] ?? false))";
$conditionNew = "@if ((\$dashboardMenuAccess['product_focus'] ?? false) || (\$dashboardMenuAccess['similar_items'] ?? false) || (\$dashboardMenuAccess['service_quotations'] ?? false))";
$anchor = "<nav class=\"sidebar-nav\">\n                @if (\$dashboardMenuAccess['product_focus'] ?? false)";
$link = <<<'BLADE'
                @if ($dashboardMenuAccess['service_quotations'] ?? false)
                    <a href="{{ route('service-quotations.service-centers') }}" title="Master Alamat Service Center" class="sidebar-link {{ request()->routeIs('service-quotations.service-centers*') ? 'active' : '' }}"><i class="bi bi-geo-alt sidebar-icon"></i><span class="nav-label">Master Alamat Service Center</span></a>
                @endif
BLADE;

$needsCondition = strpos($content, $conditionNew) === false;
$needsLink = strpos($content, "route('service-quotations.service-centers')") === false;
$oldReportActive = "request()->routeIs('service-quotations.*') ? 'active' : ''";
$newReportActive = "(request()->routeIs('service-quotations.*') && !request()->routeIs('service-quotations.service-centers*')) ? 'active' : ''";
$needsActive = strpos($content, $oldReportActive) !== false;
if (!$needsCondition && !$needsLink && !$needsActive) {
    echo "Master Alamat Service Center sudah ada di sidebar Master Data. Tidak ada file diubah.\n";
    exit(0);
}

if ($needsCondition && substr_count($content, $conditionOld) !== 1) {
    fwrite(STDERR, "Pola pembuka MASTER DATA berubah. Layout tidak diubah; silakan kirim file layout terbaru.\n");
    exit(2);
}
if ($needsLink && substr_count($content, $anchor) !== 1) {
    fwrite(STDERR, "Pola menu MASTER DATA berubah. Layout tidak diubah; silakan kirim file layout terbaru.\n");
    exit(2);
}

if ($needsCondition) $content = str_replace($conditionOld, $conditionNew, $content);
if ($needsLink) $content = str_replace($anchor, "<nav class=\"sidebar-nav\">\n".$link."\n                @if (\$dashboardMenuAccess['product_focus'] ?? false)", $content);
if ($needsActive) $content = str_replace($oldReportActive, $newReportActive, $content);

// Avoid creating partial output on failure.
$backup = $file.'.bak-service-master-'.date('Ymd-His');
if (!copy($file, $backup)) {
    fwrite(STDERR, "Gagal membuat backup; layout tidak diubah.\n");
    exit(3);
}
if (file_put_contents($file, $content, LOCK_EX) === false) {
    copy($backup, $file);
    fwrite(STDERR, "Gagal menyimpan layout; backup telah dipulihkan.\n");
    exit(4);
}
echo "OK: link Master Alamat Service Center dipindahkan ke bagian MASTER DATA.\n";
echo "Backup layout: {$backup}\n";
