<?php

use App\Http\Controllers\Api\V1\CatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['web', 'throttle:60,1'])->name('api.v1.')->group(function (): void {
    Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
    Route::get('/products/{product:slug}', [CatalogController::class, 'product'])->name('products.show');
    Route::get('/banners', [CatalogController::class, 'banners'])->name('banners.index');
});
