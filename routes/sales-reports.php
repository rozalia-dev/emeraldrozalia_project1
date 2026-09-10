<?php

use App\Http\Controllers\Admin\SalesReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/resource/sales-reports')->middleware(['auth', 'admin'])->name('admin.sales-reports.')->group(function (): void {
    Route::get('/', [SalesReportController::class, 'index'])->name('dashboard');
    Route::get('/export', [SalesReportController::class, 'export'])->name('export');
    Route::post('/views', [SalesReportController::class, 'saveView'])->name('views.store');
});
