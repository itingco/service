<?php

declare(strict_types=1);

/**
 * Standalone smoke test: no ERP connection and no database changes.
 * Run: php tests/Smoke/service_type_address_print_test.php
 */
$root = dirname(__DIR__, 2);
$load = static function (string $path) use ($root): string {
    $code = file_get_contents($root.'/'.$path);
    if ($code === false) throw new RuntimeException('Tidak ada: '.$path);
    return $code;
};
$assert = static function (bool $passed, string $message): void {
    if (!$passed) throw new RuntimeException('FAIL: '.$message);
    fwrite(STDOUT, 'PASS: '.$message."\n");
};
$routes = $load('routes/service_quotations.php');
$controller = $load('app/Http/Controllers/ServiceQuotationController.php');
$repo = $load('app/Services/ServiceQuotations/ServiceTypeAddressRepository.php');
$print = $load('resources/views/service-quotations/print.blade.php');
$editor = $load('resources/views/service-quotations/service-centers.blade.php');
$migration = $load('database/migrations/2026_10_21_001200_create_service_type_addresses.php');
$install = $load('INSTALL_SERVICE_ADDRESS_APPHUB.php');

$assert(str_contains($routes, "'/service-centers'") && str_contains($routes, "service-centers.save"), 'Route master alamat GET/POST tersedia');
$assert(str_contains($controller, 'serviceCenters(') && str_contains($controller, 'saveServiceCenter('), 'Controller master alamat tersedia');
$assert(str_contains($controller, '$addresses->address(') && str_contains($controller, 'serviceCenterAddress'), 'Print mengambil alamat sesuai database dan Service Type dokumen');
$assert(str_contains($controller, '$types->all($data[\'erp_source\'])'), 'Kode Service Type divalidasi terhadap USP ERP sumber');
$assert(str_contains($repo, "DB::connection('sqlsrv_apphub')") && str_contains($repo, "'erp_source' => \$source"), 'Master alamat memakai DB_AppHub dan kunci sumber ERP');
$assert(str_contains($migration, "protected \$connection = 'sqlsrv_apphub'") && str_contains($migration, "DB_NAME()") && str_contains($migration, "service_type_addresses"), 'Migration hanya menuju DB_AppHub dengan pemeriksaan database');
$assert(str_contains($install, "'--database' => 'sqlsrv_apphub'") && !str_contains($install, "'--database' => 'sqlsrv_ingco'"), 'Installer memakai koneksi DB_AppHub');
$assert(str_contains($editor, '<textarea') && str_contains($editor, 'name="address"') && str_contains($editor, 'name="doc_type_code"'), 'Editor menggunakan satu textarea alamat per Service Type');
$assert(strpos($print, '<div class="logo-area">') < strpos($print, '<div class="service-center-address">'), 'Header cetak: logo di kiri, alamat di kanan');
$assert(str_contains($print, "strtoupper((string) \$header->erp_source) === 'SMI'") && str_contains($print, 'ingco-indonesia.png') && str_contains($print, 'wadfow.png'), 'Logo berganti sesuai AS_SMI atau AS_INGCO');
$assert(str_contains($print, '14 (empat belas) hari kerja') && str_contains($print, 'proses perbaikan dianggap batal'), 'Ketentuan konfirmasi 14 hari kerja tampil pada cetak');
$assert(str_contains($print, 'tanpa biaya (gratis)'), 'Catatan jasa perbaikan gratis tetap ditampilkan');
fwrite(STDOUT, "OK: smoke test selesai (tanpa koneksi ke SQL Server).\n");
