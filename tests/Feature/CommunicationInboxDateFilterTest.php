<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationInboxDateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_date_range_controls_are_wired_to_the_existing_date_filters(): void
    {
        $script = file_get_contents(public_path('js/communication-center-reference.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("createField('date_from', 'From')", $script);
        $this->assertStringContainsString("createField('date_to', 'To')", $script);
        $this->assertStringContainsString('ccDateRangeFilter', $script);
        $this->assertStringContainsString("url.searchParams.set('date_from'", $script);
        $this->assertStringContainsString("url.searchParams.set('date_to'", $script);
    }

    public function test_inbox_filters_conversations_between_from_and_to_dates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $outside = Conversation::create([
            'channel' => 'email',
            'contact' => 'outside-range@example.com',
            'subject' => 'Outside date range',
            'priority' => 'normal',
            'status' => 'open',
            'metadata' => ['name' => 'Outside Range'],
        ]);
        $outside->forceFill([
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ])->saveQuietly();

        $inside = Conversation::create([
            'channel' => 'email',
            'contact' => 'inside-range@example.com',
            'subject' => 'Inside date range',
            'priority' => 'normal',
            'status' => 'open',
            'metadata' => ['name' => 'Inside Range'],
        ]);
        $inside->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->saveQuietly();

        $from = now()->subDays(5)->toDateString();
        $to = now()->toDateString();

        $this->actingAs($admin)
            ->get('/admin/resource/inbox?'.http_build_query([
                'date_from' => $from,
                'date_to' => $to,
            ]))
            ->assertOk()
            ->assertSeeText('Inside Range')
            ->assertDontSeeText('Outside Range')
            ->assertSeeText(date('d M Y', strtotime($from)).' - '.date('d M Y', strtotime($to)));
    }
}
