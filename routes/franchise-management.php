<?php

use App\Http\Controllers\Admin\FranchiseManagementController;
use App\Http\Controllers\Admin\FranchiseTerritoryController;
use Illuminate\Support\Facades\Route;

$sectionPattern = implode('|', FranchiseManagementController::SECTIONS);

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () use ($sectionPattern): void {
    Route::get('/resource/franchise-territories', [FranchiseTerritoryController::class, 'index'])
        ->name('admin.franchise.territories');

    Route::prefix('franchise-management/franchise-territories')->name('admin.franchise.territories.')->group(function (): void {
        Route::get('/export', [FranchiseTerritoryController::class, 'export'])->name('export');
        Route::post('/', [FranchiseTerritoryController::class, 'store'])->name('store');
        Route::patch('/{territory}', [FranchiseTerritoryController::class, 'update'])->name('update');
        Route::delete('/{territory}', [FranchiseTerritoryController::class, 'destroy'])->name('destroy');
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
