<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Conversation, FranchiseApplication, Order, SalesQuote, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchiseApplicationQuoteConversionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_active_activates_the_franchise_relationship_without_creating_a_sales_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $application = FranchiseApplication::create([
            'applicant_name' => 'Franchise Applicant',
            'email' => 'franchise-applicant@example.com',
            'territory' => 'Munster',
            'preferred_location' => 'Limerick',
            'status' => 'onboarding',
            'correlation_id' => '11111111-1111-4111-8111-111111111111',
            'data' => ['source' => 'public_franchise_form'],
        ]);
        $conversation = Conversation::create([
            'franchise_application_id' => $application->id,
            'channel' => 'web',
            'contact' => $application->email,
            'subject' => 'Franchise application',
            'status' => 'new',
            'priority' => 'high',
            'metadata' => ['franchise_application_uuid' => (string) $application->uuid],
        ]);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'franchise-activation-1')
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $application->refresh();
        $conversation->refresh();

        $this->assertSame('converted', $application->status);
        $this->assertNotNull(data_get($application->data, 'active_partner_at'));
        $this->assertSame('franchise_application_lifecycle', data_get($application->data, 'activation_source'));
        $this->assertSame('franchise-activation-1', data_get($application->data, 'activation_key'));
        $this->assertSame('converted', data_get($conversation->metadata, 'franchise_status'));
        $this->assertSame('open', $conversation->status);
        $this->assertDatabaseCount('sales_quotes', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertTrue(AuditLog::query()
            ->where('action', 'franchise.application.activated')
            ->where('subject_id', $application->id)
            ->exists());
    }

    public function test_franchise_application_must_complete_review_and_onboarding_before_activation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $application = FranchiseApplication::create([
            'applicant_name' => 'Approved But Not Onboarded',
            'email' => 'approved@example.com',
            'territory' => 'Connacht',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'franchise-activation-too-early')
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertStatus(422);

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertDatabaseCount('sales_quotes', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_franchise_partner_activation_is_idempotent_without_creating_an_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $application = FranchiseApplication::create([
            'applicant_name' => 'Replay Applicant',
            'email' => 'replay-applicant@example.com',
            'territory' => 'Leinster',
            'status' => 'onboarding',
        ]);
        $firstKey = 'franchise-activation-replay';

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', $firstKey)
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', $firstKey)
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'franchise-activation-other')
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertStatus(409);

        $this->assertSame('converted', $application->fresh()->status);
        $this->assertSame($firstKey, data_get($application->fresh()->data, 'activation_key'));
        $this->assertDatabaseCount('sales_quotes', 0);
        $this->assertDatabaseCount('orders', 0);
    }
}
