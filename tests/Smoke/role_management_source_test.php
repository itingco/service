<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routes = (string) file_get_contents($root.'/routes/web.php');
$userController = (string) file_get_contents($root.'/app/Http/Controllers/Security/SecurityUserController.php');
$storeRequest = (string) file_get_contents($root.'/app/Http/Requests/Security/StoreDashboardUserRequest.php');
$updateRequest = (string) file_get_contents($root.'/app/Http/Requests/Security/UpdateDashboardUserRequest.php');

$checks = [
    [str_contains($routes, 'dashboard.permission:security_roles'), 'security_roles permission route missing'],
    [str_contains($routes, 'SecurityRoleController'), 'SecurityRoleController route missing'],
    [str_contains($userController, 'DashboardPermissionService'), 'user controller must load dynamic roles'],
    [str_contains($userController, 'Anda tidak dapat menetapkan role Administrator'), 'administrator assignment protection missing'],
    [str_contains($storeRequest, 'dashboard_roles'), 'store user role validation must use dashboard_roles'],
    [str_contains($updateRequest, 'dashboard_roles'), 'update user role validation must use dashboard_roles'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: role management source checks.\n");
