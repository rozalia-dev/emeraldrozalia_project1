<?php

use App\Http\Controllers\Admin\BannerController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/resource/banners-sliders')->middleware(['auth', 'admin'])->name('admin.banners.')->group(function (): void {
    Route::get('/', [BannerController::class, 'index'])->name('index');
    Route::post('/', [BannerController::class, 'store'])->name('store');
    Route::post('/bulk', [BannerController::class, 'bulk'])->name('bulk');
    Route::post('/import', [BannerController::class, 'import'])->name('import');
    Route::get('/export', [BannerController::class, 'export'])->name('export');
    Route::post('/{banner}/duplicate', [BannerController::class, 'duplicate'])->name('duplicate');
    Route::patch('/{banner}/settings', [BannerController::class, 'updateSettings'])->name('settings');
    Route::post('/{banner}/action/{action}', [BannerController::class, 'action'])->name('action');
    Route::post('/{banner}/revisions/{revision}/restore', [BannerController::class, 'restoreRevision'])->name('restore-revision');
    Route::get('/{banner}/audit', [BannerController::class, 'audit'])->name('audit');
    Route::put('/{banner}', [BannerController::class, 'update'])->name('update');
});
