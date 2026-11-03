<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\ResetDashboardUserPasswordRequest;
use App\Http\Requests\Security\StoreDashboardUserRequest;
use App\Http\Requests\Security\UpdateDashboardUserRequest;
use App\Models\DashboardUser;
use App\Services\SalesPersonDirectoryService;
use App\Services\Security\DashboardPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class SecurityUserController extends Controller
{
    public function index(
        Request $request,
        DashboardPermissionService $permissions,
        SalesPersonDirectoryService $salesPersonDirectory,
    ): View {
        /** @var DashboardUser|null $currentUser */
        $currentUser = $request->attributes->get('dashboard_user');

        $users = DashboardUser::query()
            ->withCount('devices')
            ->orderBy('name')
            ->get();

        return view('security.users.index', [
            'users' => $users,
            'roles' => $permissions->roleOptions($currentUser?->isAdmin() ?? false),
            'canManageAdministrator' => $currentUser?->isAdmin() ?? false,
            'salesPersonOptions' => $salesPersonDirectory->options('BOTH'),
        ]);
    }

    public function store(StoreDashboardUserRequest $request): RedirectResponse
    {
        /** @var DashboardUser|null $currentUser */
        $currentUser = $request->attributes->get('dashboard_user');
        $role = (string) $request->validated('role');

        if ($role === 'admin' && !($currentUser?->isAdmin() ?? false)) {
            return back()->withInput()->withErrors([
                'security' => 'Anda tidak dapat menetapkan role Administrator.',
            ]);
        }

        DashboardUser::query()->create([
            'name' => trim((string) $request->validated('name')),
            'username' => strtolower(trim((string) $request->validated('username'))),
            'password' => Hash::make((string) $request->validated('password')),
            'role' => $role,
            'sales_person_id' => $request->validated('sales_person_id'),
            'is_active' => true,
        ]);

        return back()->with('status', 'Pengguna dashboard berhasil dibuat.');
    }

    public function update(
        UpdateDashboardUserRequest $request,
        DashboardUser $dashboardUser,
    ): RedirectResponse {
        /** @var DashboardUser|null $currentUser */
        $currentUser = $request->attributes->get('dashboard_user');
        $requestedRole = (string) $request->validated('role');
        $isAdministrator = $currentUser?->isAdmin() ?? false;

        if (!$isAdministrator && ($dashboardUser->role === 'admin' || $requestedRole === 'admin')) {
            return back()->withErrors([
                'security' => 'Anda tidak dapat menetapkan role Administrator atau mengubah akun Administrator.',
            ]);
        }

        $currentUserId = (int) $request->session()->get((string) config('dashboard_security.session_user_id_key'), 0);
        $isActive = $request->boolean('is_active');

        if ((int) $dashboardUser->getKey() === $currentUserId && !$isActive) {
            return back()->withErrors(['security' => 'Anda tidak dapat menonaktifkan akun yang sedang digunakan.']);
        }

        if ($dashboardUser->role === 'admin'
            && $requestedRole !== 'admin'
            && DashboardUser::query()->where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['security' => 'Minimal harus ada satu administrator aktif.']);
        }

        if ($dashboardUser->role === 'admin'
            && !$isActive
            && DashboardUser::query()->where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['security' => 'Minimal harus ada satu administrator aktif.']);
        }

        $dashboardUser->forceFill([
            'name' => trim((string) $request->validated('name')),
            'role' => $requestedRole,
            'sales_person_id' => $request->validated('sales_person_id'),
            'is_active' => $isActive,
        ])->save();

        return back()->with('status', 'Pengguna berhasil diperbarui.');
    }

    public function resetPassword(
        ResetDashboardUserPasswordRequest $request,
        DashboardUser $dashboardUser,
    ): RedirectResponse {
        /** @var DashboardUser|null $currentUser */
        $currentUser = $request->attributes->get('dashboard_user');

        if ($dashboardUser->role === 'admin' && !($currentUser?->isAdmin() ?? false)) {
            return back()->withErrors([
                'security' => 'Anda tidak dapat mereset password akun Administrator.',
            ]);
        }

        $dashboardUser->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
        ])->save();

        return back()->with('status', 'Password untuk '.$dashboardUser->username.' berhasil diubah.');
    }
}
