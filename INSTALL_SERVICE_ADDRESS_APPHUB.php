<?php

declare(strict_types=1);

/** php INSTALL_SERVICE_ADDRESS_APPHUB.php  -- only DB_AppHub, never AS_INGCO / AS_SMI */
if (PHP_SAPI !== 'cli') exit('CLI only');
if (!is_file(__DIR__.'/artisan') || !is_file(__DIR__.'/vendor/autoload.php')) {
    fwrite(STDERR, "Letakkan installer di root Laravel (sejajar artisan).\n");
    exit(1);
}
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $path = 'database/migrations/2026_10_21_001200_create_service_type_addresses.php';
    $contents = file_get_contents(__DIR__.'/'.$path);
    if ($contents === false || !str_contains($contents, "protected \$connection = 'sqlsrv_apphub'")) {
        throw new RuntimeException('Migration tidak ditemukan atau koneksi bukan sqlsrv_apphub.');
    }
    $db = \Illuminate\Support\Facades\DB::connection('sqlsrv_apphub');
    $name = (string) ($db->selectOne('SELECT DB_NAME() AS ActiveDatabase')->ActiveDatabase ?? '');
    if ($db->getDriverName() !== 'sqlsrv' || strcasecmp($name, 'DB_AppHub') !== 0) {
        throw new RuntimeException('STOP: database tujuan '.($name ?: '(tidak ditemukan)').' bukan DB_AppHub.');
    }
    $status = $kernel->call('migrate', [
        '--database' => 'sqlsrv_apphub', '--path' => $path, '--force' => true,
    ]);
    echo $kernel->output();
    if ($status !== 0) throw new RuntimeException('Migration master alamat gagal.');
    if (!\Illuminate\Support\Facades\Schema::connection('sqlsrv_apphub')->hasTable('service_type_addresses')) {
        throw new RuntimeException('Tabel service_type_addresses belum ditemukan.');
    }
    echo "SUKSES: master alamat service_type_addresses tersedia di DB_AppHub.\n";
    echo "AS_INGCO dan AS_SMI tidak dimodifikasi.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'GAGAL: '.$e->getMessage()."\n");
    exit(1);
}
