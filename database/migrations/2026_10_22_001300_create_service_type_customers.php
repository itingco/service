<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv_apphub';

    public function up(): void
    {
        $this->assertAppHub();
        if (Schema::connection($this->connection)->hasTable('service_type_customers')) return;
        Schema::connection($this->connection)->create('service_type_customers', function (Blueprint $table): void {
            $table->id();
            $table->string('erp_source', 10);
            $table->string('doc_type_code', 100);
            $table->unsignedBigInteger('customer_id');
            $table->string('customer_code', 100)->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['erp_source', 'doc_type_code'], 'uq_service_type_customers_source_type');
        });
    }

    public function down(): void
    {
        $this->assertAppHub();
        Schema::connection($this->connection)->dropIfExists('service_type_customers');
    }

    private function assertAppHub(): void
    {
        $db = DB::connection($this->connection);
        $actual = (string) ($db->selectOne('SELECT DB_NAME() AS db_name')->db_name ?? '');
        if ($db->getDriverName() !== 'sqlsrv' || strcasecmp($actual, 'DB_AppHub') !== 0) {
            throw new RuntimeException('Migration dibatalkan: koneksi sqlsrv_apphub tidak mengarah ke DB_AppHub.');
        }
    }
};
