<?php

use App\Http\Controllers\Admin\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.categories.')->group(function (): void {
    Route::get('/resource/categories', [CategoryController::class, 'index'])->name('index');
    Route::post('/resource/categories', [CategoryController::class, 'store'])->name('store');
    Route::post('/resource/categories/bulk', [CategoryController::class, 'bulk'])->name('bulk');
    Route::post('/resource/categories/reorder', [CategoryController::class, 'reorder'])->name('reorder');
    Route::get('/resource/categories/export', [CategoryController::class, 'export'])->name('export');
    Route::post('/resource/categories/import', [CategoryController::class, 'import'])->name('import');
    Route::patch('/resource/categories/{category}', [CategoryController::class, 'update'])->name('update');
    Route::delete('/resource/categories/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    Route::get('/resource/categories/{category}/audit', [CategoryController::class, 'audit'])->name('audit');
});
