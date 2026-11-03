<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use App\Services\Security\DeviceTokenService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DashboardDeviceAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_device_status_does_not_block_existing_session(): void
    {
        $this->actingAsDashboardAdmin();
        DashboardDevice::query()->update(['status' => DashboardDevice::STATUS_REVOKED]);

        $this->get('/security/devices')->assertOk();
    }

    public function test_viewer_cannot_open_target_settings(): void
    {
        [$user, $device, $token] = $this->createApprovedIdentity('viewer');

        $this->withCookie((string) config('dashboard_security.device_cookie'), $token)
            ->withSession($this->sessionFor($user, $device))
            ->get('/sales-kpi/targets')
            ->assertForbidden();
    }

    public function test_admin_can_open_security_device_page(): void
    {
        $this->actingAsDashboardAdmin()
            ->get('/security/devices')
            ->assertOk()
            ->assertSee('Riwayat Perangkat Dashboard')
            ->assertSee('TERCATAT');
    }

    /** @return array{DashboardUser,DashboardDevice,string} */
    private function createApprovedIdentity(string $role): array
    {
        $user = DashboardUser::query()->create([
            'name' => 'Security Test User',
            'username' => 'security-'.$role,
            'password' => Hash::make('test-password-123'),
            'role' => $role,
            'is_active' => true,
        ]);
        $token = 'security-device-'.str_repeat('z', 72);
        $hash = $this->app->make(DeviceTokenService::class)->hash($token);
        $device = DashboardDevice::query()->create([
            'user_id' => $user->getKey(),
            'label' => 'Security Test Device',
            'token_hash' => $hash,
            'status' => DashboardDevice::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $user->getKey(),
            'last_seen_at' => now(),
        ]);

        return [$user, $device, $token];
    }

    /** @return array<string,mixed> */
    private function sessionFor(DashboardUser $user, DashboardDevice $device): array
    {
        return [
            'dashboard_authenticated' => true,
            'dashboard_username' => $user->username,
            'dashboard_user_id' => $user->getKey(),
            'dashboard_user_name' => $user->name,
            'dashboard_user_role' => $user->role,
            'dashboard_device_id' => $device->getKey(),
            'dashboard_last_activity_at' => time(),
        ];
    }
}
