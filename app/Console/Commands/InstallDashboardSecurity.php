<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DashboardUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class InstallDashboardSecurity extends Command
{
    protected $signature = 'dashboard-security:install
        {--username= : Username administrator awal}
        {--password= : Password administrator awal}
        {--name= : Nama administrator awal}
        {--reset-password : Timpa password administrator yang sudah ada}';

    protected $description = 'Menyiapkan database user terpisah untuk aplikasi Service Sparepart.';

    public function handle(): int
    {
        $connection = (string) config('dashboard_security.connection', 'sqlite_targets');
        $this->ensureSqliteFileExists($connection);

        // Jangan menjalankan semua migration project asal.
        // Project asal memiliki migration Service yang menargetkan DB_AppHub.
        // Karena user Service memakai SQLite baru, hanya migration security berikut yang dijalankan.
        $securityMigrations = [
            'database/migrations/2026_08_06_000100_create_dashboard_users_table.php',
            'database/migrations/2026_08_06_000110_create_dashboard_devices_table.php',
            'database/migrations/2026_08_06_000120_create_dashboard_audit_tables.php',
            'database/migrations/2026_08_23_000130_create_dashboard_roles_and_menus.php',
        ];

        foreach ($securityMigrations as $migration) {
            if (!is_file(base_path($migration))) {
                $this->components->error('Migration security tidak ditemukan: '.$migration);
                return self::FAILURE;
            }
        }

        $this->components->info('Menjalankan migration security Service pada '.$connection.'...');
        $exitCode = Artisan::call('migrate', [
            '--database' => $connection,
            '--path' => $securityMigrations,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->output->write(Artisan::output());
        if ($exitCode !== self::SUCCESS) {
            $this->components->error('Migration security gagal.');
            return self::FAILURE;
        }

        $username = strtolower(trim((string) ($this->option('username') ?: config('dashboard_auth.username'))));
        $password = (string) ($this->option('password') ?: config('dashboard_auth.password'));
        $name = trim((string) ($this->option('name') ?: config('dashboard_auth.name', 'Service Administrator')));

        if ($username === '') {
            $this->components->error('DASHBOARD_ADMIN_USER wajib diisi.');
            return self::FAILURE;
        }

        /** @var DashboardUser|null $user */
        $user = DashboardUser::query()->where('username', $username)->first();
        $requiresPassword = $user === null || (bool) $this->option('reset-password');

        if ($requiresPassword) {
            $passwordMinLength = max(1, (int) config('dashboard_security.password_min_length', 8));
            $validPassword = $password !== '' && mb_strlen($password) >= $passwordMinLength;

            if (!$validPassword || str_contains(strtolower($password), 'change-this-password')) {
                $this->components->error("Password admin minimal {$passwordMinLength} karakter dan jangan memakai password contoh.");
                return self::FAILURE;
            }
        }

        if ($user === null) {
            $user = DashboardUser::query()->create([
                'name' => $name !== '' ? $name : 'Service Administrator',
                'username' => $username,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
            ]);
            $this->components->info('Akun administrator Service dibuat: '.$user->username);
        } else {
            $updates = [
                'name' => $name !== '' ? $name : $user->name,
                'role' => 'admin',
                'is_active' => true,
            ];
            if ((bool) $this->option('reset-password')) {
                $updates['password'] = Hash::make($password);
            }
            $user->forceFill($updates)->save();
            $this->components->info('Akun administrator Service diperbarui: '.$user->username);
        }

        $this->newLine();
        $this->components->info('Database user Service siap dan terpisah dari Sales Dashboard.');

        return self::SUCCESS;
    }

    private function ensureSqliteFileExists(string $connection): void
    {
        $driver = (string) config("database.connections.{$connection}.driver");
        if ($driver !== 'sqlite') {
            return;
        }

        $path = (string) config("database.connections.{$connection}.database");
        if ($path === '' || $path === ':memory:') {
            return;
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Tidak dapat membuat folder SQLite: '.$directory);
        }

        if (!is_file($path) && touch($path) === false) {
            throw new RuntimeException('Tidak dapat membuat file SQLite: '.$path);
        }
    }
}
