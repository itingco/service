<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Http/Requests/Security/ChangeOwnPasswordRequest.php',
    'app/Http/Controllers/AccountPasswordController.php',
    'resources/views/account/password.blade.php',
    'tests/Feature/DashboardPasswordSelfServiceTest.php',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root.'/'.$file)) {
        fwrite(STDERR, "Missing password self-service file: {$file}\n");
        exit(1);
    }
}

$securityConfig = (string) file_get_contents($root.'/config/dashboard_security.php');
foreach (["'password_min_length'", 'DASHBOARD_PASSWORD_MIN_LENGTH'] as $needle) {
    if (!str_contains($securityConfig, $needle)) {
        fwrite(STDERR, "Password config contract missing: {$needle}\n");
        exit(1);
    }
}

$envExample = (string) file_get_contents($root.'/.env.example');
if (!str_contains($envExample, 'DASHBOARD_PASSWORD_MIN_LENGTH=8')) {
    fwrite(STDERR, "Missing DASHBOARD_PASSWORD_MIN_LENGTH=8 in .env.example\n");
    exit(1);
}

foreach ([
    'app/Http/Requests/Security/StoreDashboardUserRequest.php',
    'app/Http/Requests/Security/ResetDashboardUserPasswordRequest.php',
    'app/Http/Requests/Security/ChangeOwnPasswordRequest.php',
] as $requestFile) {
    $content = (string) file_get_contents($root.'/'.$requestFile);
    if (!str_contains($content, "config('dashboard_security.password_min_length', 8)")) {
        fwrite(STDERR, "Configurable minimum missing in {$requestFile}\n");
        exit(1);
    }
    foreach (['->mixedCase()', '->numbers()', '->symbols()'] as $forbidden) {
        if (str_contains($content, $forbidden)) {
            fwrite(STDERR, "Composition rule {$forbidden} must not exist in {$requestFile}\n");
            exit(1);
        }
    }
}

$installer = (string) file_get_contents($root.'/app/Console/Commands/InstallDashboardSecurity.php');
foreach (["config('dashboard_security.password_min_length', 8)", 'mb_strlen($password) >= $passwordMinLength'] as $needle) {
    if (!str_contains($installer, $needle)) {
        fwrite(STDERR, "Installer password policy missing: {$needle}\n");
        exit(1);
    }
}
foreach (['preg_match(\'/[a-z]/\'', 'preg_match(\'/[A-Z]/\'', 'preg_match(\'/[0-9]/\'', 'preg_match(\'/[^A-Za-z0-9]/\''] as $forbidden) {
    if (str_contains($installer, $forbidden)) {
        fwrite(STDERR, "Installer still enforces composition rule: {$forbidden}\n");
        exit(1);
    }
}

$userView = (string) file_get_contents($root.'/resources/views/security/users/index.blade.php');
if (!str_contains($userView, "config('dashboard_security.password_min_length', 8)")) {
    fwrite(STDERR, "Admin password reset view is not using configurable minlength.\n");
    exit(1);
}
if (str_contains($userView, 'minlength="12"')) {
    fwrite(STDERR, "Admin password reset view still hard-codes minlength 12.\n");
    exit(1);
}

$routes = (string) file_get_contents($root.'/routes/web.php');
foreach (['account.password.edit', 'account.password.update', "'/account/password'"] as $needle) {
    if (!str_contains($routes, $needle)) {
        fwrite(STDERR, "Password route contract missing: {$needle}\n");
        exit(1);
    }
}

$request = (string) file_get_contents($root.'/app/Http/Requests/Security/ChangeOwnPasswordRequest.php');
foreach (['current_password', "'confirmed'", 'Password::min'] as $needle) {
    if (!str_contains($request, $needle)) {
        fwrite(STDERR, "Self-service request contract missing: {$needle}\n");
        exit(1);
    }
}

$controller = (string) file_get_contents($root.'/app/Http/Controllers/AccountPasswordController.php');
foreach (['Hash::check', 'Hash::make', 'current_password', 'Sesi Anda tetap aktif'] as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "Self-service controller contract missing: {$needle}\n");
        exit(1);
    }
}

if (str_contains($controller, 'DashboardDevice::STATUS_REVOKED') || str_contains($controller, "where('id', '<>', \$currentDeviceId)")) {
    fwrite(STDERR, "Password change must not revoke audit-only device records.\n");
    exit(1);
}

$view = (string) file_get_contents($root.'/resources/views/account/password.blade.php');
foreach (['Password Lama', 'Password Baru', 'Konfirmasi Password Baru', 'Riwayat perangkat tetap disimpan untuk audit'] as $needle) {
    if (!str_contains($view, $needle)) {
        fwrite(STDERR, "Password view contract missing: {$needle}\n");
        exit(1);
    }
}

$layout = (string) file_get_contents($root.'/resources/views/layouts/app.blade.php');
foreach (['account.password.edit', 'Ganti Password'] as $needle) {
    if (!str_contains($layout, $needle)) {
        fwrite(STDERR, "Header password link missing: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($securityConfig, "'account.password.update' => 'change_own_password'")) {
    fwrite(STDERR, "Password change audit mapping is missing.\n");
    exit(1);
}


foreach (['INSTALL_WINDOWS.bat', 'docs/DASHBOARD-DEVICE-SECURITY.md', 'CARA_PASANG_DASHBOARD_DEVICE_SECURITY.txt'] as $documentationFile) {
    $content = (string) file_get_contents($root.'/'.$documentationFile);
    foreach (['minimal 12 karakter', 'huruf besar, huruf kecil, angka, dan simbol'] as $obsolete) {
        if (str_contains($content, $obsolete)) {
            fwrite(STDERR, "Obsolete password guidance remains in {$documentationFile}: {$obsolete}\n");
            exit(1);
        }
    }
}

$salesRequest = (string) file_get_contents($root.'/app/Http/Requests/SalesDashboardRequest.php');
if (str_contains($salesRequest, 'final class SalesDashboardRequest')) {
    fwrite(STDERR, "SalesDashboardRequest is still final and breaks SalesDashboardDetailRequest inheritance.\n");
    exit(1);
}
if (!str_contains($salesRequest, 'class SalesDashboardRequest extends FormRequest')) {
    fwrite(STDERR, "SalesDashboardRequest extendable class declaration missing.\n");
    exit(1);
}

echo "Dashboard password self-service checks passed.\n";
