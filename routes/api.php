<?php

use App\Http\Controllers\CommunicationWebhookController;
use App\Http\Controllers\Admin\CommunicationEmailController;
use App\Http\Controllers\Admin\CommunicationTemplateController;
use App\Http\Controllers\Api\V1\CatalogController;
use Illuminate\Support\Facades\Route;

Route::post('api/v1/communication/webhooks/{provider}', CommunicationWebhookController::class)
    ->where('provider', '[A-Za-z0-9._-]+')
    ->middleware('throttle:60,1')
    ->name('api.v1.communication.webhooks');

Route::prefix('api/v1')->middleware(['web', 'throttle:60,1'])->name('api.v1.')->group(function (): void {
    Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
    Route::get('/products/{product:slug}', [CatalogController::class, 'product'])->name('products.show');
    Route::get('/banners', [CatalogController::class, 'banners'])->name('banners.index');
});

Route::prefix('api/v1/communication/templates')
    ->middleware(['web', 'auth', 'admin', 'throttle:60,1'])
    ->name('api.v1.communication.templates.')
    ->group(function (): void {
        Route::get('/', [CommunicationTemplateController::class, 'apiIndex'])->name('index');
        Route::post('/', [CommunicationTemplateController::class, 'apiStore'])->name('store');
        Route::get('/{template:uuid}', [CommunicationTemplateController::class, 'apiShow'])->name('show');
        Route::patch('/{template:uuid}', [CommunicationTemplateController::class, 'apiUpdate'])->name('update');
        Route::delete('/{template:uuid}', [CommunicationTemplateController::class, 'apiDelete'])->name('destroy');
        Route::post('/{template:uuid}/actions/{action}', [CommunicationTemplateController::class, 'apiAction'])
            ->where('action', 'activate|archive|duplicate|restore|submit_for_approval')
            ->name('action');
    });

Route::prefix('api/v1/communication/email')
    ->middleware(['web', 'auth', 'admin', 'throttle:60,1'])
    ->name('api.v1.communication.email.')
    ->group(function (): void {
        Route::get('/', [CommunicationEmailController::class, 'index'])->name('index');
        Route::get('/{conversation:uuid}', [CommunicationEmailController::class, 'show'])->name('show');
        Route::patch('/{conversation:uuid}', [CommunicationEmailController::class, 'update'])->name('update');
        Route::post('/{conversation:uuid}/messages', [CommunicationEmailController::class, 'reply'])->name('messages.store');
        Route::get('/{conversation:uuid}/audit', [CommunicationEmailController::class, 'audit'])->name('audit');
    });
