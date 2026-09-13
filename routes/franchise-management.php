<?php

use App\Http\Controllers\Admin\FranchiseManagementController;
use App\Http\Controllers\Admin\FranchiseTerritoryController;
use Illuminate\Support\Facades\Route;

$sectionPattern = implode('|', FranchiseManagementController::SECTIONS);

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () use ($sectionPattern): void {
    Route::get('/resource/franchise-territories', [FranchiseTerritoryController::class, 'index'])
        ->name('admin.franchise.territories');

    Route::get('/resource/franchise-dashboard', [FranchiseManagementController::class, 'dashboard'])
        ->name('admin.franchise.dashboard');
    Route::get('/resource/store-setup', [FranchiseManagementController::class, 'storeSetup'])
        ->name('admin.franchise.store-setup');
    Route::get('/resource/store-setup/{milestone:uuid}/edit', [FranchiseManagementController::class, 'storeSetupEdit'])
        ->name('admin.franchise.store-setup.edit');

    Route::prefix('franchise-management/franchise-territories')->name('admin.franchise.territories.')->group(function (): void {
        Route::get('/export', [FranchiseTerritoryController::class, 'export'])->name('export');
        Route::post('/', [FranchiseTerritoryController::class, 'store'])->name('store');
        Route::patch('/{territory}', [FranchiseTerritoryController::class, 'update'])->name('update');
        Route::delete('/{territory}', [FranchiseTerritoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('franchise-management')->name('admin.franchise.')->group(function (): void {
        Route::post('/applications/{application:uuid}/action/{action}', [FranchiseManagementController::class, 'applicationAction'])
            ->where('action', 'start-review|approve|reject|start-onboarding|convert')
            ->name('application.action');
        Route::post('/store-setup', [FranchiseManagementController::class, 'storeSetupStore'])
            ->name('store-setup.store');
        Route::patch('/store-setup/{milestone:uuid}', [FranchiseManagementController::class, 'storeSetupUpdate'])
            ->name('store-setup.update');
        Route::post('/store-setup/{milestone:uuid}/action/{action}', [FranchiseManagementController::class, 'storeSetupAction'])
            ->where('action', 'start|complete|activate|reopen|block')
            ->name('store-setup.action');
        Route::delete('/store-setup/{milestone:uuid}', [FranchiseManagementController::class, 'storeSetupTrash'])
            ->name('store-setup.trash');
        Route::post('/store-setup/{milestone}/restore', [FranchiseManagementController::class, 'storeSetupRestore'])
            ->name('store-setup.restore');
    });

    Route::get('/resource/{section}', [FranchiseManagementController::class, 'show'])
        ->where('section', $sectionPattern)
        ->name('admin.franchise.page');

    Route::prefix('franchise-management')->name('admin.franchise.')->group(function () use ($sectionPattern): void {
        Route::get('/{section}/export', [FranchiseManagementController::class, 'export'])
            ->where('section', $sectionPattern)
            ->name('export');
        Route::post('/{section}', [FranchiseManagementController::class, 'store'])
            ->where('section', $sectionPattern)
            ->name('store');
        Route::patch('/{section}/{id}', [FranchiseManagementController::class, 'update'])
            ->where('section', $sectionPattern)
            ->whereNumber('id')
            ->name('update');
        Route::delete('/{section}/{id}', [FranchiseManagementController::class, 'destroy'])
            ->where('section', $sectionPattern)
            ->whereNumber('id')
            ->name('destroy');
    });
});
