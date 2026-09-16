<?php

namespace App\Http\Controllers;

use App\Services\AppointmentBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentAvailabilityController extends Controller
{
    public function __invoke(Request $request, AppointmentBookingService $booking): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $companyId = $booking->resolveCompanyId();

        return response()->json([
            'ok' => true,
            ...$booking->monthAvailability($companyId, (string) $data['month']),
        ]);
    }
}
