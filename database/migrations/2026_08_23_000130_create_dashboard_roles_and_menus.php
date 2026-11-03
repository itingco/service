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
        Schema::connection($this->connection)->create('dashboard_roles', function (Blueprint $table): void {
            $table->string('role_key', 30)->primary();
            $table->string('name', 120);
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('dashboard_role_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('role_key', 30)->index();
            $table->string('menu_key', 60)->index();
            $table->timestamps();
            $table->unique(['role_key', 'menu_key']);
            $table->foreign('role_key')
                ->references('role_key')
                ->on('dashboard_roles')
                ->cascadeOnDelete();
        });

        $db = DB::connection($this->connection);
        $configuredRoles = (array) config('dashboard_security.roles', []);
        $roleKeys = array_keys($configuredRoles);

        if (Schema::connection($this->connection)->hasTable('dashboard_users')) {
            $existingRoleKeys = $db->table('dashboard_users')
                ->select('role')
                ->whereNotNull('role')
                ->distinct()
                ->pluck('role')
                ->map(static fn (mixed $role): string => strtolower(trim((string) $role)))
                ->filter()
                ->values()
                ->all();

            $roleKeys = array_values(array_unique([...$roleKeys, ...$existingRoleKeys]));
        }

        $now = now();
        foreach ($roleKeys as $roleKey) {
            $roleKey = strtolower(trim((string) $roleKey));
            if ($roleKey === '') {
                continue;
            }

            $db->table('dashboard_roles')->insertOrIgnore([
                'role_key' => $roleKey,
                'name' => (string) ($configuredRoles[$roleKey] ?? ucwords(str_replace(['_', '-'], ' ', $roleKey))),
                'is_system' => $roleKey === 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $legacy = (array) config('dashboard_security.legacy_role_menus', []);
        foreach ($legacy as $roleKey => $menus) {
            foreach ((array) $menus as $menuKey) {
                $db->table('dashboard_role_menus')->insertOrIgnore([
                    'role_key' => (string) $roleKey,
                    'menu_key' => (string) $menuKey,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('dashboard_role_menus');
        Schema::connection($this->connection)->dropIfExists('dashboard_roles');
    }
};
