<?php

use App\Http\Controllers\Admin\CustomerManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['web','auth','admin'])->name('admin.')->group(function () {
    Route::get('/customers', [CustomerManagementController::class,'index'])->name('customers.index');
    Route::post('/customers', [CustomerManagementController::class,'store'])->name('customers.store');
    Route::get('/customers/export', [CustomerManagementController::class,'exportCustomers'])->name('customers.export');
    Route::post('/customers/import', [CustomerManagementController::class,'importCustomers'])->name('customers.import');
    Route::patch('/customers/{customer}', [CustomerManagementController::class,'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerManagementController::class,'destroy'])->name('customers.destroy');

    Route::get('/customer-groups', [CustomerManagementController::class,'groups'])->name('customer-groups.index');
    Route::post('/customer-groups', [CustomerManagementController::class,'storeGroup'])->name('customer-groups.store');
    Route::get('/customer-groups/export', [CustomerManagementController::class,'exportGroups'])->name('customer-groups.export');
    Route::post('/customer-groups/import', [CustomerManagementController::class,'importGroups'])->name('customer-groups.import');
    Route::patch('/customer-groups/{group}', [CustomerManagementController::class,'updateGroup'])->name('customer-groups.update');
    Route::post('/customer-groups/{group}/duplicate', [CustomerManagementController::class,'duplicateGroup'])->name('customer-groups.duplicate');
    Route::delete('/customer-groups/{group}', [CustomerManagementController::class,'destroyGroup'])->name('customer-groups.destroy');

    Route::get('/customer-segments', [CustomerManagementController::class,'segments'])->name('customer-segments.index');
    Route::post('/customer-segments', [CustomerManagementController::class,'storeSegment'])->name('customer-segments.store');
    Route::get('/customer-segments/export', [CustomerManagementController::class,'exportSegments'])->name('customer-segments.export');
    Route::post('/customer-segments/import', [CustomerManagementController::class,'importSegments'])->name('customer-segments.import');
    Route::patch('/customer-segments/{segment}', [CustomerManagementController::class,'updateSegment'])->name('customer-segments.update');
    Route::post('/customer-segments/{segment}/duplicate', [CustomerManagementController::class,'duplicateSegment'])->name('customer-segments.duplicate');
    Route::delete('/customer-segments/{segment}', [CustomerManagementController::class,'destroySegment'])->name('customer-segments.destroy');
});
