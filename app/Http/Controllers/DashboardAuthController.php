<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DashboardLoginRequest;
use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use App\Services\Security\DashboardAuditService;
use App\Services\Security\DashboardDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

final class DashboardAuthController extends Controller
{
    public function __construct(
        private readonly DashboardDeviceService $devices,
        private readonly DashboardAuditService $audits,
    ) {
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->session()->get((string) config('dashboard_auth.session_key')) === true) {
            return redirect()->route('dashboard.home');
        }

        $securityReady = $this->securityReady();

        return view('auth.login', compact('securityReady'));
    }

    public function store(DashboardLoginRequest $request): RedirectResponse
    {
        if (!$this->securityReady()) {
            return back()->withErrors([
                'login' => 'Modul keamanan belum diinstal. Jalankan php artisan dashboard-security:install.',
            ]);
        }

        $username = strtolower(trim($request->string('username')->toString()));
        $password = $request->string('password')->toString();
        $ip = (string) $request->ip();
        $rateKey = $this->rateKey($username, $ip);
        $ipRateKey = $this->ipRateKey($ip);
        $maxAttempts = max(1, (int) config('dashboard_security.login_max_attempts', 5));
        $ipMaxAttempts = max($maxAttempts, (int) config('dashboard_security.login_ip_max_attempts', 15));

        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)
            || RateLimiter::tooManyAttempts($ipRateKey, $ipMaxAttempts)) {
            $seconds = max(RateLimiter::availableIn($rateKey), RateLimiter::availableIn($ipRateKey));
            $this->audits->login($request, 'login_throttled', null, null, $username, 'Terlalu banyak percobaan login.');

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['login' => "Terlalu banyak percobaan login. Coba kembali dalam {$seconds} detik."]);
        }

        try {
            /** @var DashboardUser|null $user */
            $user = DashboardUser::query()->where('username', $username)->first();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput($request->only('username'))->withErrors([
                'login' => 'Database keamanan tidak dapat dibaca. Periksa konfigurasi dan permission SQLite.',
            ]);
        }

        if ($user === null || !$user->is_active || !Hash::check($password, $user->password)) {
            $decay = max(1, (int) config('dashboard_security.login_decay_seconds', 60));
            RateLimiter::hit($rateKey, $decay);
            RateLimiter::hit($ipRateKey, $decay);
            $reason = $user !== null && !$user->is_active ? 'Akun tidak aktif.' : 'Username atau password tidak valid.';
            $this->audits->login($request, 'login_failed', $user, null, $username, $reason);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['login' => 'Username atau password tidak valid, atau akun tidak aktif.']);
        }

        RateLimiter::clear($rateKey);
        RateLimiter::clear($ipRateKey);

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        }

        $device = null;
        $deviceToken = null;

        try {
            $resolved = $this->devices->registerOrResolve(
                $user,
                $request,
            );
            $device = $resolved['device'];
            $deviceToken = $resolved['token'];
        } catch (Throwable $exception) {
            // Device data is audit-only. A logging failure must never block a valid user login.
            report($exception);
        }

        $this->activateSession($request, $user, $device);
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audits->login($request, 'login_success', $user, $device);

        $response = redirect()->intended(route('dashboard.home'));
        if ($deviceToken !== null) {
            $response->withCookie($this->devices->cookie($deviceToken));
        }

        return $response;
    }

    public function destroy(Request $request): RedirectResponse
    {
        $userId = (int) $request->session()->get((string) config('dashboard_security.session_user_id_key'), 0);
        $deviceId = (int) $request->session()->get((string) config('dashboard_security.session_device_id_key'), 0);
        $user = $userId > 0 ? DashboardUser::query()->find($userId) : null;
        $device = $deviceId > 0 ? DashboardDevice::query()->find($deviceId) : null;
        $this->audits->login($request, 'logout', $user, $device);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard.login');
    }

    private function activateSession(Request $request, DashboardUser $user, ?DashboardDevice $device): void
    {
        $request->session()->regenerate();
        $request->session()->put((string) config('dashboard_auth.session_key'), true);
        $request->session()->put((string) config('dashboard_auth.username_session_key'), $user->username);
        $request->session()->put((string) config('dashboard_security.session_user_id_key'), $user->getKey());
        $request->session()->put((string) config('dashboard_security.session_user_name_key'), $user->name);
        $request->session()->put((string) config('dashboard_security.session_user_role_key'), $user->role);
        $request->session()->put((string) config('dashboard_security.session_last_activity_key'), time());

        if ($device !== null) {
            $request->session()->put((string) config('dashboard_security.session_device_id_key'), $device->getKey());
        } else {
            $request->session()->forget((string) config('dashboard_security.session_device_id_key'));
        }

        $request->session()->forget([
            (string) config('dashboard_security.pending_user_id_key'),
            (string) config('dashboard_security.pending_device_id_key'),
        ]);
    }

    private function rateKey(string $username, string $ip): string
    {
        return 'dashboard-login:'.hash('sha256', $username.'|'.$ip);
    }

    private function ipRateKey(string $ip): string
    {
        return 'dashboard-login-ip:'.hash('sha256', $ip);
    }

    private function securityReady(): bool
    {
        try {
            $connection = (string) config('dashboard_security.connection', 'sqlite_targets');

            // Only the user table is required to authorize login. Device/audit tables are best-effort telemetry.
            return Schema::connection($connection)->hasTable('dashboard_users');
        } catch (Throwable) {
            return false;
        }
    }
}
