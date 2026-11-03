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
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp((string) ($db->selectOne('SELECT DB_NAME() AS db_name')->db_name ?? ''), 'DB_AppHub') !== 0)
            throw new RuntimeException('Migration hanya boleh dijalankan di DB_AppHub.');
        if (!Schema::connection($this->connection)->hasColumn('service_quotations', 'approval_status')) {
            Schema::connection($this->connection)->table('service_quotations', function (Blueprint $table): void {
                $table->string('approval_status', 20)->default('pending')->index();
            });
        }
    }
    public function down(): void
    {
        $db = DB::connection($this->connection);
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp((string) ($db->selectOne('SELECT DB_NAME() AS db_name')->db_name ?? ''), 'DB_AppHub') !== 0)
            throw new RuntimeException('Rollback hanya boleh dijalankan di DB_AppHub.');
        if (Schema::connection($this->connection)->hasColumn('service_quotations', 'approval_status'))
            Schema::connection($this->connection)->table('service_quotations', fn (Blueprint $table) => $table->dropColumn('approval_status'));
    }
};
