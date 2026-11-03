<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use App\Services\Security\DashboardAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDashboardRole
{
    public function __construct(private readonly DashboardAuditService $audits)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = (string) $request->session()->get((string) config('dashboard_security.session_user_role_key'), '');
        if ($role === '' || !in_array($role, $roles, true)) {
            /** @var DashboardUser|null $user */
            $user = $request->attributes->get('dashboard_user');
            /** @var DashboardDevice|null $device */
            $device = $request->attributes->get('dashboard_device');
            $this->audits->activity($request, 'authorization_denied', 403, $user, $device, [
                'actual_role' => $role,
                'required_roles' => $roles,
            ]);

            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }
}
