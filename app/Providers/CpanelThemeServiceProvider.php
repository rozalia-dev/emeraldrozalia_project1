<?php

namespace App\Providers;

use App\Http\Controllers\Admin\CpanelThemeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CpanelThemeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth', 'admin'])
            ->prefix('admin/settings/cpanel-theme')
            ->name('admin.settings.cpanel-theme.')
            ->group(function (): void {
                Route::get('/', [CpanelThemeController::class, 'index'])->name('index');
                Route::post('/', [CpanelThemeController::class, 'store'])->name('store');
                Route::patch('/{theme}', [CpanelThemeController::class, 'update'])->whereUuid('theme')->name('update');
                Route::post('/{theme}/{action}', [CpanelThemeController::class, 'action'])
                    ->whereUuid('theme')
                    ->where('action', 'validate|submit|approve|activate|disable|rollback')
                    ->name('action');
            });
    }
}
