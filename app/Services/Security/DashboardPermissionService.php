<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\DashboardRole;
use App\Models\DashboardRoleMenu;
use App\Models\DashboardUser;
use App\Support\DashboardMenuPolicy;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class DashboardPermissionService
{
    public function __construct(private readonly DashboardMenuPolicy $policy)
    {
    }

    /** @return array<string,array{label:string,route:string,group:string}> */
    public function menuCatalog(): array
    {
        return (array) config('dashboard_security.menus', []);
    }

    public function can(DashboardUser $user, string $menuKey): bool
    {
        if (!array_key_exists($menuKey, $this->menuCatalog())) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->policy->allows($user->role, $this->grantedMenus($user->role), $menuKey);
    }

    /** @return array<string,bool> */
    public function menuAccess(DashboardUser $user): array
    {
        $access = [];
        foreach (array_keys($this->menuCatalog()) as $menuKey) {
            $access[$menuKey] = $this->can($user, $menuKey);
        }

        return $access;
    }

    /** @return list<string> */
    public function grantedMenus(string $roleKey): array
    {
        $roleKey = strtolower(trim($roleKey));
        if ($roleKey === 'admin') {
            return array_keys($this->menuCatalog());
        }

        if ($this->rolesReady()) {
            try {
                return DashboardRoleMenu::query()
                    ->where('role_key', $roleKey)
                    ->pluck('menu_key')
                    ->map(static fn (mixed $key): string => (string) $key)
                    ->values()
                    ->all();
            } catch (Throwable) {
                // Fall back to the legacy matrix so a migration issue does not lock out existing roles.
            }
        }

        return array_values(array_map(
            static fn (mixed $key): string => (string) $key,
            (array) config('dashboard_security.legacy_role_menus.'.$roleKey, []),
        ));
    }

    /** @return array<string,string> */
    public function roleOptions(bool $includeAdministrator = true): array
    {
        $roles = [];

        if ($this->rolesReady()) {
            try {
                $roles = DashboardRole::query()
                    ->orderByDesc('is_system')
                    ->orderBy('name')
                    ->pluck('name', 'role_key')
                    ->mapWithKeys(static fn (mixed $name, mixed $key): array => [(string) $key => (string) $name])
                    ->all();
            } catch (Throwable) {
                $roles = [];
            }
        }

        if ($roles === []) {
            $roles = array_map(static fn (mixed $name): string => (string) $name, (array) config('dashboard_security.roles', []));
        }

        if (!$includeAdministrator) {
            unset($roles['admin']);
        }

        return $roles;
    }

    public function roleLabel(string $roleKey): string
    {
        $roleKey = strtolower(trim($roleKey));
        $options = $this->roleOptions();

        return $options[$roleKey] ?? strtoupper($roleKey);
    }

    public function landingRoute(DashboardUser $user): string
    {
        $catalog = $this->menuCatalog();
        $order = (array) config('dashboard_security.landing_menu_order', array_keys($catalog));

        foreach ($order as $menuKey) {
            $menuKey = (string) $menuKey;
            if (isset($catalog[$menuKey]) && $this->can($user, $menuKey)) {
                return (string) ($catalog[$menuKey]['route'] ?? 'account.password.edit');
            }
        }

        return 'account.password.edit';
    }

    /** @param list<string> $menus */
    public function syncRoleMenus(DashboardRole $role, array $menus): void
    {
        if ($role->isAdministrator()) {
            return;
        }

        $allowed = array_keys($this->menuCatalog());
        $menus = array_values(array_unique(array_intersect($menus, $allowed)));

        DashboardRoleMenu::query()->where('role_key', $role->role_key)->delete();
        $now = now();

        foreach ($menus as $menuKey) {
            DashboardRoleMenu::query()->create([
                'role_key' => $role->role_key,
                'menu_key' => $menuKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function rolesReady(): bool
    {
        try {
            $connection = (string) config('dashboard_security.connection', 'sqlite_targets');

            return Schema::connection($connection)->hasTable('dashboard_roles')
                && Schema::connection($connection)->hasTable('dashboard_role_menus');
        } catch (Throwable) {
            return false;
        }
    }
}
