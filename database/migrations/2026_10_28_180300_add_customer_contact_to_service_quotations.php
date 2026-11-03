<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'sqlsrv_apphub';

    public function up(): void
    {
        $db = DB::connection($this->connection);
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp((string) ($db->selectOne('SELECT DB_NAME() AS db')->db ?? ''), 'DB_AppHub') !== 0) {
            throw new RuntimeException('Migration ini hanya boleh berjalan pada SQL Server DB_AppHub.');
        }
        if (!Schema::connection($this->connection)->hasTable('service_quotations')) {
            throw new RuntimeException('Tabel DB_AppHub.service_quotations belum tersedia.');
        }
        if (!Schema::connection($this->connection)->hasColumn('service_quotations', 'customer_phone')) {
            Schema::connection($this->connection)->table('service_quotations', static function (Blueprint $table): void {
                $table->string('customer_phone', 50)->nullable();
            });
        }
        if (!Schema::connection($this->connection)->hasColumn('service_quotations', 'customer_address')) {
            Schema::connection($this->connection)->table('service_quotations', static function (Blueprint $table): void {
                $table->string('customer_address', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Reversible for this migration only; never touch the ERP tables.
        $db = DB::connection($this->connection);
        if (strcasecmp((string) ($db->selectOne('SELECT DB_NAME() AS db')->db ?? ''), 'DB_AppHub') !== 0) {
            throw new RuntimeException('Bukan DB_AppHub; rollback dibatalkan.');
        }
        foreach (['customer_phone', 'customer_address'] as $column) {
            if (Schema::connection($this->connection)->hasColumn('service_quotations', $column)) {
                Schema::connection($this->connection)->table('service_quotations', static function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
