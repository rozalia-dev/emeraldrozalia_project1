<?php

use App\Http\Controllers\Admin\CommunicationCenterController;
use App\Http\Controllers\Admin\CommunicationApprovalController;
use App\Http\Controllers\Admin\CommunicationEmailController;
use App\Http\Controllers\Admin\CommunicationTemplateController;
use App\Http\Controllers\Admin\WhatsAppEngineController;
use Illuminate\Support\Facades\Route;

$communicationSectionPattern = implode('|', CommunicationCenterController::SECTIONS);

Route::prefix('admin')->middleware(['web', 'auth', 'communication.permission'])->group(function () use ($communicationSectionPattern): void {
    // Franchise management owns the wildcard /resource/{section} route. Use
    // literal communication-center paths here so the two route families do
    // not replace one another in Laravel's route collection.
    foreach (CommunicationCenterController::SECTIONS as $section) {
        // The dedicated mailbox route owns /admin/resource/email. Keeping it
        // out of this loop prevents Laravel's route cache/name lookup from
        // dropping admin.communication-center.page.email when duplicate GET
        // routes are compiled.
        if ($section === 'email') {
            continue;
        }

        Route::get('/resource/'.$section, [CommunicationCenterController::class, 'show'])
            ->defaults('section', $section)
            ->name('admin.communication-center.page.'.$section);
    }

    Route::get('/communication-center/whatsapp/setup', [WhatsAppEngineController::class, 'index'])
        ->name('admin.communication-center.whatsapp.setup');
    Route::get('/communication-center/whatsapp/status', [WhatsAppEngineController::class, 'status'])
        ->middleware('throttle:120,1')
        ->name('admin.communication-center.whatsapp.status');
    Route::get('/communication-center/whatsapp/qr', [WhatsAppEngineController::class, 'qr'])
        ->middleware('throttle:120,1')
        ->name('admin.communication-center.whatsapp.qr');
    Route::post('/communication-center/whatsapp/messages', [WhatsAppEngineController::class, 'send'])
        ->middleware('throttle:60,1')
        ->name('admin.communication-center.whatsapp.send');

    Route::get('/communication-center/{section}/export', [CommunicationCenterController::class, 'export'])
        ->where('section', $communicationSectionPattern)
        ->name('admin.communication-center.export');

    Route::post('/communication-center/email/{conversation:uuid}/actions/{action}', [CommunicationEmailController::class, 'webAction'])
        ->where('action', 'resolve|reopen|escalate')
        ->name('admin.communication-center.email.action');

    Route::get('/communication-center/email/{conversation:uuid}/audit/export', [CommunicationEmailController::class, 'exportAudit'])
        ->name('admin.communication-center.email.audit.export');

    Route::post('/communication-center/email-templates/templates', [CommunicationTemplateController::class, 'store'])
        ->name('admin.communication-center.templates.store');

    Route::patch('/communication-center/email-templates/templates/{template:uuid}', [CommunicationTemplateController::class, 'update'])
        ->name('admin.communication-center.templates.update');

    Route::delete('/communication-center/email-templates/templates/{template:uuid}', [CommunicationTemplateController::class, 'destroy'])
        ->name('admin.communication-center.templates.destroy');

    Route::post('/communication-center/email-templates/templates/{template:uuid}/{action}', [CommunicationTemplateController::class, 'action'])
        ->where('action', 'activate|archive|duplicate|restore|submit_for_approval')
        ->name('admin.communication-center.templates.action');

    Route::post('/communication-center/approval-center/requests', [CommunicationApprovalController::class, 'store'])
        ->name('admin.communication-center.approvals.store');

    Route::patch('/communication-center/approval-center/requests/{approval:uuid}', [CommunicationApprovalController::class, 'update'])
        ->name('admin.communication-center.approvals.update');

    Route::delete('/communication-center/approval-center/requests/{approval:uuid}', [CommunicationApprovalController::class, 'destroy'])
        ->name('admin.communication-center.approvals.destroy');

    Route::post('/communication-center/approval-center/requests/{approval:uuid}/actions/{action}', [CommunicationApprovalController::class, 'action'])
        ->where('action', 'approve|reject|escalate|reopen|cancel')
        ->name('admin.communication-center.approvals.action');

    Route::get('/communication-center/approval-center/requests/{approval:uuid}/audit/export', [CommunicationApprovalController::class, 'exportAudit'])
        ->name('admin.communication-center.approvals.audit.export');

    Route::post('/communication-center/action-follow-ups/records', [CommunicationCenterController::class, 'storeAction'])
        ->defaults('section', 'action-follow-ups')
        ->name('admin.communication-center.actions.store');
    Route::patch('/communication-center/action-follow-ups/records/{record}', [CommunicationCenterController::class, 'updateAction'])
        ->defaults('section', 'action-follow-ups')
        ->name('admin.communication-center.actions.update');
    Route::delete('/communication-center/action-follow-ups/records/{record}', [CommunicationCenterController::class, 'destroyAction'])
        ->defaults('section', 'action-follow-ups')
        ->name('admin.communication-center.actions.destroy');
    Route::post('/communication-center/action-follow-ups/records/{record}/{action}', [CommunicationCenterController::class, 'actionAction'])
        ->defaults('section', 'action-follow-ups')
        ->where('action', 'complete|reopen')
        ->name('admin.communication-center.actions.action');

    Route::post('/communication-center/alerts-notifications/records', [CommunicationCenterController::class, 'storeAlert'])
        ->defaults('section', 'alerts-notifications')
        ->name('admin.communication-center.alerts.store');
    Route::patch('/communication-center/alerts-notifications/records/{record}', [CommunicationCenterController::class, 'updateAlert'])
        ->defaults('section', 'alerts-notifications')
        ->name('admin.communication-center.alerts.update');
    Route::delete('/communication-center/alerts-notifications/records/{record}', [CommunicationCenterController::class, 'destroyAlert'])
        ->defaults('section', 'alerts-notifications')
        ->name('admin.communication-center.alerts.destroy');
    Route::post('/communication-center/alerts-notifications/records/{record}/{action}', [CommunicationCenterController::class, 'alertAction'])
        ->defaults('section', 'alerts-notifications')
        ->where('action', 'acknowledge|resolve')
        ->name('admin.communication-center.alerts.action');

    Route::post('/communication-center/{section}/records', [CommunicationCenterController::class, 'storeRecord'])
        ->where('section', 'approval-center')
        ->name('admin.communication-center.record.store');

    Route::patch('/communication-center/{section}/records/{record}', [CommunicationCenterController::class, 'updateRecord'])
        ->where('section', 'approval-center')
        ->whereNumber('record')
        ->name('admin.communication-center.record.update');

    Route::delete('/communication-center/{section}/records/{record}', [CommunicationCenterController::class, 'destroyRecord'])
        ->where('section', 'approval-center')
        ->whereNumber('record')
        ->name('admin.communication-center.record.destroy');

    Route::post('/communication-center/{section}/records/{record}/{action}', [CommunicationCenterController::class, 'recordAction'])
        ->where('section', 'approval-center')
        ->whereNumber('record')
        ->where('action', 'approve|reject|complete|reopen|acknowledge|resolve|duplicate|archive')
        ->name('admin.communication-center.record.action');
});
