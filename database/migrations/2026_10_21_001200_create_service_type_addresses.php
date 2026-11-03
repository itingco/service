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
        if (Schema::connection($this->connection)->hasTable('service_type_addresses')) return;

        Schema::connection($this->connection)->create('service_type_addresses', function (Blueprint $table): void {
            $table->id();
            $table->string('erp_source', 10);
            $table->string('doc_type_code', 100);
            $table->text('address')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['erp_source', 'doc_type_code'], 'uq_service_type_addresses_source_code');
        });
    }

    public function down(): void
    {
        $this->assertAppHub();
        Schema::connection($this->connection)->dropIfExists('service_type_addresses');
    }

    private function assertAppHub(): void
    {
        $db = DB::connection($this->connection);
        if ($db->getDriverName() !== 'sqlsrv') {
            throw new \RuntimeException('Migration master alamat hanya untuk SQL Server DB_AppHub.');
        }
        $name = (string) ($db->selectOne('SELECT DB_NAME() AS ActiveDatabase')->ActiveDatabase ?? '');
        if (strcasecmp($name, 'DB_AppHub') !== 0) {
            throw new \RuntimeException('Migration DIBATALKAN: database aktif '.($name ?: '(kosong)').' bukan DB_AppHub.');
        }
    }
};
