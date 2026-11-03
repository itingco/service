<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'sqlsrv_apphub';
    public function up(): void {
        $db = DB::connection($this->connection);
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp((string)($db->selectOne('SELECT DB_NAME() AS db')->db ?? ''), 'DB_AppHub') !== 0) {
            throw new RuntimeException('Migration hanya boleh dijalankan pada SQL Server DB_AppHub.');
        }
        $schema = Schema::connection($this->connection);
        if (!$schema->hasTable('service_quotations')) throw new RuntimeException('Tabel service_quotations belum tersedia.');
        foreach (['serial_number' => 150, 'service_description' => 1000, 'accessories' => 1000, 'service_status' => 60] as $column => $length) {
            if (!$schema->hasColumn('service_quotations', $column)) {
                $schema->table('service_quotations', static function (Blueprint $table) use ($column, $length): void {
                    $table->string($column, $length)->nullable();
                });
            }
        }
    }
    public function down(): void {
        throw new RuntimeException('Rollback otomatis dinonaktifkan untuk mencegah penghapusan data serial number dan penerimaan service.');
    }
};
