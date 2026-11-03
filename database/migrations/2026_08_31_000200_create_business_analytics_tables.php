<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlite_targets';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

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

        if ($schema->hasTable('dashboard_role_menus') && $schema->hasTable('dashboard_roles')) {
            $db = DB::connection($this->connection);
            $now = now();
            foreach (['direksi', 'sales', 'rnd'] as $role) {
                if (!$db->table('dashboard_roles')->where('role_key', $role)->exists()) {
                    continue;
                }
                $db->table('dashboard_role_menus')->insertOrIgnore([
                    'role_key' => $role,
                    'menu_key' => 'business_analytics',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        if ($schema->hasTable('dashboard_role_menus')) {
            DB::connection($this->connection)->table('dashboard_role_menus')
                ->where('menu_key', 'business_analytics')
                ->delete();
        }
        $schema->dropIfExists('business_analytics_sync_runs');
        $schema->dropIfExists('business_analytics_targets');
        $schema->dropIfExists('business_analytics_monthly_sales');
    }
};
