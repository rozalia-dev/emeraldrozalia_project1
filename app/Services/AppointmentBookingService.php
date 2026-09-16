<?php

namespace App\Services;

use App\Models\Inquiry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AppointmentBookingService
{
    public const TIMEZONE = 'Europe/Dublin';
    public const MAX_PER_DAY = 4;
    public const SLOTS = ['13:00', '13:15', '13:30', '13:45'];
    public const MEETING_TYPES = [
        'in_person' => 'In person — Emerald Rozalia office',
        'microsoft_teams' => 'Microsoft Teams',
        'google_meet' => 'Google Meet',
    ];

    public function assertCanBook(int $companyId, string $date, string $time, string $meetingType): void
    {
        $day = $this->parseDate($date);
        $slot = $this->parseSlot($date, $time);

        if (! array_key_exists($meetingType, self::MEETING_TYPES)) {
            throw ValidationException::withMessages([
                'meeting_type' => 'Choose in person, Microsoft Teams or Google Meet.',
            ]);
        }

        if ($day->isWeekend()) {
            throw ValidationException::withMessages([
                'meeting_date' => 'Appointments are available Monday to Friday only.',
            ]);
        }

        if ($holiday = $this->holidayName($day)) {
            throw ValidationException::withMessages([
                'meeting_date' => $holiday.' is unavailable for appointments.',
            ]);
        }

        if (! in_array($time, self::SLOTS, true)) {
            throw ValidationException::withMessages([
                'meeting_time' => 'Appointments are available between 13:00 and 14:00 Irish time in 15-minute slots.',
            ]);
        }

        if ($slot->lessThanOrEqualTo(CarbonImmutable::now(self::TIMEZONE))) {
            throw ValidationException::withMessages([
                'meeting_time' => 'Please choose a future appointment slot.',
            ]);
        }

        $bookings = $this->bookingsForDate($companyId, $date);
        if ($bookings->count() >= self::MAX_PER_DAY) {
            throw ValidationException::withMessages([
                'meeting_date' => 'This date is fully booked. Please choose another available weekday.',
            ]);
        }

        if ($bookings->contains(fn (Inquiry $inquiry): bool => (string) data_get($inquiry->meta, 'meeting.time') === $time)) {
            throw ValidationException::withMessages([
                'meeting_time' => 'That appointment time has already been booked. Please choose another available time.',
            ]);
        }
    }

    public function monthAvailability(int $companyId, string $month): array
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m', $month, self::TIMEZONE)->startOfMonth();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['month' => 'Use a valid month in YYYY-MM format.']);
        }

        if ($start->format('Y-m') !== $month) {
            throw ValidationException::withMessages(['month' => 'Use a valid month in YYYY-MM format.']);
        }

        $now = CarbonImmutable::now(self::TIMEZONE);
        $days = [];
        for ($day = $start; $day->month === $start->month; $day = $day->addDay()) {
            $date = $day->format('Y-m-d');
            $holiday = $this->holidayName($day);
            $bookings = $this->bookingsForDate($companyId, $date);
            $bookedTimes = $bookings
                ->map(fn (Inquiry $inquiry) => (string) data_get($inquiry->meta, 'meeting.time'))
                ->filter()
                ->values()
                ->all();

            $slots = [];
            foreach (self::SLOTS as $time) {
                $slot = $this->parseSlot($date, $time);
                $slots[] = [
                    'time' => $time,
                    'available' => ! $day->isWeekend()
                        && $holiday === null
                        && $slot->greaterThan($now)
                        && $bookings->count() < self::MAX_PER_DAY
                        && ! in_array($time, $bookedTimes, true),
                ];
            }

            $availableCount = count(array_filter($slots, fn (array $slot): bool => $slot['available']));
            $reason = null;
            if ($day->isWeekend()) {
                $reason = 'weekend';
            } elseif ($holiday !== null) {
                $reason = 'public_holiday';
            } elseif ($bookings->count() >= self::MAX_PER_DAY) {
                $reason = 'fully_booked';
            } elseif ($availableCount === 0) {
                $reason = 'past';
            }

            $days[$date] = [
                'available' => $availableCount > 0,
                'remaining' => min($availableCount, max(0, self::MAX_PER_DAY - $bookings->count())),
                'booked' => $bookings->count(),
                'reason' => $reason,
                'holiday' => $holiday,
                'slots' => $slots,
            ];
        }

        return [
            'month' => $start->format('Y-m'),
            'timezone' => self::TIMEZONE,
            'window' => '13:00-14:00',
            'maximum_per_day' => self::MAX_PER_DAY,
            'meeting_types' => self::MEETING_TYPES,
            'days' => $days,
        ];
    }

    public function meetingTypeLabel(string $meetingType): string
    {
        return self::MEETING_TYPES[$meetingType] ?? $meetingType;
    }

    public function holidayName(CarbonImmutable $date): ?string
    {
        $holidays = $this->holidaysForYear($date->year);

        return $holidays[$date->format('Y-m-d')] ?? null;
    }

    private function bookingsForDate(int $companyId, string $date): Collection
    {
        return Inquiry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['cancelled'])
            ->where('meta->meeting->date', $date)
            ->get(['id', 'meta', 'status']);
    }

    private function parseDate(string $date): CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['meeting_date' => 'Choose a valid appointment date.']);
        }

        if ($parsed->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['meeting_date' => 'Choose a valid appointment date.']);
        }

        return $parsed;
    }

    private function parseSlot(string $date, string $time): CarbonImmutable
    {
        try {
            $slot = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, self::TIMEZONE);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['meeting_time' => 'Choose a valid appointment time.']);
        }

        if ($slot->format('Y-m-d H:i') !== $date.' '.$time) {
            throw ValidationException::withMessages(['meeting_time' => 'Choose a valid appointment time.']);
        }

        return $slot;
    }

    private function holidaysForYear(int $year): array
    {
        $holidays = [];
        $add = static function (array &$target, CarbonImmutable $date, string $name): void {
            $target[$date->format('Y-m-d')] = $name;
        };

        $fixed = [
            [CarbonImmutable::create($year, 1, 1, 0, 0, 0, self::TIMEZONE), "New Year's Day"],
            [CarbonImmutable::create($year, 3, 17, 0, 0, 0, self::TIMEZONE), "St Patrick's Day"],
            [CarbonImmutable::create($year, 12, 25, 0, 0, 0, self::TIMEZONE), 'Christmas Day'],
            [CarbonImmutable::create($year, 12, 26, 0, 0, 0, self::TIMEZONE), "St Stephen's Day"],
        ];

        foreach ($fixed as [$date, $name]) {
            $add($holidays, $date, $name);
        }

        // Emerald Rozalia office also closes on the normal weekday observed by
        // offices/banks when one of the fixed holidays falls on a weekend.
        $occupied = array_fill_keys(array_keys($holidays), true);
        foreach ($fixed as [$date, $name]) {
            if (! $date->isWeekend()) {
                continue;
            }
            $observed = $date->next(CarbonImmutable::MONDAY);
            while (isset($occupied[$observed->format('Y-m-d')])) {
                $observed = $observed->addDay();
                while ($observed->isWeekend()) {
                    $observed = $observed->addDay();
                }
            }
            $key = $observed->format('Y-m-d');
            $occupied[$key] = true;
            $holidays[$key] = $name.' (office observed)';
        }

        if ($year >= 2023) {
            $februaryFirst = CarbonImmutable::create($year, 2, 1, 0, 0, 0, self::TIMEZONE);
            $stBrigid = $februaryFirst->isFriday()
                ? $februaryFirst
                : $this->firstWeekdayOfMonth($year, 2, CarbonImmutable::MONDAY);
            $add($holidays, $stBrigid, "St Brigid's Day");
        }

        $add($holidays, $this->easterSunday($year)->addDay(), 'Easter Monday');
        $add($holidays, $this->firstWeekdayOfMonth($year, 5, CarbonImmutable::MONDAY), 'May Bank Holiday');
        $add($holidays, $this->firstWeekdayOfMonth($year, 6, CarbonImmutable::MONDAY), 'June Bank Holiday');
        $add($holidays, $this->firstWeekdayOfMonth($year, 8, CarbonImmutable::MONDAY), 'August Bank Holiday');
        $add($holidays, $this->lastWeekdayOfMonth($year, 10, CarbonImmutable::MONDAY), 'October Bank Holiday');

        ksort($holidays);

        return $holidays;
    }

    private function firstWeekdayOfMonth(int $year, int $month, int $weekday): CarbonImmutable
    {
        $date = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE);

        return $date->dayOfWeek === $weekday ? $date : $date->next($weekday);
    }

    private function lastWeekdayOfMonth(int $year, int $month, int $weekday): CarbonImmutable
    {
        $date = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE)->endOfMonth()->startOfDay();

        return $date->dayOfWeek === $weekday ? $date : $date->previous($weekday);
    }

    private function easterSunday(int $year): CarbonImmutable
    {
        // Anonymous Gregorian computus; avoids requiring PHP's optional calendar extension.
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, self::TIMEZONE);
    }
}
