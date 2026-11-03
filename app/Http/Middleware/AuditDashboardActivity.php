<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DashboardDevice;
use App\Models\DashboardUser;
use App\Services\Security\DashboardAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditDashboardActivity
{
    public function __construct(private readonly DashboardAuditService $audits)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $routeName = (string) ($request->route()?->getName() ?? '');
        $action = (string) config('dashboard_security.audited_routes.'.$routeName, '');

        if ($action !== '') {
            /** @var DashboardUser|null $user */
            $user = $request->attributes->get('dashboard_user');
            /** @var DashboardDevice|null $device */
            $device = $request->attributes->get('dashboard_device');

            $metadata = array_filter([
                'database' => $request->query('database'),
                'end_date' => $request->query('end_date'),
                'item_status' => $request->query('item_status'),
                'sales_line' => $request->query('sales_line'),
                'target_user_id' => $request->route('dashboardUser')?->getKey(),
                'target_device_id' => $request->route('dashboardDevice')?->getKey(),
                'role' => $request->input('role'),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
                'device_label' => $request->input('label'),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            $this->audits->activity(
                $request,
                $action,
                $response->getStatusCode(),
                $user,
                $device,
                $metadata,
            );
        }

        return $response;
    }
}
