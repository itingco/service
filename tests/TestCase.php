<?php

namespace Tests;

use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use App\Services\Security\DeviceTokenService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function actingAsDashboardAdmin(): static
    {
        if (!Schema::connection('sqlite_targets')->hasTable('dashboard_users')) {
            $this->artisan('migrate', ['--database' => 'sqlite_targets'])->assertExitCode(0);
        }

        $user = DashboardUser::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Test Administrator',
                'password' => Hash::make('test-password-123'),
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        $token = 'test-device-token-'.str_repeat('x', 72);
        $hash = $this->app->make(DeviceTokenService::class)->hash($token);
        $device = DashboardDevice::query()->updateOrCreate(
            ['token_hash' => $hash],
            [
                'user_id' => $user->getKey(),
                'label' => 'Test Device',
                'status' => DashboardDevice::STATUS_APPROVED,
                'browser' => 'Test Browser',
                'platform' => 'Test OS',
                'approved_at' => now(),
                'approved_by' => $user->getKey(),
                'last_seen_at' => now(),
            ],
        );

        return $this
            ->withCookie((string) config('dashboard_security.device_cookie'), $token)
            ->withSession([
                'dashboard_authenticated' => true,
                'dashboard_username' => $user->username,
                'dashboard_user_id' => $user->getKey(),
                'dashboard_user_name' => $user->name,
                'dashboard_user_role' => $user->role,
                'dashboard_device_id' => $device->getKey(),
                'dashboard_last_activity_at' => time(),
            ]);
    }
}
