<?php

namespace Tests\Feature;

use App\Models\AppointmentReservation;
use App\Models\Company;
use App\Models\Inquiry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_availability_excludes_weekends_and_irish_public_holidays(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 10:00:00', 'Europe/Dublin'));
        $company = $this->company();

        $this->withSession(['company_id' => $company->id])
            ->getJson(route('appointments.availability', ['month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('maximum_per_day', 4)
            ->assertJsonPath('window', '13:00-14:00')
            ->assertJsonPath('days.2026-10-03.available', false)
            ->assertJsonPath('days.2026-10-03.reason', 'weekend')
            ->assertJsonPath('days.2026-10-26.available', false)
            ->assertJsonPath('days.2026-10-26.reason', 'public_holiday')
            ->assertJsonPath('days.2026-10-26.holiday', 'October Bank Holiday')
            ->assertJsonPath('days.2026-10-05.available', true)
            ->assertJsonCount(4, 'days.2026-10-05.slots');
    }

    public function test_a_day_accepts_only_four_appointments_between_1300_and_1400(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-16 10:00:00', 'Europe/Dublin'));
        $company = $this->company();
        $date = '2026-09-21';
        $slots = ['13:00', '13:15', '13:30', '13:45'];
        $types = ['in_person', 'microsoft_teams', 'google_meet', 'in_person'];

        foreach ($slots as $index => $slot) {
            $this->withSession(['company_id' => $company->id])
                ->post(route('inquiry'), $this->payload($index + 1, $date, $slot, $types[$index]))
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(4, Inquiry::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertSame(4, AppointmentReservation::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $first = Inquiry::withoutGlobalScopes()->where('company_id', $company->id)->oldest('id')->firstOrFail();
        $this->assertSame('in_person', data_get($first->meta, 'meeting.type'));
        $this->assertStringContainsString('Preferred meeting type: In person — Emerald Rozalia office.', (string) $first->message);

        $this->withSession(['company_id' => $company->id])
            ->post(route('inquiry'), $this->payload(5, $date, '13:00', 'microsoft_teams'))
            ->assertRedirect()
            ->assertSessionHasErrors('meeting_date');

        $this->assertSame(4, Inquiry::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertSame(4, AppointmentReservation::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $this->withSession(['company_id' => $company->id])
            ->getJson(route('appointments.availability', ['month' => '2026-09']))
            ->assertOk()
            ->assertJsonPath('days.2026-09-21.available', false)
            ->assertJsonPath('days.2026-09-21.reason', 'fully_booked')
            ->assertJsonPath('days.2026-09-21.booked', 4);
    }

    public function test_booking_rejects_holidays_weekends_and_times_outside_the_window(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 10:00:00', 'Europe/Dublin'));
        $company = $this->company();

        $this->withSession(['company_id' => $company->id])
            ->post(route('inquiry'), $this->payload(1, '2026-10-26', '13:00', 'google_meet'))
            ->assertRedirect()
            ->assertSessionHasErrors('meeting_date');

        $this->withSession(['company_id' => $company->id])
            ->post(route('inquiry'), $this->payload(2, '2026-10-03', '13:00', 'microsoft_teams'))
            ->assertRedirect()
            ->assertSessionHasErrors('meeting_date');

        $this->withSession(['company_id' => $company->id])
            ->post(route('inquiry'), $this->payload(3, '2026-10-05', '14:00', 'in_person'))
            ->assertRedirect()
            ->assertSessionHasErrors('meeting_time');

        $this->assertSame(0, AppointmentReservation::withoutGlobalScopes()->count());
    }

    public function test_all_three_meeting_methods_are_accepted_and_reservations_are_unique_per_slot(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-16 10:00:00', 'Europe/Dublin'));
        $company = $this->company();
        $date = '2026-09-22';

        foreach ([
            ['13:00', 'in_person'],
            ['13:15', 'microsoft_teams'],
            ['13:30', 'google_meet'],
        ] as $index => [$time, $type]) {
            $this->withSession(['company_id' => $company->id])
                ->post(route('inquiry'), $this->payload($index + 1, $date, $time, $type))
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('appointment_reservations', [
            'company_id' => $company->id,
            'appointment_date' => $date,
            'appointment_time' => '13:00',
            'meeting_type' => 'in_person',
        ]);
        $this->assertDatabaseHas('appointment_reservations', [
            'company_id' => $company->id,
            'appointment_date' => $date,
            'appointment_time' => '13:15',
            'meeting_type' => 'microsoft_teams',
        ]);
        $this->assertDatabaseHas('appointment_reservations', [
            'company_id' => $company->id,
            'appointment_date' => $date,
            'appointment_time' => '13:30',
            'meeting_type' => 'google_meet',
        ]);
    }

    private function company(): Company
    {
        return Company::create([
            'name' => 'Appointment Test Company',
            'code' => 'APT-'.str()->upper(str()->random(6)),
            'active' => true,
        ]);
    }

    private function payload(int $number, string $date, string $time, string $meetingType): array
    {
        return [
            'type' => 'contact',
            'name' => 'Appointment Customer '.$number,
            'email' => 'appointment'.$number.'@example.test',
            'phone' => '+3538700000'.$number,
            'subject' => 'Appointment request',
            'message' => 'I would like to discuss Emerald Rozalia products.',
            'consent' => '1',
            'meeting_date' => $date,
            'meeting_time' => $time,
            'meeting_type' => $meetingType,
        ];
    }
}
