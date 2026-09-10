<?php

use App\Http\Controllers\Admin\FranchiseManagementController;
use Illuminate\Support\Facades\Route;

$sectionPattern = implode('|', FranchiseManagementController::SECTIONS);

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () use ($sectionPattern): void {
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
