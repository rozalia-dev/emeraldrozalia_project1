<?php

use App\Http\Controllers\Admin\TryOnController;
use App\Http\Controllers\TryOnViewerController;
use Illuminate\Support\Facades\Route;

Route::get('/try-on/{tryon:uuid}/assets/{asset}', [TryOnViewerController::class,'asset'])
    ->where('asset','preview|model')->name('tryons.asset');
Route::post('/try-on/{tryon:uuid}/visit', [TryOnViewerController::class,'visit'])
    ->middleware('throttle:30,1')->name('tryons.visit');

Route::prefix('admin/resource/virtual-try-on')->middleware(['auth','admin'])->name('admin.tryons.')->group(function () {
    Route::get('/', [TryOnController::class,'index'])->name('index');
    Route::post('/', [TryOnController::class,'store'])->name('store');
    Route::get('/export', [TryOnController::class,'export'])->name('export');
    Route::post('/bulk', [TryOnController::class,'bulk'])->name('bulk');
    Route::patch('/{tryon}', [TryOnController::class,'update'])->name('update');
    Route::get('/{tryon}/audit', [TryOnController::class,'audit'])->name('audit');
});
