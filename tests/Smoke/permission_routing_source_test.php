<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routes = (string) file_get_contents($root.'/routes/web.php');
$kernel = (string) file_get_contents($root.'/app/Http/Kernel.php');
$layout = (string) file_get_contents($root.'/resources/views/layouts/app.blade.php');

$checks = [
    [!str_contains($routes, "Route::middleware('dashboard.role"), 'routes must not use hard-coded dashboard.role groups'],
    [str_contains($routes, 'dashboard.permission:sales_dashboard'), 'sales dashboard permission route missing'],
    [str_contains($routes, 'dashboard.permission:sales_kpi'), 'sales KPI permission route missing'],
    [str_contains($kernel, "'dashboard.permission'"), 'permission middleware alias missing'],
    [str_contains($layout, 'dashboardMenuAccess'), 'layout must render from shared menu access'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: permission routing/navigation source checks.\n");
