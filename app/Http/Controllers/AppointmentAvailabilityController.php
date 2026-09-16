<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\AppointmentBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AppointmentAvailabilityController extends Controller
{
    public function __invoke(Request $request, AppointmentBookingService $bookings): JsonResponse
    {
        $monthValue = (string) $request->query('month', now(AppointmentBookingService::TIMEZONE)->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $monthValue)) {
            throw ValidationException::withMessages(['month' => 'Use a valid YYYY-MM month.']);
        }

        try {
            $month = CarbonImmutable::createFromFormat('!Y-m', $monthValue, AppointmentBookingService::TIMEZONE);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['month' => 'Use a valid YYYY-MM month.']);
        }

        $today = CarbonImmutable::now(AppointmentBookingService::TIMEZONE)->startOfMonth();
        if ($month->lt($today) || $month->gt($today->addMonths(12))) {
            throw ValidationException::withMessages(['month' => 'Appointments can be viewed up to 12 months ahead.']);
        }

        $companyId = (int) $request->session()->get('company_id', 0);
        if ($companyId <= 0 || ! Company::query()->whereKey($companyId)->where('active', true)->exists()) {
            $companyId = (int) Company::query()->where('active', true)->orderBy('id')->value('id');
        }
        abort_unless($companyId > 0, 503, 'Appointment booking is temporarily unavailable.');

        return response()->json([
            'month' => $month->format('Y-m'),
            'timezone' => AppointmentBookingService::TIMEZONE,
            'times' => AppointmentBookingService::TIMES,
            'modes' => [
                ['value' => 'in_person', 'label' => 'In Person — Our Office'],
                ['value' => 'microsoft_teams', 'label' => 'Microsoft Teams'],
                ['value' => 'google_meet', 'label' => 'Google Meet'],
            ],
            'days' => $bookings->availability($companyId, $month),
        ]);
    }
}
