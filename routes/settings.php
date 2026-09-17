<?php

use App\Http\Controllers\Admin\LocalizationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SystemMaintenanceController;
use App\Http\Controllers\Admin\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/resource/settings/company-branding/theme', [ThemeController::class, 'index'])
    ->middleware(['auth', 'admin'])
    ->name('admin.settings.theme.index');

Route::prefix('admin/resource/settings/localization/manage')->middleware(['auth', 'admin'])->name('admin.localization.')->group(function (): void {
    Route::get('/', [LocalizationController::class, 'index'])->name('index');
    Route::post('/language', [LocalizationController::class, 'language'])->name('language');
    Route::post('/currency', [LocalizationController::class, 'currency'])->name('currency');
    Route::post('/rate', [LocalizationController::class, 'rate'])->name('rate');
    Route::post('/rates/sync', [LocalizationController::class, 'syncRates'])->name('rates.sync');
    Route::post('/translation', [LocalizationController::class, 'translation'])->name('translation');
    Route::post('/content-translation', [LocalizationController::class, 'contentTranslation'])->name('content-translation');
});

Route::prefix('admin/resource/settings')->middleware(['auth', 'admin'])->name('admin.settings.')->group(function (): void {
    Route::get('/', [SettingsController::class, 'overview'])->name('overview');
    Route::get('/{section}', [SettingsController::class, 'show'])
        ->where('section', SettingsController::SECTION_PATTERN)
        ->name('page');
});

Route::prefix('admin/settings')->middleware(['auth', 'admin'])->name('admin.settings.')->group(function (): void {
    Route::post('/save/{section}', [SettingsController::class, 'save'])
        ->where('section', SettingsController::SECTION_PATTERN)
        ->name('save');
    Route::post('/action/{action}', [SettingsController::class, 'action'])->name('action');
    Route::post('/api-roles', [SettingsController::class, 'storeApiRole'])->name('api-roles.store');
    Route::post('/api-roles/{role}/toggle', [SettingsController::class, 'toggleApiRole'])->name('api-roles.toggle');
    Route::post('/api-roles/{role}/clone', [SettingsController::class, 'cloneApiRole'])->name('api-roles.clone');
    Route::post('/automations', [SettingsController::class, 'storeAutomation'])->name('automations.store');
    Route::post('/automations/{automation}/toggle', [SettingsController::class, 'toggleAutomation'])->name('automations.toggle');
    Route::post('/backups', [SettingsController::class, 'storeBackup'])->name('backups.store');
    Route::post('/backups/{backup}/restore', [SettingsController::class, 'restoreBackup'])->name('backups.restore');
    Route::get('/maintenance/status', SystemMaintenanceController::class)->name('maintenance.status');
    Route::post('/themes/drafts', [ThemeController::class, 'store'])->name('theme.store');
    Route::patch('/themes/{theme}', [ThemeController::class, 'update'])->name('theme.update');
    Route::post('/themes/{theme}/action/{action}', [ThemeController::class, 'action'])->name('theme.action');
});

Route::middleware(['auth', 'admin'])->prefix('admin/resource')->group(function (): void {
    Route::get('/company-profile', fn () => redirect()->route('admin.settings.page', 'company-branding'));
    Route::get('/language', fn () => redirect()->route('admin.localization.index'));
    Route::get('/currency', fn () => redirect()->route('admin.localization.index'));
    Route::get('/payment-settings', fn () => redirect()->route('admin.settings.page', 'payment-gateways'));
    Route::get('/notifications', fn () => redirect()->route('admin.settings.page', 'email-notifications'));
    Route::get('/branding', fn () => redirect()->route('admin.settings.page', 'company-branding'));
    Route::get('/security', fn () => redirect()->route('admin.settings.page', 'security-access'));
    Route::get('/automation', fn () => redirect()->route('admin.settings.page', 'automations'));
    Route::get('/backup-recovery', fn () => redirect()->route('admin.settings.page', 'backup-recovery'));
});
