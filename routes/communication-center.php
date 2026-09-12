<?php

use App\Http\Controllers\Admin\CommunicationCenterController;
use App\Http\Controllers\Admin\CommunicationTemplateController;
use Illuminate\Support\Facades\Route;

$communicationSectionPattern = implode('|', CommunicationCenterController::SECTIONS);

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () use ($communicationSectionPattern): void {
    // Franchise management owns the wildcard /resource/{section} route. Use
    // literal communication-center paths here so the two route families do
    // not replace one another in Laravel's route collection.
    foreach (CommunicationCenterController::SECTIONS as $section) {
        Route::get('/resource/'.$section, [CommunicationCenterController::class, 'show'])
            ->defaults('section', $section)
            ->name('admin.communication-center.page.'.$section);
    }

    Route::get('/communication-center/{section}/export', [CommunicationCenterController::class, 'export'])
        ->where('section', $communicationSectionPattern)
        ->name('admin.communication-center.export');

    Route::post('/communication-center/email-templates/templates', [CommunicationTemplateController::class, 'store'])
        ->name('admin.communication-center.templates.store');

    Route::patch('/communication-center/email-templates/templates/{template:uuid}', [CommunicationTemplateController::class, 'update'])
        ->name('admin.communication-center.templates.update');

    Route::delete('/communication-center/email-templates/templates/{template:uuid}', [CommunicationTemplateController::class, 'destroy'])
        ->name('admin.communication-center.templates.destroy');

    Route::post('/communication-center/email-templates/templates/{template:uuid}/{action}', [CommunicationTemplateController::class, 'action'])
        ->where('action', 'activate|archive|duplicate|restore|submit_for_approval')
        ->name('admin.communication-center.templates.action');

    Route::post('/communication-center/{section}/records', [CommunicationCenterController::class, 'storeRecord'])
        ->where('section', 'approval-center|action-follow-ups|alerts-notifications')
        ->name('admin.communication-center.record.store');

    Route::patch('/communication-center/{section}/records/{record}', [CommunicationCenterController::class, 'updateRecord'])
        ->where('section', 'approval-center|action-follow-ups|alerts-notifications')
        ->whereNumber('record')
        ->name('admin.communication-center.record.update');

    Route::delete('/communication-center/{section}/records/{record}', [CommunicationCenterController::class, 'destroyRecord'])
        ->where('section', 'approval-center|action-follow-ups|alerts-notifications')
        ->whereNumber('record')
        ->name('admin.communication-center.record.destroy');

    Route::post('/communication-center/{section}/records/{record}/{action}', [CommunicationCenterController::class, 'recordAction'])
        ->where('section', 'approval-center|action-follow-ups|alerts-notifications')
        ->whereNumber('record')
        ->where('action', 'approve|reject|complete|reopen|acknowledge|resolve|duplicate|archive')
        ->name('admin.communication-center.record.action');
});
