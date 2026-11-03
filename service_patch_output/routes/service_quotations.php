<?php

declare(strict_types=1);

use App\Http\Controllers\ServiceQuotationController;
use Illuminate\Support\Facades\Route;

// Hak akses khusus penawaran service; administrator otomatis mendapat akses.
Route::middleware(['dashboard.auth', 'dashboard.audit', 'dashboard.permission:service_quotations'])
    ->prefix('service-quotations')->name('service-quotations.')
    ->group(function (): void {
        Route::get('/', [ServiceQuotationController::class, 'index'])->name('index');
        Route::get('/create', [ServiceQuotationController::class, 'create'])->name('create');
        Route::get('/service-centers', [ServiceQuotationController::class, 'serviceCenters'])->name('service-centers');
        Route::post('/service-centers', [ServiceQuotationController::class, 'saveServiceCenter'])->name('service-centers.save');
        Route::get('/service-customers', [ServiceQuotationController::class, 'serviceCustomers'])->name('service-customers');
        Route::post('/service-customers', [ServiceQuotationController::class, 'saveServiceCustomer'])->name('service-customers.save');
        Route::get('/lookup/customers', [ServiceQuotationController::class, 'lookupServiceCustomers'])->name('customers');
        Route::get('/lookup/doc-types', [ServiceQuotationController::class, 'docTypes'])->name('doc-types');
        Route::get('/lookup/items', [ServiceQuotationController::class, 'items'])->name('items');
        Route::get('/lookup/parts', [ServiceQuotationController::class, 'parts'])->name('parts');
        Route::get('/lookup/availability', [ServiceQuotationController::class, 'availability'])->name('availability');
        Route::post('/', [ServiceQuotationController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [ServiceQuotationController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [ServiceQuotationController::class, 'update'])->whereNumber('id')->name('update');
        Route::get('/{id}', [ServiceQuotationController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('/{id}/print', [ServiceQuotationController::class, 'print'])->whereNumber('id')->name('print');
        Route::get('/{id}/print-receipt', [ServiceQuotationController::class, 'printReceipt'])->whereNumber('id')->name('print-receipt');
        Route::post('/{id}/send-erp', [ServiceQuotationController::class, 'sendErp'])->whereNumber('id')->name('send-erp');
    });
