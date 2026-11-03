<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlite_targets';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('dashboard_roles')
            || !Schema::connection($this->connection)->hasTable('dashboard_role_menus')) {
            return;
        }

        $db = DB::connection($this->connection);
        $roleKeys = $db->table('dashboard_role_menus')
            ->where('menu_key', 'business_analytics')
            ->pluck('role_key')
            ->map(static fn (mixed $roleKey): string => strtolower(trim((string) $roleKey)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($roleKeys === []) {
            return;
        }

        $now = now();
        foreach ($roleKeys as $roleKey) {
            foreach (['product_focus', 'similar_items'] as $menuKey) {
                $db->table('dashboard_role_menus')->insertOrIgnore([
                    'role_key' => $roleKey,
                    'menu_key' => $menuKey,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable('dashboard_role_menus')) {
            return;
        }

        $db = DB::connection($this->connection);
        $roleKeys = $db->table('dashboard_role_menus')
            ->where('menu_key', 'business_analytics')
            ->pluck('role_key')
            ->map(static fn (mixed $roleKey): string => strtolower(trim((string) $roleKey)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($roleKeys === []) {
            return;
        }

        $db->table('dashboard_role_menus')
            ->whereIn('role_key', $roleKeys)
            ->whereIn('menu_key', ['product_focus', 'similar_items'])
            ->delete();
    }
};
