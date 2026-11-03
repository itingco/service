<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use App\Services\Security\DashboardAuditService;
use App\Services\Security\DashboardPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDashboardPermission
{
    public function __construct(
        private readonly DashboardPermissionService $permissions,
        private readonly DashboardAuditService $audits,
    ) {
    }

    public function handle(Request $request, Closure $next, string $menuKey): Response
    {
        /** @var DashboardUser|null $user */
        $user = $request->attributes->get('dashboard_user');

        if ($user === null || !$this->permissions->can($user, $menuKey)) {
            /** @var DashboardDevice|null $device */
            $device = $request->attributes->get('dashboard_device');
            $this->audits->activity($request, 'authorization_denied', 403, $user, $device, [
                'actual_role' => $user?->role,
                'required_menu' => $menuKey,
            ]);

            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }
}
