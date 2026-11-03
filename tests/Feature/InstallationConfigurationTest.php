<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class InstallationConfigurationTest extends TestCase
{
    public function test_install_scripts_prepare_sqlite_authentication_and_target_migration(): void
    {
        $install = (string) file_get_contents(base_path('INSTALL_WINDOWS.bat'));
        $update = (string) file_get_contents(base_path('UPDATE_AFTER_OVERWRITE.bat'));
        $requirements = (string) file_get_contents(base_path('CHECK_REQUIREMENTS.bat'));
        $env = (string) file_get_contents(base_path('.env.example'));

        self::assertStringContainsString('database\\database.sqlite', $install);
        self::assertStringContainsString('migrate --database=sqlite_targets --force', $install);
        self::assertStringContainsString('sales-kpi:import-legacy-targets', $install);
        self::assertStringContainsString('migrate --database=sqlite_targets --force', $update);
        self::assertStringContainsString('sales-kpi:import-legacy-targets', $update);
        self::assertStringContainsString('pdo_sqlite', $requirements);
        self::assertStringContainsString('sqlite3', $requirements);
        self::assertStringContainsString('TARGET_DB_DATABASE=database/database.sqlite', $env);
        self::assertStringContainsString('DASHBOARD_ADMIN_USER=', $env);
        self::assertStringContainsString('DASHBOARD_ADMIN_PASSWORD=', $env);
    }
}
