<?php

use App\Http\Controllers\Admin\CommunicationCenterController;
use Illuminate\Support\Facades\Route;

$communicationSectionPattern = implode('|', CommunicationCenterController::SECTIONS);

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () use ($communicationSectionPattern): void {
    Route::get('/resource/{section}', [CommunicationCenterController::class, 'show'])
        ->where('section', $communicationSectionPattern)
        ->name('admin.communication-center.page');

    Route::get('/communication-center/{section}/export', [CommunicationCenterController::class, 'export'])
        ->where('section', $communicationSectionPattern)
        ->name('admin.communication-center.export');

    Route::post('/communication-center/{section}/records', [CommunicationCenterController::class, 'storeRecord'])
        ->where('section', 'email-templates|approval-center|action-follow-ups|alerts-notifications')
        ->name('admin.communication-center.record.store');

    Route::patch('/communication-center/{section}/records/{record}', [CommunicationCenterController::class, 'updateRecord'])
        ->where('section', 'email-templates|approval-center|action-follow-ups|alerts-notifications')
        ->whereNumber('record')
        ->name('admin.communication-center.record.update');

    Route::delete('/communication-center/{section}/records/{record}', [CommunicationCenterController::class, 'destroyRecord'])
        ->where('section', 'email-templates|approval-center|action-follow-ups|alerts-notifications')
        ->whereNumber('record')
        ->name('admin.communication-center.record.destroy');

    Route::post('/communication-center/{section}/records/{record}/{action}', [CommunicationCenterController::class, 'recordAction'])
        ->where('section', 'email-templates|approval-center|action-follow-ups|alerts-notifications')
        ->whereNumber('record')
        ->where('action', 'approve|reject|complete|reopen|acknowledge|resolve|duplicate|archive')
        ->name('admin.communication-center.record.action');
});
