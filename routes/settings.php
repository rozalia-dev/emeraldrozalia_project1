<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/resource/settings/company-branding/theme', [ThemeController::class, 'index'])
    ->middleware(['auth', 'admin'])
    ->name('admin.settings.theme.index');

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
    Route::post('/themes/drafts', [ThemeController::class, 'store'])->name('theme.store');
    Route::patch('/themes/{theme}', [ThemeController::class, 'update'])->name('theme.update');
    Route::post('/themes/{theme}/action/{action}', [ThemeController::class, 'action'])->name('theme.action');
});

Route::middleware(['auth', 'admin'])->prefix('admin/resource')->group(function (): void {
    Route::get('/company-profile', fn () => redirect()->route('admin.settings.page', 'company-branding'));
    Route::get('/language', fn () => redirect()->route('admin.settings.page', 'localization'));
    Route::get('/currency', fn () => redirect()->route('admin.settings.page', 'localization'));
    Route::get('/payment-settings', fn () => redirect()->route('admin.settings.page', 'payment-gateways'));
    Route::get('/notifications', fn () => redirect()->route('admin.settings.page', 'email-notifications'));
    Route::get('/branding', fn () => redirect()->route('admin.settings.page', 'company-branding'));
    Route::get('/security', fn () => redirect()->route('admin.settings.page', 'security-access'));
    Route::get('/automation', fn () => redirect()->route('admin.settings.page', 'automations'));
    Route::get('/backup-recovery', fn () => redirect()->route('admin.settings.page', 'backup-recovery'));
});
