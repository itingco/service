<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'sqlsrv_apphub';
    private function verify(): void {
        $db = DB::connection($this->connection);
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp((string) ($db->selectOne('SELECT DB_NAME() AS db')->db ?? ''), 'DB_AppHub') !== 0) {
            throw new RuntimeException('Migrasi diskon hanya diizinkan pada DB_AppHub SQL Server.');
        }
    }
    public function up(): void {
        $this->verify();
        Schema::connection($this->connection)->table('service_quotation_lines', function (Blueprint $table): void {
            $table->string('discount_type', 10)->default('none');
            $table->decimal('discount_value', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
        });
    }
    public function down(): void {
        $this->verify();
        Schema::connection($this->connection)->table('service_quotation_lines', function (Blueprint $table): void {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
