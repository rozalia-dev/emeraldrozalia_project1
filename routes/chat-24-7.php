<?php

use App\Http\Controllers\PublicChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('chat-24-7')->middleware('throttle:60,1')->name('chat24.')->group(function (): void {
    Route::post('/start', [PublicChatController::class, 'start'])->name('start');
    Route::post('/{conversation}/messages', [PublicChatController::class, 'send'])->whereUuid('conversation')->name('send');
    Route::get('/{conversation}/messages', [PublicChatController::class, 'messages'])->whereUuid('conversation')->name('messages');
    Route::post('/{conversation}/human', [PublicChatController::class, 'human'])->whereUuid('conversation')->name('human');
});
