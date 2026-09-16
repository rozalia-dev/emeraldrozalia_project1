<?php

use App\Http\Controllers\PublicInquiryController;
use Illuminate\Support\Facades\Route;

Route::post('/enquiry', PublicInquiryController::class)
    ->middleware('throttle:10,1')
    ->name('inquiry');
