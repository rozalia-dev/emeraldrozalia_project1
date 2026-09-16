<?php

namespace App\Services;

use App\Models\AppointmentBooking;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class AppointmentBookingService
{
    public const TIMEZONE = 'Europe/Dublin';
    public const TIMES = ['13:00', '13:15', '13:30', '13:45'];
    public const MODES = ['in_person', 'microsoft_teams', 'google_meet'];

    public function validateSelection(string $date, string $time, string $mode, ?int $companyId = null): CarbonImmutable
    {
        try {
            $slot = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, self::TIMEZONE);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['meeting_date' => 'Please choose a valid appointment date and time.']);
        }

        if (! in_array($time, self::TIMES, true)) {
            throw ValidationException::withMessages(['meeting_time' => 'Appointments are available from 13:00 to 14:00 in 15-minute slots.']);
        }
        if (! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['meeting_mode' => 'Choose In Person, Microsoft Teams, or Google Meet.']);
        }
        if ($slot->isWeekend()) {
            throw ValidationException::withMessages(['meeting_date' => 'Appointments are available Monday to Friday only.']);
        }
        if ($this->isIrishPublicHoliday($slot)) {
            throw ValidationException::withMessages(['meeting_date' => 'Appointments are not available on Irish bank or public holidays.']);
        }
        if ($slot->lessThanOrEqualTo(now(self::TIMEZONE))) {
            throw ValidationException::withMessages(['meeting_time' => 'Please choose a future appointment time.']);
        }
        if ($companyId && AppointmentBooking::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('meeting_date', $date)
            ->where('meeting_time', $time.':00')
            ->where('status', '!=', 'cancelled')
            ->exists()) {
            throw ValidationException::withMessages(['meeting_time' => 'That appointment time has just been booked. Please choose another available time.']);
        }

        return $slot;
    }

    public function availability(int $companyId, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $booked = AppointmentBooking::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('meeting_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->get(['meeting_date', 'meeting_time'])
            ->groupBy(fn (AppointmentBooking $booking) => $booking->meeting_date->toDateString());

        $days = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $holiday = $this->isIrishPublicHoliday($date);
            $closed = $date->isWeekend() || $holiday || $date->endOfDay()->isPast();
            $taken = $booked->get($key, collect())
                ->map(fn (AppointmentBooking $booking) => substr((string) $booking->meeting_time, 0, 5))
                ->all();
            $available = $closed ? [] : array_values(array_diff(self::TIMES, $taken));
            $days[$key] = [
                'available' => $available,
                'remaining' => count($available),
                'closed' => $closed,
                'holiday' => $holiday,
            ];
        }

        return $days;
    }

    public function isIrishPublicHoliday(CarbonImmutable $date): bool
    {
        return in_array($date->toDateString(), $this->irishPublicHolidayDates($date->year), true);
    }

    public function irishPublicHolidayDates(int $year): array
    {
        $dates = collect();
        $fixed = [
            CarbonImmutable::create($year, 1, 1, 0, 0, 0, self::TIMEZONE),
            CarbonImmutable::create($year, 3, 17, 0, 0, 0, self::TIMEZONE),
            CarbonImmutable::create($year, 12, 25, 0, 0, 0, self::TIMEZONE),
            CarbonImmutable::create($year, 12, 26, 0, 0, 0, self::TIMEZONE),
        ];
        foreach ($fixed as $holiday) {
            $dates->push($holiday->toDateString());
        }

        // St Brigid's Day: first Monday in February, except when 1 February is a Friday.
        $feb1 = CarbonImmutable::create($year, 2, 1, 0, 0, 0, self::TIMEZONE);
        $stBrigid = $feb1->isFriday() ? $feb1 : ($feb1->isMonday() ? $feb1 : $feb1->next(CarbonImmutable::MONDAY));
        $dates->push($stBrigid->toDateString());

        $easterSunday = CarbonImmutable::createFromTimestamp(easter_date($year), self::TIMEZONE)->startOfDay();
        $dates->push($easterSunday->addDay()->toDateString());
        $dates->push($this->nthWeekday($year, 5, CarbonImmutable::MONDAY, 1)->toDateString());
        $dates->push($this->nthWeekday($year, 6, CarbonImmutable::MONDAY, 1)->toDateString());
        $dates->push($this->nthWeekday($year, 8, CarbonImmutable::MONDAY, 1)->toDateString());
        $dates->push($this->lastWeekday($year, 10, CarbonImmutable::MONDAY)->toDateString());

        // Treat observed weekday bank closures as unavailable when a fixed-date
        // public holiday falls on a weekend. This also handles the Christmas pair.
        $occupied = $dates->flip();
        foreach ($fixed as $holiday) {
            if (! $holiday->isWeekend()) {
                continue;
            }
            $candidate = $holiday->addDay();
            while ($candidate->isWeekend() || $occupied->has($candidate->toDateString())) {
                $candidate = $candidate->addDay();
            }
            $dates->push($candidate->toDateString());
            $occupied->put($candidate->toDateString(), true);
        }

        return $dates->unique()->sort()->values()->all();
    }

    private function nthWeekday(int $year, int $month, int $weekday, int $nth): CarbonImmutable
    {
        $date = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE);
        if ($date->dayOfWeek !== $weekday) {
            $date = $date->next($weekday);
        }

        return $date->addWeeks($nth - 1);
    }

    private function lastWeekday(int $year, int $month, int $weekday): CarbonImmutable
    {
        $date = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE)->endOfMonth()->startOfDay();
        return $date->dayOfWeek === $weekday ? $date : $date->previous($weekday);
    }
}
