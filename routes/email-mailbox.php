<?php

use App\Http\Controllers\Admin\EmailMailboxController;
use Illuminate\Support\Facades\Route;

// Keep the established Communication Center route name for the mailbox URL.
// This is the only GET /admin/resource/email route, so route caching cannot
// replace the named route with a later duplicate registration.
Route::get('/admin/resource/email', [EmailMailboxController::class, 'index'])
    ->middleware(['web', 'auth', 'communication.permission'])
    ->name('admin.communication-center.page.email');

Route::prefix('admin')->middleware(['web', 'auth', 'communication.permission'])->name('admin.email-mailbox.')->group(function (): void {
    Route::post('/communication-center/email/compose', [EmailMailboxController::class, 'compose'])->name('compose');
    Route::patch('/communication-center/email/drafts/{conversation:uuid}', [EmailMailboxController::class, 'updateDraft'])->name('draft.update');
    Route::post('/communication-center/email/drafts/{conversation:uuid}/send', [EmailMailboxController::class, 'sendDraft'])->name('draft.send');
    Route::post('/communication-center/email/{conversation:uuid}/reply-mailbox', [EmailMailboxController::class, 'reply'])->name('reply');
    Route::post('/communication-center/email/{conversation:uuid}/trash', [EmailMailboxController::class, 'trash'])->name('trash');
    Route::post('/communication-center/email/trash/{uuid}/restore', [EmailMailboxController::class, 'restore'])->whereUuid('uuid')->name('restore');
    Route::delete('/communication-center/email/trash/{uuid}', [EmailMailboxController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
    Route::post('/communication-center/email/test', [EmailMailboxController::class, 'sendTest'])->middleware('throttle:5,10')->name('test');
});
