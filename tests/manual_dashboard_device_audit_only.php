<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$routes = (string) file_get_contents($root.'/routes/web.php');
$authController = (string) file_get_contents($root.'/app/Http/Controllers/DashboardAuthController.php');
$middleware = (string) file_get_contents($root.'/app/Http/Middleware/EnsureApprovedDashboardDevice.php');
$deviceService = (string) file_get_contents($root.'/app/Services/Security/DashboardDeviceService.php');
$deviceController = (string) file_get_contents($root.'/app/Http/Controllers/Security/SecurityDeviceController.php');
$deviceView = (string) file_get_contents($root.'/resources/views/security/devices/index.blade.php');
$loginView = (string) file_get_contents($root.'/resources/views/auth/login.blade.php');
$passwordController = (string) file_get_contents($root.'/app/Http/Controllers/AccountPasswordController.php');
$userController = (string) file_get_contents($root.'/app/Http/Controllers/Security/SecurityUserController.php');
$userView = (string) file_get_contents($root.'/resources/views/security/users/index.blade.php');
$config = (string) file_get_contents($root.'/config/dashboard_security.php');

$mustContain = [
    [$authController, "registerOrResolve", 'Login must continue recording device history.'],
    [$authController, "login_success", 'Successful login audit must remain.'],
    [$middleware, "session_idle_minutes", 'Idle timeout must remain enforced.'],
    [$middleware, "DashboardUser::query()->find", 'Authenticated user must still be revalidated.'],
    [$deviceView, "TERCATAT", 'Device page must show audit-only status.'],
    [$loginView, "dicatat untuk audit", 'Login copy must explain audit-only device recording.'],
];

foreach ($mustContain as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message." Missing: {$needle}\n");
        exit(1);
    }
}

$mustNotContain = [
    [$routes, "dashboard.device.pending", 'Pending-device route must no longer be part of login flow.'],
    [$routes, "security.devices.approve", 'Approve route must be removed.'],
    [$routes, "security.devices.reject", 'Reject route must be removed.'],
    [$routes, "security.devices.revoke", 'Revoke route must be removed.'],
    [$authController, "device_pending", 'Login must not create a pending-device authorization state.'],
    [$authController, "->isApproved()", 'Login must not gate on device approval.'],
    [$authController, "hasTable('dashboard_devices')", 'Device table readiness must not block user/password login.'],
    [$middleware, "approvedForRequest", 'Session middleware must not require an approved device cookie.'],
    [$middleware, "Perangkat tidak lagi disetujui", 'Session must not be denied due to device approval state.'],
    [$deviceService, "shouldAutoApprove", 'Device registration must not have approval policy.'],
    [$deviceController, "function approve", 'Device controller must not expose approve action.'],
    [$deviceController, "function reject", 'Device controller must not expose reject action.'],
    [$deviceController, "function revoke", 'Device controller must not expose revoke action.'],
    [$deviceView, ">Setujui<", 'Device history UI must not contain approve button.'],
    [$deviceView, ">Tolak<", 'Device history UI must not contain reject button.'],
    [$deviceView, "Cabut akses", 'Device history UI must not contain revoke button.'],
    [$passwordController, "STATUS_REVOKED", 'Password change must not revoke audit-only device records.'],
    [$config, "security.devices.approve", 'Approval action must not remain in audited route map.'],
    [$config, "security.devices.reject", 'Reject action must not remain in audited route map.'],
    [$config, "security.devices.revoke", 'Revoke action must not remain in audited route map.'],
    [$userController, "status' => 'revoked", 'Admin password reset must not revoke audit-only devices.'],
    [$userView, 'pending_devices_count', 'User page must not present pending device authorization counts.'],
    [$userView, 'approved_devices_count', 'User page must not present approved device authorization counts.'],
];

foreach ($mustNotContain as [$haystack, $needle, $message]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message." Found: {$needle}\n");
        exit(1);
    }
}

if (preg_match('/session_key[^\n]+true[^\n]+\|\|[^\n]+deviceId/s', $middleware) === 1) {
    fwrite(STDERR, "Auth middleware still appears to require a device id.\n");
    exit(1);
}

if (!str_contains($deviceService, "'status' => DashboardDevice::STATUS_APPROVED")) {
    fwrite(STDERR, "New audit device records should use approved-compatible status for schema compatibility.\n");
    exit(1);
}

echo "Dashboard device audit-only checks passed.\n";
