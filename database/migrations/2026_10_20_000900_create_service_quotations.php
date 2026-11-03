<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Tiga tabel baru hanya dibuat di DB_AppHub, bukan database ERP.
    protected $connection = 'sqlsrv_apphub';

    public function up(): void
    {
        $this->assertAppHub();
        Schema::connection($this->connection)->create('service_quotation_counters', function (Blueprint $table): void {
            $table->string('series', 120)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });
        Schema::connection($this->connection)->create('service_quotations', function (Blueprint $table): void {
            $table->id();
            $table->string('quote_no', 100)->unique();
            $table->string('customer_name', 200);
            $table->string('erp_source', 10);
            $table->string('doc_type_id', 100);
            $table->string('doc_type_code', 100);
            $table->string('doc_type_label', 200);
            $table->string('damaged_item_code', 100);
            $table->string('damaged_item_name', 255);
            $table->text('complaint')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->string('erp_doc_no', 120)->nullable();
            $table->timestamp('erp_sent_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['doc_type_code', 'created_at']);
        });
        Schema::connection($this->connection)->create('service_quotation_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_quotation_id')->index();
            $table->unsignedInteger('line_no');
            $table->string('requested_sparepart_code', 100);
            $table->string('sparepart_code', 100);
            $table->string('sparepart_name', 255);
            $table->string('source', 10);
            $table->integer('warehouse_id');
            $table->string('warehouse_code', 100)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->decimal('available_qty_at_quote', 18, 4);
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->unsignedTinyInteger('similar_depth')->default(0);
            $table->timestamps();
            $table->unique(['service_quotation_id', 'line_no']);
            $table->foreign('service_quotation_id')->references('id')->on('service_quotations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->assertAppHub();
        Schema::connection($this->connection)->dropIfExists('service_quotation_lines');
        Schema::connection($this->connection)->dropIfExists('service_quotations');
        Schema::connection($this->connection)->dropIfExists('service_quotation_counters');
    }
    private function assertAppHub(): void
    {
        $connection = DB::connection($this->connection);
        if ($connection->getDriverName() !== 'sqlsrv') {
            throw new \RuntimeException('Migration Penawaran Service hanya untuk SQL Server DB_AppHub.');
        }
        $active = (string) ($connection->selectOne('SELECT DB_NAME() AS ActiveDatabase')->ActiveDatabase ?? '');
        if (strcasecmp($active, 'DB_AppHub') !== 0) {
            throw new \RuntimeException('Migration dibatalkan: database aktif '.($active ?: '(kosong)').' bukan DB_AppHub.');
        }
    }
};
