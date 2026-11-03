<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\DashboardActivityAudit;
use App\Models\DashboardLoginAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SecurityAuditController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'activity' ? 'activity' : 'login';
        $search = trim((string) $request->query('q', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $loginQuery = DashboardLoginAudit::query()->with(['user', 'device']);
        $activityQuery = DashboardActivityAudit::query()->with(['user', 'device']);

        if ($search !== '') {
            $loginQuery->where(function (Builder $query) use ($search): void {
                $query->where('username', 'like', '%'.$search.'%')
                    ->orWhere('event', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('reason', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%'))
                    ->orWhereHas('device', fn (Builder $device) => $device
                        ->where('label', 'like', '%'.$search.'%'));
            });

            $activityQuery->where(function (Builder $query) use ($search): void {
                $query->where('action', 'like', '%'.$search.'%')
                    ->orWhere('route_name', 'like', '%'.$search.'%')
                    ->orWhere('path', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%'))
                    ->orWhereHas('device', fn (Builder $device) => $device
                        ->where('label', 'like', '%'.$search.'%'));
            });
        }

        if ($dateFrom !== '') {
            $loginQuery->whereDate('created_at', '>=', $dateFrom);
            $activityQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $loginQuery->whereDate('created_at', '<=', $dateTo);
            $activityQuery->whereDate('created_at', '<=', $dateTo);
        }

        $loginAudits = $loginQuery
            ->latest('created_at')
            ->paginate(50, ['*'], 'login_page')
            ->withQueryString();

        $activityAudits = $activityQuery
            ->latest('created_at')
            ->paginate(50, ['*'], 'activity_page')
            ->withQueryString();

        return view('security.audits.index', compact(
            'tab',
            'search',
            'dateFrom',
            'dateTo',
            'loginAudits',
            'activityAudits',
        ));
    }
}
