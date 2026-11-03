<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/sales-dashboard';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function (): void {
            Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));
            Route::middleware('web')->group(base_path('routes/web.php'));

            // Modul Penawaran Service dipindahkan ke project service-sparepart terpisah.
            // routes/service_quotations.php sengaja tidak lagi dimuat oleh Sales Dashboard.
        });
    }
}
