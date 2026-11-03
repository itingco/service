<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DashboardActivityAudit;
use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use App\Services\Security\DeviceTokenService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DashboardPasswordSelfServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
        config()->set('dashboard_security.password_min_length', 8);
    }

    public function test_viewer_can_open_own_password_page(): void
    {
        [$user, $device, $token] = $this->createApprovedIdentity('viewer');

        $this->withCookie((string) config('dashboard_security.device_cookie'), $token)
            ->withSession($this->sessionFor($user, $device))
            ->get('/account/password')
            ->assertOk()
            ->assertSee('Ganti Password');
    }

    public function test_user_can_change_own_password_and_device_history_is_preserved(): void
    {
        [$user, $device, $token] = $this->createApprovedIdentity('sales');
        $otherDevice = DashboardDevice::query()->create([
            'user_id' => $user->getKey(),
            'label' => 'Laptop Lain',
            'token_hash' => hash('sha256', 'other-device'),
            'status' => DashboardDevice::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $user->getKey(),
        ]);

        $response = $this->withCookie((string) config('dashboard_security.device_cookie'), $token)
            ->withSession($this->sessionFor($user, $device))
            ->put('/account/password', [
                'current_password' => 'oldpass88',
                'password' => 'newpass88',
                'password_confirmation' => 'newpass88',
            ]);

        $response->assertRedirect('/account/password');
        $response->assertSessionHas('status');

        $user->refresh();
        $device->refresh();
        $otherDevice->refresh();

        $this->assertTrue(Hash::check('newpass88', $user->password));
        $this->assertSame(DashboardDevice::STATUS_APPROVED, $device->status);
        $this->assertSame(DashboardDevice::STATUS_APPROVED, $otherDevice->status);
        $this->assertDatabaseHas('dashboard_activity_audits', [
            'user_id' => $user->getKey(),
            'device_id' => $device->getKey(),
            'action' => 'change_own_password',
        ], 'sqlite_targets');
    }

    public function test_wrong_current_password_does_not_change_password_or_devices(): void
    {
        [$user, $device, $token] = $this->createApprovedIdentity('sales');
        $otherDevice = DashboardDevice::query()->create([
            'user_id' => $user->getKey(),
            'label' => 'Laptop Lain',
            'token_hash' => hash('sha256', 'other-device-wrong-current'),
            'status' => DashboardDevice::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $user->getKey(),
        ]);

        $response = $this->from('/account/password')
            ->withCookie((string) config('dashboard_security.device_cookie'), $token)
            ->withSession($this->sessionFor($user, $device))
            ->put('/account/password', [
                'current_password' => 'wrongpass',
                'password' => 'newpass88',
                'password_confirmation' => 'newpass88',
            ]);

        $response->assertRedirect('/account/password');
        $response->assertSessionHasErrors('current_password');

        $user->refresh();
        $otherDevice->refresh();
        $this->assertTrue(Hash::check('oldpass88', $user->password));
        $this->assertSame(DashboardDevice::STATUS_APPROVED, $otherDevice->status);
    }

    public function test_new_password_must_respect_configured_minimum_length(): void
    {
        [$user, $device, $token] = $this->createApprovedIdentity('rnd');

        $response = $this->from('/account/password')
            ->withCookie((string) config('dashboard_security.device_cookie'), $token)
            ->withSession($this->sessionFor($user, $device))
            ->put('/account/password', [
                'current_password' => 'oldpass88',
                'password' => '1234567',
                'password_confirmation' => '1234567',
            ]);

        $response->assertRedirect('/account/password');
        $response->assertSessionHasErrors('password');
        $user->refresh();
        $this->assertTrue(Hash::check('oldpass88', $user->password));
    }

    /** @return array{DashboardUser,DashboardDevice,string} */
    private function createApprovedIdentity(string $role): array
    {
        $user = DashboardUser::query()->create([
            'name' => 'Password Test User',
            'username' => 'password-'.$role,
            'password' => Hash::make('oldpass88'),
            'role' => $role,
            'is_active' => true,
        ]);

        $token = 'password-device-token-'.str_repeat('p', 72);
        $hash = $this->app->make(DeviceTokenService::class)->hash($token);
        $device = DashboardDevice::query()->create([
            'user_id' => $user->getKey(),
            'label' => 'Current Device',
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
