# Manual Integration Points

Gunakan ini hanya bila `apply_patch.php` tidak menemukan anchor karena source lokal Anda berbeda dari branch `main` yang dianalisis.

## 1. `routes/web.php`

Tambahkan import:

```php
use App\Http\Controllers\BusinessAnalyticsController;
```

Di dalam group `dashboard.auth` + `dashboard.audit` tambahkan:

```php
Route::middleware('dashboard.permission:business_analytics')->group(function (): void {
    Route::get('/business-analytics', [BusinessAnalyticsController::class, 'index'])
        ->name('business-analytics.index');
    Route::post('/business-analytics/targets', [BusinessAnalyticsController::class, 'saveTargets'])
        ->middleware('dashboard.permission:kpi_targets')
        ->name('business-analytics.targets.save');
    Route::get('/business-analytics/export/csv', [BusinessAnalyticsController::class, 'exportCsv'])
        ->name('business-analytics.export.csv');
});
```

## 2. `config/dashboard_security.php`

Tambahkan menu:

```php
'business_analytics' => [
    'label' => 'Business Analytics',
    'route' => 'business-analytics.index',
    'group' => 'Laporan',
],
```

Tambahkan `business_analytics` ke role yang ingin diberi akses dan ke `landing_menu_order` bila diperlukan.

Tambahkan audit actions:

```php
'business-analytics.index' => 'view_business_analytics',
'business-analytics.export.csv' => 'export_business_analytics_csv',
'business-analytics.targets.save' => 'update_business_analytics_targets',
```

## 3. `resources/views/layouts/app.blade.php`

Di bagian LAPORAN sidebar:

```blade
@if ($dashboardMenuAccess['business_analytics'] ?? false)
    <a href="{{ route('business-analytics.index') }}"
       title="Business Analytics"
       class="sidebar-link {{ request()->routeIs('business-analytics.*') ? 'active' : '' }}">
        <i class="bi bi-bar-chart-line sidebar-icon"></i>
        <span class="nav-label">Business Analytics</span>
    </a>
@endif
```

## 4. `app/Console/Kernel.php`

Di `schedule()`:

```php
$schedule->command('analytics:sync --months=2')
    ->dailyAt('02:15')
    ->withoutOverlapping();
```
