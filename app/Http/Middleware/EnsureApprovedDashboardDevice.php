<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use App\Services\Security\DashboardAuditService;
use App\Services\Security\DashboardPermissionService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureApprovedDashboardDevice
{
    public function __construct(
        private readonly DashboardAuditService $audits,
        private readonly DashboardPermissionService $permissions,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $userId = (int) $session->get((string) config('dashboard_security.session_user_id_key'), 0);

        if ($session->get((string) config('dashboard_auth.session_key')) !== true || $userId <= 0) {
            return $this->deny($request, 'Sesi login belum tersedia.');
        }

        try {
            /** @var DashboardUser|null $user */
            $user = DashboardUser::query()->find($userId);
        } catch (Throwable $exception) {
            report($exception);

            return $this->deny($request, 'Database keamanan belum siap atau tidak dapat dibaca.');
        }

        if ($user === null || !$user->is_active) {
            return $this->deny($request, 'Akun tidak aktif atau tidak ditemukan.', $user);
        }

        $lastActivity = (int) $session->get((string) config('dashboard_security.session_last_activity_key'), 0);
        $idleLimit = max(1, (int) config('dashboard_security.session_idle_minutes', 30)) * 60;
        if ($lastActivity > 0 && (time() - $lastActivity) > $idleLimit) {
            return $this->deny($request, 'Sesi berakhir karena tidak ada aktivitas.', $user);
        }

        $device = null;
        $deviceId = (int) $session->get((string) config('dashboard_security.session_device_id_key'), 0);
        if ($deviceId > 0) {
            try {
                /** @var DashboardDevice|null $device */
                $device = DashboardDevice::query()
                    ->whereKey($deviceId)
                    ->where('user_id', $user->getKey())
                    ->first();

                if ($device !== null && ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinutes(5)))) {
                    $device->forceFill([
                        'last_seen_at' => now(),
                        'last_ip' => $request->ip(),
                    ])->save();
                }
            } catch (Throwable $exception) {
                // Device data is informational only, so a device lookup/update error does not invalidate the session.
                report($exception);
                $device = null;
            }
        }

        $session->put((string) config('dashboard_auth.username_session_key'), $user->username);
        $session->put((string) config('dashboard_security.session_user_name_key'), $user->name);
        $session->put((string) config('dashboard_security.session_user_role_key'), $user->role);
        $session->put((string) config('dashboard_security.session_last_activity_key'), time());

        $request->attributes->set('dashboard_user', $user);
        $request->attributes->set('dashboard_device', $device);

        view()->share('currentDashboardUser', $user);
        view()->share('currentDashboardDevice', $device);
        view()->share('dashboardMenuAccess', $this->permissions->menuAccess($user));
        view()->share('dashboardRoleLabel', $this->permissions->roleLabel($user->role));

        return $next($request);
    }

    private function deny(
        Request $request,
        string $reason,
        ?DashboardUser $user = null,
        ?DashboardDevice $device = null,
    ): RedirectResponse {
        $this->audits->login($request, 'session_denied', $user, $device, $user?->username, $reason);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard.login')->withErrors(['login' => $reason.' Silakan login kembali.']);
    }
}
