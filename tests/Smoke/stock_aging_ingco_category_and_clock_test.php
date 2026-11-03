<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$sqlPath = $root.'/database/sql/USP_Dashboard_SisaStokWarehouse.sql';
$salesDashboardPath = $root.'/resources/views/sales-dashboard/index.blade.php';

if (!is_file($sqlPath)) {
    fwrite(STDERR, "FAIL: missing database/sql/USP_Dashboard_SisaStokWarehouse.sql\n");
    exit(1);
}

$sql = (string) file_get_contents($sqlPath);
$dashboard = (string) file_get_contents($salesDashboardPath);

$checks = [
    [str_contains($sql, "StockCategoryCode") && str_contains($sql, "'BDG'"), 'stock procedure must resolve BDG by category code'],
    [!preg_match('/CategoryID\s*=\s*1\b/i', $sql), 'stock procedure must not hard-code CategoryID = 1'],
    [str_contains($dashboard, 'data-live-jakarta-clock'), 'sales dashboard live clock placeholder missing'],
    [str_contains($dashboard, "timeZone: 'Asia/Jakarta'"), 'sales dashboard clock must use Asia/Jakarta timezone'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: dynamic BDG category SQL and live WIB dashboard clock source checks.\n");
