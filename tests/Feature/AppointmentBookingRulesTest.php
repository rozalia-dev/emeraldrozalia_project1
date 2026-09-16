<?php

namespace Tests\Feature;

use App\Models\AppointmentBooking;
use App\Models\Company;
use App\Models\Inquiry;
use App\Services\AppointmentBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentBookingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_four_15_minute_slots_are_offered_between_13_and_14_with_three_meeting_modes(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-16 10:00', 'Europe/Dublin'));
        $company = $this->company();

        $response = $this->withSession(['company_id' => $company->id])
            ->getJson('/chat-24-7/appointments/availability?month=2026-09')
            ->assertOk()
            ->assertJsonPath('times', ['13:00', '13:15', '13:30', '13:45'])
            ->assertJsonPath('modes.0.value', 'in_person')
            ->assertJsonPath('modes.1.value', 'microsoft_teams')
            ->assertJsonPath('modes.2.value', 'google_meet');

        $this->assertSame(4, $response->json('days.2026-09-18.remaining'));
    }

    public function test_booking_reserves_one_of_four_daily_slots_and_stores_meeting_mode(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-16 10:00', 'Europe/Dublin'));
        $company = $this->company();
        session(['company_id' => $company->id]);

        $inquiry = $this->createMeetingInquiry($company, '2026-09-18', '13:00', 'microsoft_teams');
        $booking = AppointmentBooking::withoutGlobalScopes()->where('inquiry_id', $inquiry->id)->firstOrFail();

        $this->assertSame('microsoft_teams', $booking->meeting_mode);
        $this->assertSame('13:00', substr((string) $booking->meeting_time, 0, 5));
        $this->assertSame('microsoft_teams', data_get($inquiry->fresh()->meta, 'meeting.mode'));

        $availability = app(AppointmentBookingService::class)
            ->availability($company->id, CarbonImmutable::parse('2026-09-01', 'Europe/Dublin'));
        $this->assertSame(3, $availability['2026-09-18']['remaining']);
        $this->assertNotContains('13:00', $availability['2026-09-18']['available']);
    }

    public function test_fifth_person_cannot_book_after_four_slots_are_taken(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-16 10:00', 'Europe/Dublin'));
        $company = $this->company();
        session(['company_id' => $company->id]);

        foreach (AppointmentBookingService::TIMES as $index => $time) {
            $this->createMeetingInquiry($company, '2026-09-18', $time, $index % 2 ? 'google_meet' : 'in_person');
        }

        $availability = app(AppointmentBookingService::class)
            ->availability($company->id, CarbonImmutable::parse('2026-09-01', 'Europe/Dublin'));
        $this->assertSame(0, $availability['2026-09-18']['remaining']);
        $this->assertSame([], $availability['2026-09-18']['available']);

        $this->expectException(ValidationException::class);
        app(AppointmentBookingService::class)->validateSelection('2026-09-18', '13:00', 'in_person', $company->id);
    }

    public function test_weekends_and_irish_public_holidays_are_closed(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-16 10:00', 'Europe/Dublin'));
        $company = $this->company();
        $service = app(AppointmentBookingService::class);

        $october = $service->availability($company->id, CarbonImmutable::parse('2026-10-01', 'Europe/Dublin'));
        $this->assertTrue($october['2026-10-26']['holiday']); // October public holiday.
        $this->assertTrue($october['2026-10-26']['closed']);
        $this->assertTrue($october['2026-10-24']['closed']); // Saturday.

        $this->assertContains('2026-12-25', $service->irishPublicHolidayDates(2026));
        $this->assertContains('2026-12-28', $service->irishPublicHolidayDates(2026)); // observed after weekend St Stephen's Day.
    }

    private function company(): Company
    {
        return Company::create([
            'name' => 'Emerald Appointment Tenant',
            'code' => 'APT-'.str()->upper(str()->random(6)),
            'active' => true,
        ]);
    }

    private function createMeetingInquiry(Company $company, string $date, string $time, string $mode): Inquiry
    {
        return Inquiry::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'type' => 'contact',
            'name' => 'Appointment Customer',
            'email' => str()->lower(str()->random(8)).'@example.test',
            'subject' => 'Appointment request',
            'message' => 'Please book a meeting.',
            'meeting_mode' => $mode,
            'meta' => [
                'source' => 'public_contact_form',
                'meeting' => ['date' => $date, 'time' => $time],
            ],
            'status' => 'new',
        ]);
    }
}
