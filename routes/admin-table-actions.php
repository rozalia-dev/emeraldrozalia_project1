<?php

use App\Http\Controllers\Admin\GenericResourceBulkController;
use App\Http\Controllers\Admin\MediaBulkActionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->group(function (): void {
    Route::prefix('admin/resource')
        ->name('admin.resource.')
        ->group(function (): void {
            Route::post('/{module}/bulk-actions', GenericResourceBulkController::class)
                ->name('bulk-actions');
        });

    Route::prefix('admin/table-actions')
        ->name('admin.table-actions.')
        ->group(function (): void {
            Route::post('/product-media', [MediaBulkActionController::class, 'productMedia'])
                ->name('product-media');
            Route::post('/site-media', [MediaBulkActionController::class, 'siteMedia'])
                ->name('site-media');
        });
});
