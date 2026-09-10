<?php

use App\Http\Controllers\Admin\OrderMasterController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['web', 'auth', 'admin'])->name('admin.')->group(function (): void {
    Route::get('/order-master', [OrderMasterController::class, 'overview'])->name('order-master.overview');
    Route::post('/order-master', [OrderMasterController::class, 'store'])->name('order-master.store');
    Route::get('/order-master/export', [OrderMasterController::class, 'export'])->name('order-master.export');
    Route::post('/order-master/import', [OrderMasterController::class, 'import'])->name('order-master.import');
});
