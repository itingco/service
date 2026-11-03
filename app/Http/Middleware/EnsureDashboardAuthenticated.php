<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDashboardAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get((string) config('dashboard_auth.session_key')) !== true) {
            return redirect()->guest(route('dashboard.login'));
        }

        return $next($request);
    }
}
