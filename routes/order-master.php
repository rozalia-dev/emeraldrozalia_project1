<?php

use App\Http\Controllers\Admin\OrderMasterOverviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['web', 'auth', 'admin'])->name('admin.')->group(function (): void {
    Route::get('/order-master', [OrderMasterOverviewController::class, 'index'])->name('order-master.overview');
    Route::post('/order-master', [OrderMasterOverviewController::class, 'store'])->name('order-master.store');
    Route::get('/order-master/export', [OrderMasterOverviewController::class, 'export'])->name('order-master.export');
    Route::post('/order-master/import', [OrderMasterOverviewController::class, 'import'])->name('order-master.import');
});
