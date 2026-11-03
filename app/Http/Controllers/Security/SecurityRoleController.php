<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\StoreDashboardRoleRequest;
use App\Http\Requests\Security\UpdateDashboardRoleRequest;
use App\Models\DashboardRole;
use App\Services\Security\DashboardPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class SecurityRoleController extends Controller
{
    public function index(DashboardPermissionService $permissions): View
    {
        $roles = DashboardRole::query()
            ->with('menus')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('security.roles.index', [
            'roles' => $roles,
            'menuCatalog' => $permissions->menuCatalog(),
        ]);
    }

    public function store(
        StoreDashboardRoleRequest $request,
        DashboardPermissionService $permissions,
    ): RedirectResponse {
        $name = trim((string) $request->validated('name'));
        $roleKey = Str::of($name)->slug('_')->lower()->substr(0, 30)->toString();

        if ($roleKey === '' || $roleKey === 'admin') {
            return back()->withInput()->withErrors([
                'role' => 'Nama role tidak dapat digunakan.',
            ]);
        }

        if (DashboardRole::query()->whereKey($roleKey)->exists()) {
            return back()->withInput()->withErrors([
                'role' => 'Role dengan kode '.$roleKey.' sudah ada. Gunakan nama role lain.',
            ]);
        }

        $role = DashboardRole::query()->create([
            'role_key' => $roleKey,
            'name' => $name,
            'is_system' => false,
        ]);

        $permissions->syncRoleMenus($role, (array) $request->validated('menus', []));

        return back()->with('status', 'Role '.$role->name.' berhasil dibuat.');
    }

    public function update(
        UpdateDashboardRoleRequest $request,
        DashboardRole $dashboardRole,
        DashboardPermissionService $permissions,
    ): RedirectResponse {
        if ($dashboardRole->isAdministrator() || $dashboardRole->is_system) {
            return back()->withErrors([
                'role' => 'Role Administrator adalah system role dan aksesnya tidak dapat diubah.',
            ]);
        }

        $dashboardRole->forceFill([
            'name' => trim((string) $request->validated('name')),
        ])->save();

        $permissions->syncRoleMenus($dashboardRole, (array) $request->validated('menus', []));

        return back()->with('status', 'Role '.$dashboardRole->name.' berhasil diperbarui.');
    }
}
