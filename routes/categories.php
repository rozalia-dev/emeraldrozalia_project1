<?php

use App\Http\Controllers\Admin\CatalogTaxonomyController;
use App\Http\Controllers\Admin\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/resource/categories')
    ->middleware(['auth', 'admin'])
    ->name('admin.categories.')
    ->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::post('/bulk', [CategoryController::class, 'bulk'])->name('bulk');
        Route::delete('/bulk', [CategoryController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::post('/reorder', [CategoryController::class, 'reorder'])->name('reorder');
        Route::post('/import', [CategoryController::class, 'import'])->name('import');
        Route::get('/export', [CategoryController::class, 'export'])->name('export');

        Route::get('/taxonomy', [CatalogTaxonomyController::class, 'index'])->name('taxonomy');
        Route::post('/taxonomy/build', [CatalogTaxonomyController::class, 'build'])->name('taxonomy.build');
        Route::get('/countries', [CatalogTaxonomyController::class, 'countries'])->name('countries');
        Route::post('/countries', [CatalogTaxonomyController::class, 'storeCountry'])->name('countries.store');
        Route::patch('/countries/{country}', [CatalogTaxonomyController::class, 'updateCountry'])->name('countries.update');
        Route::get('/clubs', [CatalogTaxonomyController::class, 'clubs'])->name('clubs');
        Route::get('/clubs/options', [CatalogTaxonomyController::class, 'clubOptions'])->name('clubs.options');
        Route::post('/clubs', [CatalogTaxonomyController::class, 'storeClub'])->name('clubs.store');
        Route::patch('/clubs/{club}', [CatalogTaxonomyController::class, 'updateClub'])->name('clubs.update');
        Route::delete('/clubs/{club}', [CatalogTaxonomyController::class, 'destroyClub'])->name('clubs.destroy');

        Route::post('/{category}/visibility', [CategoryController::class, 'toggleVisibility'])->name('visibility');
        Route::get('/{category}/audit', [CategoryController::class, 'audit'])->name('audit');
        Route::patch('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });

require __DIR__.'/variants.php';
require __DIR__.'/collections.php';
