<?php

namespace App\Providers;

use App\Http\Controllers\Admin\ProductCatalogueController as AdminProductCatalogueController;
use App\Http\Controllers\ProductCatalogueController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ProductCatalogueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware('web')->group(function (): void {
            Route::get('/product-catalogue', [ProductCatalogueController::class, 'show'])->name('catalogue.show');
            Route::get('/product-catalogue/print', [ProductCatalogueController::class, 'printable'])->name('catalogue.print');
            Route::get('/product-catalogue/download', [ProductCatalogueController::class, 'download'])->name('catalogue.download');
            Route::get('/product-catalogue/cover', [ProductCatalogueController::class, 'cover'])->name('catalogue.cover');
        });

        Route::middleware(['web', 'auth', 'admin'])
            ->prefix('admin/resource/product-catalogue')
            ->name('admin.product-catalogue.')
            ->group(function (): void {
                Route::get('/', [AdminProductCatalogueController::class, 'index'])->name('index');
                Route::put('/', [AdminProductCatalogueController::class, 'update'])->name('update');
                Route::get('/download', [AdminProductCatalogueController::class, 'download'])->name('download');
            });
    }
}
