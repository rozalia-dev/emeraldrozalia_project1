<?php

use App\Http\Controllers\Admin\GenericResourceBulkController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/resource')
    ->middleware(['auth', 'admin'])
    ->name('admin.resource.')
    ->group(function (): void {
        Route::post('/{module}/bulk-actions', GenericResourceBulkController::class)
            ->name('bulk-actions');
    });
