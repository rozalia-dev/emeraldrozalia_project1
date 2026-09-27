<?php

use App\Http\Controllers\Admin\AiFashionModelController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/resource/ai-fashion-model')->middleware(['auth','admin'])->name('admin.ai-fashion-model.')->group(function () {
    Route::get('/', [AiFashionModelController::class,'index'])->name('index');
    Route::post('/generate', [AiFashionModelController::class,'generate'])->name('generate');
    Route::get('/generations/{generation}/status', [AiFashionModelController::class,'status'])->name('status');
    Route::post('/generations/{generation}/approve', [AiFashionModelController::class,'approve'])->name('approve');
    Route::post('/generations/{generation}/publish', [AiFashionModelController::class,'publish'])->name('publish');
});
