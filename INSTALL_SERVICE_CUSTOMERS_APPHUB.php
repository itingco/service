<?php

declare(strict_types=1);

/**
 * Run from project root: php INSTALL_SERVICE_CUSTOMERS_APPHUB.php
 * Only runs the service_type_customers migration on SQL Server DB_AppHub.
 * Never runs migrations against the default sqlsrv_ingco connection.
 */
if (PHP_SAPI !== 'cli') exit('CLI only');
if (!is_file(__DIR__.'/artisan') || !is_file(__DIR__.'/vendor/autoload.php')) {
    fwrite(STDERR, "Installer harus diletakkan di root Laravel (sejajar artisan).\n");
    exit(1);
}
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $path = 'database/migrations/2026_10_22_001300_create_service_type_customers.php';
    $source = file_get_contents(__DIR__.'/'.$path);
    if ($source === false || !str_contains($source, "protected \$connection = 'sqlsrv_apphub'")) {
        throw new RuntimeException('Migration mapping customer tidak ditemukan atau koneksi salah.');
    }
    $db = \Illuminate\Support\Facades\DB::connection('sqlsrv_apphub');
    $database = (string) ($db->selectOne('SELECT DB_NAME() AS ActiveDatabase')->ActiveDatabase ?? '');
    if ($db->getDriverName() !== 'sqlsrv' || strcasecmp($database, 'DB_AppHub') !== 0) {
        throw new RuntimeException('STOP! Target database adalah '.$database.', seharusnya DB_AppHub.');
    }
    $status = $kernel->call('migrate', [
        '--database' => 'sqlsrv_apphub', '--path' => $path, '--force' => true,
    ]);
    echo $kernel->output();
    if ($status !== 0) throw new RuntimeException('Migration mapping gagal.');
    if (!\Illuminate\Support\Facades\Schema::connection('sqlsrv_apphub')->hasTable('service_type_customers')) {
        throw new RuntimeException('Tabel mapping belum ditemukan setelah migration.');
    }
    echo "OK: service_type_customers tersedia di DB_AppHub.\n";
    echo "Tidak ada struktur maupun data AS_INGCO / AS_SMI yang diubah.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'GAGAL: '.$e->getMessage()."\n");
    exit(1);
}
