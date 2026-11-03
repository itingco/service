<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlite_targets';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        // Repair migration: semua create memakai hasTable agar aman untuk database
        // yang sudah normal maupun database lama yang migration history-nya tidak sinkron.
        if (!$schema->hasTable('business_analytics_monthly_sales')) {
            $schema->create('business_analytics_monthly_sales', function (Blueprint $table): void {
                $table->id();
                $table->string('period', 7)->index();
                $table->string('source', 10)->index();
                $table->string('sales_line', 160)->default('LAINNYA')->index();
                $table->string('customer_code', 80)->default('');
                $table->string('customer_name', 240)->default('');
                $table->string('salesperson_name', 180)->default('');
                $table->string('item_code', 100)->default('');
                $table->string('item_name', 255)->default('');
                $table->decimal('sales_amount', 22, 2)->default(0);
                $table->decimal('quantity', 22, 4)->default(0);
                $table->unsignedInteger('invoice_count')->default(0);
                $table->timestamps();

                $table->index(['period', 'source', 'sales_line'], 'ba_month_source_line_idx');
                $table->index(['period', 'customer_code'], 'ba_month_customer_idx');
                $table->index(['period', 'salesperson_name'], 'ba_month_salesperson_idx');
                $table->index(['period', 'item_code'], 'ba_month_item_idx');
            });
        }

        if (!$schema->hasTable('business_analytics_targets')) {
            $schema->create('business_analytics_targets', function (Blueprint $table): void {
                $table->id();
                $table->string('period', 7)->index();
                $table->string('scope_level', 20)->index();
                $table->string('source', 10)->default('ALL')->index();
                $table->string('dimension_key', 200)->default('TOTAL')->index();
                $table->decimal('official_target', 22, 2)->nullable();
                $table->timestamps();
                $table->unique(['period', 'scope_level', 'source', 'dimension_key'], 'ba_target_unique');
            });
        }

        if (!$schema->hasTable('business_analytics_sync_runs')) {
            $schema->create('business_analytics_sync_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 10)->index();
                $table->string('period', 7)->index();
                $table->string('status', 20)->index();
                $table->unsignedInteger('source_rows')->default(0);
                $table->unsignedInteger('aggregate_rows')->default(0);
                $table->text('message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('business_analytics_daily_sales')) {
            $schema->create('business_analytics_daily_sales', function (Blueprint $table): void {
                $table->id();
                $table->date('sales_date')->index();
                $table->string('source', 10)->index();
                $table->string('sales_line', 160)->default('LAINNYA')->index();
                $table->string('customer_code', 80)->default('');
                $table->string('customer_name', 240)->default('');
                $table->string('salesperson_name', 180)->default('');
                $table->string('item_code', 100)->default('')->index();
                $table->string('item_name', 255)->default('');
                $table->decimal('sales_amount', 22, 2)->default(0);
                $table->decimal('quantity', 22, 4)->default(0);
                $table->unsignedInteger('invoice_count')->default(0);
                $table->timestamps();

                $table->index(['sales_date', 'source', 'item_code'], 'ba_daily_date_source_item_idx');
                $table->index(['sales_date', 'customer_code'], 'ba_daily_customer_idx');
                $table->index(['sales_date', 'sales_line'], 'ba_daily_line_idx');
            });
        }

        if (!$schema->hasTable('business_analytics_web_sync_jobs')) {
            $schema->create('business_analytics_web_sync_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('status', 40)->index();
                $table->unsignedInteger('total_steps')->default(72);
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->string('command', 255)->default('php artisan analytics:sync --months=36');
                $table->text('log_path')->nullable();
                $table->text('message')->nullable();
                $table->timestamp('started_at')->nullable()->index();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Sengaja no-op. Ini migration repair dan sebagian tabel mungkin sudah ada
        // sebelum migration ini dijalankan; rollback tidak boleh menghapus data lama.
    }
};
