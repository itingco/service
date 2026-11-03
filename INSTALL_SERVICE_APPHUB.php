<?php

declare(strict_types=1);

/**
 * Install ONLY the Service Quotation draft tables on DB_AppHub.
 * Dashboard permission migration stays in SQLite_targets (dashboard security).
 * Never invokes migration on the default connection (AS_INGCO).
 * Usage: php INSTALL_SERVICE_APPHUB.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = __DIR__;
if (!is_file($root.'/artisan') || !is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "GAGAL: script harus diletakkan di root Laravel bersama artisan dan vendor.\n");
    exit(1);
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$schemaPath = 'database/migrations/2026_10_20_000900_create_service_quotations.php';
$permissionPath = 'database/migrations/2026_10_20_000910_grant_service_quotation_permission.php';

try {
    $migrationFiles = [
        $schemaPath => 'sqlsrv_apphub',
        $permissionPath => 'sqlite_targets',
    ];
    foreach ($migrationFiles as $path => $connection) {
        $file = $root.'/'.$path;
        if (!is_file($file)) {
            throw new \RuntimeException('Migration tidak ditemukan: '.$path);
        }
        $contents = file_get_contents($file);
        if ($contents === false || !preg_match(
            '/protected\s+\$connection\s*=\s*[\'\"]'.preg_quote($connection, '/').'[\'\"]\s*;/',
            $contents
        )) {
            throw new \RuntimeException('Migration '.$path.' bukan untuk '.$connection.'. Stop, tidak ada perubahan database.');
        }
    }

    $erp = \Illuminate\Support\Facades\DB::connection('sqlsrv_apphub');
    if ($erp->getDriverName() !== 'sqlsrv') {
        throw new \RuntimeException('sqlsrv_apphub bukan koneksi SQL Server.');
    }
    $actual = (string) ($erp->selectOne('SELECT DB_NAME() AS ActiveDatabase')->ActiveDatabase ?? '');
    if (strcasecmp($actual, 'DB_AppHub') !== 0) {
        throw new \RuntimeException('DIBATALKAN: sqlsrv_apphub menunjuk ke '.($actual ?: '(tidak diketahui)').', bukan DB_AppHub.');
    }

    $local = \Illuminate\Support\Facades\DB::connection('sqlite_targets');
    if ($local->getDriverName() !== 'sqlite') {
        throw new \RuntimeException('sqlite_targets bukan SQLite; migrasi permission tidak aman.');
    }
    $localPath = (string) $local->getDatabaseName();
    if ($localPath !== ':memory:' && !is_file($localPath)) {
        throw new \RuntimeException('Database SQLite untuk permission tidak ditemukan: '.$localPath);
    }

    $schema = \Illuminate\Support\Facades\Schema::connection('sqlsrv_apphub');
    $names = ['service_quotation_counters', 'service_quotations', 'service_quotation_lines'];
    $present = array_values(array_filter($names, static fn (string $name): bool => $schema->hasTable($name)));
    $migrationName = '2026_10_20_000900_create_service_quotations';
    $recorded = $schema->hasTable('migrations')
        && $erp->table('migrations')->where('migration', $migrationName)->exists();

    if ($present !== [] && (!$recorded || count($present) !== count($names))) {
        throw new \RuntimeException('DB_AppHub sudah mempunyai sebagian/semua tabel penawaran, namun status migration tidak konsisten: '.implode(', ', $present).'. Proses dihentikan supaya tidak menimpa data.');
    }
    if ($recorded && $present === []) {
        throw new \RuntimeException('Migration pernah tercatat tetapi tabel tidak ditemukan. Periksa DB_AppHub secara manual.');
    }

    echo "Target draft: DB_AppHub (SQL Server) [TERVERIFIKASI]\n";
    echo "Target permission dashboard: SQLite lokal [TERVERIFIKASI]\n";
    echo "AS_INGCO / AS_SMI: tidak digunakan untuk migration apa pun.\n\n";

    foreach ([
        ['sqlsrv_apphub', $schemaPath],
        ['sqlite_targets', $permissionPath],
    ] as [$connection, $path]) {
        echo "Menjalankan hanya: {$path} pada {$connection}\n";
        $status = $kernel->call('migrate', [
            '--database' => $connection,
            '--path' => $path,
            '--force' => true,
        ]);
        echo $kernel->output()."\n";
        if ($status !== 0) {
            throw new \RuntimeException('Migration gagal: '.$path.'. Periksa hak CREATE TABLE pada DB_AppHub jika ini migration pertama.');
        }
    }

    foreach ($names as $name) {
        if (!$schema->hasTable($name)) {
            throw new \RuntimeException('Tabel tidak ditemukan setelah migration: '.$name);
        }
    }
    echo "SELESAI: semua tabel Penawaran Service berada di DB_AppHub.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "GAGAL: {$e->getMessage()}\n");
    exit(1);
}
