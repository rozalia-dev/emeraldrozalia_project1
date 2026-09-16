<?php

use App\Http\Controllers\Admin\AdminProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin/profile')
    ->name('admin.profile.')
    ->group(function (): void {
        Route::get('/', [AdminProfileController::class, 'show'])->name('show');
        Route::patch('/', [AdminProfileController::class, 'update'])->name('update');
        Route::patch('/password', [AdminProfileController::class, 'password'])->name('password');
    });
