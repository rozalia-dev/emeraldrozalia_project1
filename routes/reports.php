<?php

use App\Http\Controllers\Admin\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/resource/reports')->middleware(['auth', 'admin'])->name('admin.reports.')->group(function (): void {
    Route::get('/', [ReportController::class, 'overview'])->name('overview');
    Route::get('/order', [ReportController::class, 'order'])->name('order');
    Route::get('/communication', [ReportController::class, 'communication'])->name('communication');
    Route::get('/customer', [ReportController::class, 'customer'])->name('customer');
    Route::get('/approvals', fn (\Illuminate\Http\Request $request) => app(ReportController::class)->page($request, 'approvals'))->name('approvals');
    Route::get('/custom', fn (\Illuminate\Http\Request $request) => app(ReportController::class)->page($request, 'custom'))->name('custom');
    Route::get('/history', fn (\Illuminate\Http\Request $request) => app(ReportController::class)->page($request, 'history'))->name('history');
    Route::get('/returns', fn (\Illuminate\Http\Request $request) => app(ReportController::class)->page($request, 'returns'))->name('returns');
    Route::get('/roles', fn (\Illuminate\Http\Request $request) => app(ReportController::class)->page($request, 'roles'))->name('roles');
    Route::get('/scheduler', fn (\Illuminate\Http\Request $request) => app(ReportController::class)->page($request, 'scheduler'))->name('scheduler');
});

Route::prefix('admin/reports')->middleware(['auth', 'admin'])->name('admin.reports.')->group(function (): void {
    Route::post('/run', [ReportController::class, 'run'])->name('run');
    Route::get('/export', [ReportController::class, 'export'])->name('export');
    Route::post('/custom', [ReportController::class, 'storeCustom'])->name('custom.store');
    Route::post('/schedules', [ReportController::class, 'storeSchedule'])->name('schedules.store');
    Route::post('/schedules/{schedule}/toggle', [ReportController::class, 'toggleSchedule'])->name('schedules.toggle');
    Route::post('/schedules/{schedule}/duplicate', [ReportController::class, 'duplicateSchedule'])->name('schedules.duplicate');
    Route::post('/schedules/{schedule}/delete', [ReportController::class, 'deleteSchedule'])->name('schedules.delete');
});
