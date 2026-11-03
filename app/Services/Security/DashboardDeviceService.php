<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use Illuminate\Http\Request;

final class DashboardDeviceService
{
    public function __construct(private readonly DeviceTokenService $tokens)
    {
    }

    /** @return array{device:DashboardDevice,token:?string,new:bool} */
    public function registerOrResolve(DashboardUser $user, Request $request, ?string $label = null): array
    {
        $cookieName = (string) config('dashboard_security.device_cookie');
        $plainToken = trim((string) $request->cookie($cookieName, ''));
        $mustIssueCookie = $plainToken === '';

        if ($mustIssueCookie) {
            $plainToken = $this->tokens->generate();
        }

        $tokenHash = $this->tokens->hash($plainToken);
        $existing = DashboardDevice::query()
            ->where('user_id', $user->getKey())
            ->where('token_hash', $tokenHash)
            ->first();

        if ($existing !== null) {
            $existing->forceFill([
                'last_ip' => $request->ip(),
                'last_seen_at' => now(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ])->save();

            return ['device' => $existing, 'token' => $mustIssueCookie ? $plainToken : null, 'new' => false];
        }

        $userAgent = substr((string) $request->userAgent(), 0, 1000);
        $device = DashboardDevice::query()->create([
            'user_id' => $user->getKey(),
            'label' => $this->normalizeLabel($label, $userAgent),
            'token_hash' => $tokenHash,
            // Status is retained only for backward-compatible schema; it is not used for authorization.
            'status' => DashboardDevice::STATUS_APPROVED,
            'browser' => $this->browser($userAgent),
            'platform' => $this->platform($userAgent),
            'first_ip' => $request->ip(),
            'last_ip' => $request->ip(),
            'user_agent' => $userAgent,
            'approved_at' => null,
            'approved_by' => null,
            'last_seen_at' => now(),
        ]);

        return ['device' => $device, 'token' => $mustIssueCookie ? $plainToken : null, 'new' => true];
    }

    public function cookie(string $plainToken): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            name: (string) config('dashboard_security.device_cookie'),
            value: $plainToken,
            minutes: (int) config('dashboard_security.device_cookie_minutes', 525600),
            path: '/',
            domain: null,
            secure: (bool) config('dashboard_security.device_cookie_secure', false),
            httpOnly: true,
            raw: false,
            sameSite: (string) config('dashboard_security.device_cookie_same_site', 'strict'),
        );
    }

    private function normalizeLabel(?string $label, string $userAgent): string
    {
        $label = preg_replace('/\s+/', ' ', trim((string) $label)) ?? '';
        if ($label !== '') {
            return mb_substr($label, 0, 120);
        }

        return mb_substr($this->platform($userAgent).' · '.$this->browser($userAgent), 0, 120);
    }

    private function browser(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Chrome/') => 'Google Chrome',
            str_contains($userAgent, 'Firefox/') => 'Mozilla Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser tidak dikenal',
        };
    }

    private function platform(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Windows NT 10.0') => 'Windows 10/11',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS/iPadOS',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Platform tidak dikenal',
        };
    }
}
