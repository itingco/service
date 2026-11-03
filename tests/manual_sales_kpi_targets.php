<?php

declare(strict_types=1);

use App\Models\SalesKpiTarget;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$autoload = __DIR__.'/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "[FAIL] vendor/autoload.php tidak ditemukan. Jalankan composer install.\n");
    exit(1);
}

require $autoload;
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    if ($condition) {
        echo "[PASS] {$message}\n";
        return;
    }

    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
};

try {
    DB::connection('sqlite_targets')->getPdo();
    $check(true, 'Koneksi sqlite_targets dapat dibuka.');
} catch (Throwable $exception) {
    $check(false, 'Koneksi sqlite_targets gagal: '.$exception->getMessage());
}

try {
    $check(
        Schema::connection('sqlite_targets')->hasTable('sales_kpi_targets'),
        'Tabel sales_kpi_targets tersedia.',
    );
} catch (Throwable $exception) {
    $check(false, 'Pemeriksaan tabel gagal: '.$exception->getMessage());
}

try {
    $connection = DB::connection('sqlite_targets');
    $connection->beginTransaction();
    SalesKpiTarget::query()->updateOrCreate(
        ['sales_line' => '__MANUAL_TEST__'],
        ['monthly_target' => 12345],
    );
    $inserted = SalesKpiTarget::query()->where('sales_line', '__MANUAL_TEST__')->exists();
    $connection->rollBack();
    $persisted = SalesKpiTarget::query()->where('sales_line', '__MANUAL_TEST__')->exists();
    $check($inserted && !$persisted, 'Insert target sementara berhasil dan rollback bekerja.');
} catch (Throwable $exception) {
    if (isset($connection) && $connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
    $check(false, 'Uji transaksi SQLite gagal: '.$exception->getMessage());
}

$check(trim((string) config('dashboard_auth.username')) !== '', 'DASHBOARD_ADMIN_USER tersedia.');
$check(trim((string) config('dashboard_auth.password')) !== '', 'DASHBOARD_ADMIN_PASSWORD tersedia.');

foreach (['dashboard.login', 'sales-kpi.index', 'sales-kpi.targets.index'] as $routeName) {
    $check(Route::has($routeName), "Route {$routeName} tersedia.");
}

if ($failures > 0) {
    fwrite(STDERR, "manual_sales_kpi_targets: {$failures} pemeriksaan gagal.\n");
    exit(1);
}

echo "manual_sales_kpi_targets: OK\n";
