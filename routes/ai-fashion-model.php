<?php

use App\Http\Controllers\Admin\AiFashionModelController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/resource/ai-fashion-model')->middleware(['auth','admin'])->name('admin.ai-fashion-model.')->group(function () {
    Route::get('/', [AiFashionModelController::class,'index'])->name('index');
    Route::post('/generate', [AiFashionModelController::class,'generate'])->name('generate');
});
