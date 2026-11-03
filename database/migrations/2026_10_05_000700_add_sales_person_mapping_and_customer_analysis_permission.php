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
        if (Schema::connection($this->connection)->hasTable('dashboard_users')
            && !Schema::connection($this->connection)->hasColumn('dashboard_users', 'sales_person_id')) {
            Schema::connection($this->connection)->table('dashboard_users', function (Blueprint $table): void {
                $table->integer('sales_person_id')->nullable()->index();
            });
        }

        if (!Schema::connection($this->connection)->hasTable('dashboard_role_menus')) {
            return;
        }

        $db = DB::connection($this->connection);
        $now = now();
        foreach (['sales', 'direksi'] as $roleKey) {
            if (Schema::connection($this->connection)->hasTable('dashboard_roles')
                && !$db->table('dashboard_roles')->where('role_key', $roleKey)->exists()) {
                continue;
            }

            $db->table('dashboard_role_menus')->insertOrIgnore([
                'role_key' => $roleKey,
                'menu_key' => 'customer_analysis',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('dashboard_role_menus')) {
            DB::connection($this->connection)
                ->table('dashboard_role_menus')
                ->where('menu_key', 'customer_analysis')
                ->delete();
        }

        if (Schema::connection($this->connection)->hasTable('dashboard_users')
            && Schema::connection($this->connection)->hasColumn('dashboard_users', 'sales_person_id')) {
            Schema::connection($this->connection)->table('dashboard_users', function (Blueprint $table): void {
                $table->dropColumn('sales_person_id');
            });
        }
    }
};
