<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\DashboardAuthController;
use App\Http\Controllers\DashboardHomeController;
use App\Http\Controllers\SparepartSearchController;
use App\Http\Controllers\Security\SecurityAuditController;
use App\Http\Controllers\Security\SecurityDeviceController;
use App\Http\Controllers\Security\SecurityRoleController;
use App\Http\Controllers\Security\SecurityUserController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [DashboardAuthController::class, 'create'])->name('dashboard.login');
Route::post('/login', [DashboardAuthController::class, 'store'])->name('dashboard.login.store');
Route::redirect('/device/pending', '/login');

Route::middleware(['dashboard.auth', 'dashboard.audit'])->group(function (): void {
    Route::get('/', DashboardHomeController::class)->name('dashboard.home');

    Route::get('/account/password', [AccountPasswordController::class, 'edit'])
        ->name('account.password.edit');
    Route::put('/account/password', [AccountPasswordController::class, 'update'])
        ->name('account.password.update');

    Route::middleware('dashboard.permission:sparepart_search')->group(function (): void {
        Route::get('/spareparts/search', [SparepartSearchController::class, 'index'])
            ->name('spareparts.search');
    });

    Route::middleware('dashboard.permission:security_users')->group(function (): void {
        Route::get('/security/users', [SecurityUserController::class, 'index'])
            ->name('security.users.index');
        Route::post('/security/users', [SecurityUserController::class, 'store'])
            ->name('security.users.store');
        Route::put('/security/users/{dashboardUser}', [SecurityUserController::class, 'update'])
            ->name('security.users.update');
        Route::put('/security/users/{dashboardUser}/password', [SecurityUserController::class, 'resetPassword'])
            ->name('security.users.password');
    });

    Route::middleware('dashboard.permission:security_roles')->group(function (): void {
        Route::get('/security/roles', [SecurityRoleController::class, 'index'])
            ->name('security.roles.index');
        Route::post('/security/roles', [SecurityRoleController::class, 'store'])
            ->name('security.roles.store');
        Route::put('/security/roles/{dashboardRole}', [SecurityRoleController::class, 'update'])
            ->name('security.roles.update');
    });

    Route::middleware('dashboard.permission:security_devices')->group(function (): void {
        Route::get('/security/devices', [SecurityDeviceController::class, 'index'])
            ->name('security.devices.index');
        Route::put('/security/devices/{dashboardDevice}', [SecurityDeviceController::class, 'update'])
            ->name('security.devices.update');
    });

    Route::middleware('dashboard.permission:security_audits')->group(function (): void {
        Route::get('/security/audits', [SecurityAuditController::class, 'index'])
            ->name('security.audits.index');
    });

    Route::post('/logout', [DashboardAuthController::class, 'destroy'])
        ->name('dashboard.logout');
});
