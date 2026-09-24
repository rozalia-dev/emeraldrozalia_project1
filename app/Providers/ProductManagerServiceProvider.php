<?php

namespace App\Providers;

use App\Http\Controllers\Admin\ProductManagerController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ProductManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::middleware(['web', 'auth', 'admin'])
                ->prefix('admin/resource/product-manager')
                ->name('admin.product-manager.')
                ->group(function (): void {
                    Route::get('/', [ProductManagerController::class, 'index'])->name('index');
                    Route::post('/bulk-publish', [ProductManagerController::class, 'bulkPublish'])->name('bulk-publish');
                    Route::post('/{product}/publish', [ProductManagerController::class, 'publish'])->whereNumber('product')->name('publish');
                    Route::delete('/bulk', [ProductManagerController::class, 'bulkDestroy'])->name('bulk-destroy');
                    Route::delete('/{product}', [ProductManagerController::class, 'destroy'])->whereNumber('product')->name('destroy');
                    Route::post('/{product}/restore', [ProductManagerController::class, 'restore'])->whereNumber('product')->name('restore');
                    Route::delete('/{product}/permanent', [ProductManagerController::class, 'permanentDestroy'])->whereNumber('product')->name('permanent-destroy');
                });
        }

    }
}
