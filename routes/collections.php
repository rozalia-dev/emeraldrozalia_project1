<?php

use App\Http\Controllers\Admin\CollectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/resource/collections')
    ->middleware(['auth', 'admin'])
    ->name('admin.collections.')
    ->group(function (): void {
        Route::post('/bulk', [CollectionController::class, 'bulk'])->name('bulk');
        Route::post('/reorder', [CollectionController::class, 'reorder'])->name('reorder');
        Route::post('/import', [CollectionController::class, 'import'])->name('import');
        Route::get('/export', [CollectionController::class, 'export'])->name('export');
        Route::patch('/{collection}/settings', [CollectionController::class, 'updateSettings'])->name('settings');
        Route::post('/{collection}/products/category', [CollectionController::class, 'addByCategory'])->name('products.category');
        Route::post('/{collection}/products/import', [CollectionController::class, 'importProducts'])->name('products.import');
        Route::get('/{collection}/audit', [CollectionController::class, 'audit'])->name('audit');
    });
