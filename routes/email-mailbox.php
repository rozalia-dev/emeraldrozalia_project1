<?php

use App\Http\Controllers\Admin\EmailMailboxController;
use App\Http\Controllers\Admin\EmailTemplateUiController;
use Illuminate\Support\Facades\Route;

// Keep the established Communication Center route name for the mailbox URL.
// These dedicated email routes are loaded after the generic Communication
// Center section routes so route caching keeps the specialized controllers.
Route::get('/admin/resource/email', [EmailMailboxController::class, 'index'])
    ->middleware(['web', 'auth', 'communication.permission'])
    ->name('admin.communication-center.page.email');

Route::get('/admin/resource/email-templates', [EmailTemplateUiController::class, 'page'])
    ->middleware(['web', 'auth', 'communication.permission'])
    ->name('admin.communication-center.page.email-templates');

Route::prefix('admin')->middleware(['web', 'auth', 'communication.permission'])->name('admin.email-mailbox.')->group(function (): void {
    Route::get('/communication-center/email/templates/available', [EmailTemplateUiController::class, 'available'])->name('templates.available');
    Route::get('/communication-center/email-templates/editor-options', [EmailTemplateUiController::class, 'editorOptions'])->name('templates.editor-options');
    Route::post('/communication-center/email/compose', [EmailMailboxController::class, 'compose'])->name('compose');
    Route::patch('/communication-center/email/drafts/{conversation:uuid}', [EmailMailboxController::class, 'updateDraft'])->name('draft.update');
    Route::post('/communication-center/email/drafts/{conversation:uuid}/send', [EmailMailboxController::class, 'sendDraft'])->name('draft.send');
    Route::post('/communication-center/email/{conversation:uuid}/reply-mailbox', [EmailMailboxController::class, 'reply'])->name('reply');
    Route::post('/communication-center/email/{conversation:uuid}/trash', [EmailMailboxController::class, 'trash'])->name('trash');
    Route::post('/communication-center/email/trash/{uuid}/restore', [EmailMailboxController::class, 'restore'])->whereUuid('uuid')->name('restore');
    Route::delete('/communication-center/email/trash/{uuid}', [EmailMailboxController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
    Route::post('/communication-center/email/test', [EmailMailboxController::class, 'sendTest'])->middleware('throttle:5,10')->name('test');
});