<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'sqlite_targets';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('dashboard_role_menus')) return;
        $db = DB::connection($this->connection);
        $roles = $db->table('dashboard_role_menus')->where('menu_key', 'sparepart_search')->distinct()->pluck('role_key');
        foreach ($roles as $role) {
            $db->table('dashboard_role_menus')->insertOrIgnore([
                'role_key' => $role, 'menu_key' => 'service_quotations', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('dashboard_role_menus')) {
            DB::connection($this->connection)->table('dashboard_role_menus')->where('menu_key', 'service_quotations')->delete();
        }
    }
};
