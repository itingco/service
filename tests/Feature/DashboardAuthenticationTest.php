<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DashboardAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--database' => 'sqlite_targets'])->assertExitCode(0);
    }

    public function test_valid_login_records_device_and_creates_session(): void
    {
        DashboardUser::query()->create([
            'name' => 'Administrator',
            'username' => 'admin',
            'password' => Hash::make('safe-password-123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'safe-password-123',
            'device_label' => 'Laptop Admin',
        ]);

        $response->assertRedirect('/sales-kpi');
        $response->assertSessionHas('dashboard_authenticated', true);
        $response->assertSessionHas('dashboard_username', 'admin');
        $this->assertDatabaseHas('dashboard_devices', [
            'label' => 'Laptop Admin',
            'status' => DashboardDevice::STATUS_APPROVED,
        ], 'sqlite_targets');
    }

    public function test_additional_device_is_recorded_without_blocking_login(): void
    {
        $admin = DashboardUser::query()->create([
            'name' => 'Administrator',
            'username' => 'admin',
            'password' => Hash::make('safe-password-123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        DashboardDevice::query()->create([
            'user_id' => $admin->getKey(),
            'label' => 'Existing Device',
            'token_hash' => hash('sha256', 'existing'),
            'status' => DashboardDevice::STATUS_APPROVED,
        ]);

        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'safe-password-123',
            'device_label' => 'New Laptop',
        ]);

        $response->assertRedirect('/sales-kpi');
        $response->assertSessionHas('dashboard_authenticated', true);
        $this->assertDatabaseHas('dashboard_devices', [
            'label' => 'New Laptop',
            'status' => DashboardDevice::STATUS_APPROVED,
        ], 'sqlite_targets');
    }

    public function test_invalid_credentials_return_generic_error(): void
    {
        DashboardUser::query()->create([
            'name' => 'Administrator',
            'username' => 'admin',
            'password' => Hash::make('safe-password-123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->from('/login')->post('/login', [
            'username' => 'wrong',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
          ->assertSessionHasErrors('login');
    }

    public function test_protected_route_redirects_to_login(): void
    {
        $this->get('/sales-kpi')->assertRedirect('/login');
    }

    public function test_logout_invalidates_dashboard_session(): void
    {
        $this->actingAsDashboardAdmin()->post('/logout')->assertRedirect('/login');
        $this->assertFalse(session()->has('dashboard_authenticated'));
    }
}
