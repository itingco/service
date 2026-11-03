<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    'app/Services/Security/DeviceTokenService.php',
    'app/Models/DashboardUser.php',
    'app/Models/DashboardDevice.php',
    'app/Models/DashboardLoginAudit.php',
    'app/Models/DashboardActivityAudit.php',
    'app/Http/Middleware/EnsureApprovedDashboardDevice.php',
    'app/Http/Middleware/EnsureDashboardRole.php',
    'app/Http/Middleware/AuditDashboardActivity.php',
    'app/Http/Middleware/DashboardSecurityHeaders.php',
    'app/Http/Controllers/Security/SecurityUserController.php',
    'app/Http/Controllers/Security/SecurityDeviceController.php',
    'app/Http/Controllers/Security/SecurityAuditController.php',
    'resources/views/security/users/index.blade.php',
    'resources/views/security/devices/index.blade.php',
    'resources/views/security/audits/index.blade.php',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root.'/'.$file)) {
        fwrite(STDERR, "Missing security file: {$file}\n");
        exit(1);
    }
}

require_once $root.'/app/Services/Security/DeviceTokenService.php';

$key = 'base64:'.base64_encode(str_repeat('a', 32));
$service = new App\Services\Security\DeviceTokenService($key);
$token = $service->generate();

if (strlen($token) < 64) {
    fwrite(STDERR, "Device token is too short.\n");
    exit(1);
}

$hash = $service->hash($token);
if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
    fwrite(STDERR, "Device token hash is invalid.\n");
    exit(1);
}

if (!$service->matches($token, $hash)) {
    fwrite(STDERR, "Device token match failed.\n");
    exit(1);
}

if ($service->matches($token.'x', $hash)) {
    fwrite(STDERR, "Modified device token unexpectedly matched.\n");
    exit(1);
}

$routes = file_get_contents($root.'/routes/web.php');
foreach (['security.users.index', 'security.devices.index', 'security.audits.index'] as $routeName) {
    if (!str_contains((string) $routes, $routeName)) {
        fwrite(STDERR, "Missing route name: {$routeName}\n");
        exit(1);
    }
}


$deviceMigration = file_get_contents($root.'/database/migrations/2026_08_06_000110_create_dashboard_devices_table.php');
foreach (["token_hash", "unique(['user_id', 'token_hash'])", "approved_by", "last_seen_at"] as $needle) {
    if (!str_contains((string) $deviceMigration, $needle)) {
        fwrite(STDERR, "Device migration contract missing: {$needle}\n");
        exit(1);
    }
}

$auditMigration = file_get_contents($root.'/database/migrations/2026_08_06_000120_create_dashboard_audit_tables.php');
foreach (['dashboard_login_audits', 'dashboard_activity_audits', 'response_status', 'ip_address'] as $needle) {
    if (!str_contains((string) $auditMigration, $needle)) {
        fwrite(STDERR, "Audit migration contract missing: {$needle}\n");
        exit(1);
    }
}

$authMiddleware = file_get_contents($root.'/app/Http/Middleware/EnsureApprovedDashboardDevice.php');
foreach (['session_idle_minutes', 'DashboardUser::query()->find', 'session_user_role_key'] as $needle) {
    if (!str_contains((string) $authMiddleware, $needle)) {
        fwrite(STDERR, "Dashboard auth middleware contract missing: {$needle}\n");
        exit(1);
    }
}


if (str_contains((string) $authMiddleware, 'approvedForRequest') || str_contains((string) $authMiddleware, 'Perangkat tidak lagi disetujui')) {
    fwrite(STDERR, "Device approval must not be required by dashboard auth middleware.\n");
    exit(1);
}

$headers = file_get_contents($root.'/app/Http/Middleware/DashboardSecurityHeaders.php');
foreach (['X-Frame-Options', 'X-Content-Type-Options', 'Strict-Transport-Security'] as $needle) {
    if (!str_contains((string) $headers, $needle)) {
        fwrite(STDERR, "Security header missing: {$needle}\n");
        exit(1);
    }
}

$authController = file_get_contents($root.'/app/Http/Controllers/DashboardAuthController.php');
foreach (['RateLimiter', 'Hash::check', 'DashboardAuditService', 'registerOrResolve', 'login_success'] as $needle) {
    if (!str_contains((string) $authController, $needle)) {
        fwrite(STDERR, "Authentication security contract missing: {$needle}\n");
        exit(1);
    }
}

$routes = (string) file_get_contents($root.'/routes/web.php');
foreach (['dashboard.device.pending', 'security.devices.approve', 'security.devices.reject', 'security.devices.revoke'] as $obsoleteRoute) {
    if (str_contains($routes, $obsoleteRoute)) {
        fwrite(STDERR, "Obsolete device authorization route remains: {$obsoleteRoute}\n");
        exit(1);
    }
}

echo "Dashboard security checks passed.\n";
