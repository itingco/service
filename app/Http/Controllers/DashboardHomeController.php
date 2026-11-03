<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DashboardUser;
use App\Services\Security\DashboardPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DashboardHomeController extends Controller
{
    public function __invoke(Request $request, DashboardPermissionService $permissions): RedirectResponse
    {
        /** @var DashboardUser|null $user */
        $user = $request->attributes->get('dashboard_user');

        if ($user === null) {
            return redirect()->route('dashboard.login');
        }

        return redirect()->route($permissions->landingRoute($user));
    }
}
