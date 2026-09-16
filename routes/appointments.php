<?php

use App\Http\Controllers\AppointmentAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::get('/appointments/availability', AppointmentAvailabilityController::class)
    ->middleware('throttle:60,1')
    ->name('appointments.availability');
