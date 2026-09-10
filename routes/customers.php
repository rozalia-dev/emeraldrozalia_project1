<?php

use App\Http\Controllers\Admin\CustomerManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['web','auth','admin'])->name('admin.')->group(function () {
    Route::get('/resource/customers', [CustomerManagementController::class,'index'])->name('customers.index');
    Route::post('/resource/customers', [CustomerManagementController::class,'store'])->name('customers.store');
    Route::get('/resource/customers/export', [CustomerManagementController::class,'exportCustomers'])->name('customers.export');
    Route::post('/resource/customers/import', [CustomerManagementController::class,'importCustomers'])->name('customers.import');
    Route::patch('/resource/customers/{customer}', [CustomerManagementController::class,'update'])->name('customers.update');
    Route::delete('/resource/customers/{customer}', [CustomerManagementController::class,'destroy'])->name('customers.destroy');

    Route::get('/resource/customer-groups', [CustomerManagementController::class,'groups'])->name('customer-groups.index');
    Route::post('/resource/customer-groups', [CustomerManagementController::class,'storeGroup'])->name('customer-groups.store');
    Route::get('/resource/customer-groups/export', [CustomerManagementController::class,'exportGroups'])->name('customer-groups.export');
    Route::post('/resource/customer-groups/import', [CustomerManagementController::class,'importGroups'])->name('customer-groups.import');
    Route::patch('/resource/customer-groups/{group}', [CustomerManagementController::class,'updateGroup'])->name('customer-groups.update');
    Route::post('/resource/customer-groups/{group}/duplicate', [CustomerManagementController::class,'duplicateGroup'])->name('customer-groups.duplicate');
    Route::delete('/resource/customer-groups/{group}', [CustomerManagementController::class,'destroyGroup'])->name('customer-groups.destroy');

    Route::get('/resource/customer-segments', [CustomerManagementController::class,'segments'])->name('customer-segments.index');
    Route::post('/resource/customer-segments', [CustomerManagementController::class,'storeSegment'])->name('customer-segments.store');
    Route::get('/resource/customer-segments/export', [CustomerManagementController::class,'exportSegments'])->name('customer-segments.export');
    Route::post('/resource/customer-segments/import', [CustomerManagementController::class,'importSegments'])->name('customer-segments.import');
    Route::patch('/resource/customer-segments/{segment}', [CustomerManagementController::class,'updateSegment'])->name('customer-segments.update');
    Route::post('/resource/customer-segments/{segment}/duplicate', [CustomerManagementController::class,'duplicateSegment'])->name('customer-segments.duplicate');
    Route::delete('/resource/customer-segments/{segment}', [CustomerManagementController::class,'destroySegment'])->name('customer-segments.destroy');
});
