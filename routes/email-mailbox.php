<?php

use App\Http\Controllers\Admin\EmailMailboxController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['web', 'auth', 'communication.permission'])->name('admin.email-mailbox.')->group(function (): void {
    // Registered after communication-center.php so the dedicated mailbox owns
    // the existing /admin/resource/email URL without changing sidebar links.
    Route::get('/resource/email', [EmailMailboxController::class, 'index'])->name('index');
    Route::post('/communication-center/email/compose', [EmailMailboxController::class, 'compose'])->name('compose');
    Route::patch('/communication-center/email/drafts/{conversation:uuid}', [EmailMailboxController::class, 'updateDraft'])->name('draft.update');
    Route::patch('/communication-center/email/drafts/{conversation:uuid}/send', [EmailMailboxController::class, 'sendDraft'])->name('draft.send');
    Route::post('/communication-center/email/{conversation:uuid}/reply-mailbox', [EmailMailboxController::class, 'reply'])->name('reply');
    Route::post('/communication-center/email/{conversation:uuid}/trash', [EmailMailboxController::class, 'trash'])->name('trash');
    Route::post('/communication-center/email/trash/{uuid}/restore', [EmailMailboxController::class, 'restore'])->whereUuid('uuid')->name('restore');
    Route::delete('/communication-center/email/trash/{uuid}', [EmailMailboxController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
    Route::post('/communication-center/email/test', [EmailMailboxController::class, 'sendTest'])->middleware('throttle:5,10')->name('test');
});
