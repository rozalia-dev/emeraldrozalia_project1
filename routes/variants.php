<?php
use App\Http\Controllers\Admin\VariantBulkPriceController;
use App\Http\Controllers\Admin\VariantController;
use Illuminate\Support\Facades\Route;
Route::prefix('admin/resource/variants')->middleware(['auth','admin'])->name('admin.variants.')->group(function():void{
    Route::get('/',[VariantController::class,'index'])->name('index');
    Route::post('/',[VariantController::class,'store'])->name('store');
    Route::post('/bulk-create',[VariantController::class,'bulkCreate'])->name('bulk-create');
    Route::post('/bulk-price',VariantBulkPriceController::class)->name('bulk-price');
    Route::post('/bulk',[VariantController::class,'bulk'])->name('bulk');
    Route::post('/settings',[VariantController::class,'updateSettings'])->name('settings');
    Route::post('/import',[VariantController::class,'import'])->name('import');
    Route::get('/export',[VariantController::class,'export'])->name('export');
    Route::get('/{variant}/audit',[VariantController::class,'audit'])->name('audit');
    Route::post('/{variant}/media',[VariantController::class,'storeMedia'])->name('media.store');
    Route::delete('/{variant}/media/{media}',[VariantController::class,'destroyMedia'])->name('media.destroy');
    Route::patch('/{variant}',[VariantController::class,'update'])->name('update');
    Route::delete('/{variant}',[VariantController::class,'destroy'])->name('destroy');
});
