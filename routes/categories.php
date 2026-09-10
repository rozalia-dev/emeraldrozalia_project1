<?php

use App\Http\Controllers\Admin\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/resource/categories')
    ->middleware(['auth', 'admin'])
    ->name('admin.categories.')
    ->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::post('/bulk', [CategoryController::class, 'bulk'])->name('bulk');
        Route::post('/reorder', [CategoryController::class, 'reorder'])->name('reorder');
        Route::post('/import', [CategoryController::class, 'import'])->name('import');
        Route::get('/export', [CategoryController::class, 'export'])->name('export');
        Route::post('/{category}/visibility', [CategoryController::class, 'toggleVisibility'])->name('visibility');
        Route::get('/{category}/audit', [CategoryController::class, 'audit'])->name('audit');
        Route::patch('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });

require __DIR__.'/variants.php';
