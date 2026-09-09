<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SpinController;
use App\Http\Controllers\SpinViewerController;

Route::get('/360-sitemap.xml',[SpinViewerController::class,'sitemap'])->name('spins.sitemap');
Route::get('/360/{spin:uuid}',[SpinViewerController::class,'show'])->name('spins.show');
Route::get('/360/{spin:uuid}/frames/{frame}',[SpinViewerController::class,'frame'])->whereNumber('frame')->name('spins.frame');
Route::post('/360/{spin:uuid}/visit',[SpinViewerController::class,'visit'])->middleware('throttle:30,1')->name('spins.visit');
Route::prefix('admin/resource/360-product-view')->middleware(['auth','admin'])->name('admin.spins.')->group(function(){
    Route::get('/',[SpinController::class,'index'])->name('index');
    Route::post('/',[SpinController::class,'store'])->name('store');
    Route::get('/export',[SpinController::class,'export'])->name('export');
    Route::post('/bulk',[SpinController::class,'bulk'])->name('bulk');
    Route::patch('/{spin}',[SpinController::class,'update'])->name('update');
    Route::get('/{spin}/audit',[SpinController::class,'audit'])->name('audit');
});
