<?php

namespace Tests\Feature;

use App\Models\{AdminRecord, CommunicationAction, CommunicationAlert, Company, Conversation, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationWorkItemContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_actions_are_durable_idempotent_and_explicitly_transitioned(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = [
            'title' => 'Follow up with franchise documents',
            'category' => 'Franchise',
            'priority' => 'high',
            'assigned_to_name' => 'Sarah Kelly',
            'entity' => 'FRAN-TEST-001',
            'source' => 'Approval Center',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'status' => 'pending',
            'record_date' => '2026-09-10',
            'idempotency_key' => 'work-action-create-1',
        ];

        $this->actingAs($admin)
            ->post(route('admin.communication-center.actions.store'), $payload)
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.communication-center.actions.store'), $payload)
            ->assertRedirect();

        $action = CommunicationAction::query()->firstOrFail();
        $this->assertSame(1, CommunicationAction::query()->count());
        $this->assertSame(0, AdminRecord::query()->where('module', 'action-follow-ups')->count());
        $this->assertSame('ACT-', substr((string) $action->reference, 0, 4));

        $this->actingAs($admin)
            ->post(route('admin.communication-center.actions.action', [
                'record' => $action->uuid,
                'action' => 'complete',
            ]), [
                'idempotency_key' => 'work-action-complete-1',
                'expected_version' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('completed', $action->fresh()->status);
        $this->assertNotNull($action->fresh()->completed_at);

        $this->actingAs($admin)
            ->post(route('admin.communication-center.actions.action', [
                'record' => $action->uuid,
                'action' => 'complete',
            ]), [
                'idempotency_key' => 'work-action-complete-1',
                'expected_version' => 1,
            ])
            ->assertRedirect();
        $this->assertSame(1, CommunicationAction::query()->count());

        $this->actingAs($admin)
            ->post(route('admin.communication-center.actions.action', [
                'record' => $action->uuid,
                'action' => 'complete',
            ]), [
                'idempotency_key' => 'work-action-complete-2',
            ])
            ->assertStatus(409);

        $this->actingAs($admin)
            ->get(route('admin.communication-center.page.action-follow-ups'))
            ->assertOk()
            ->assertSeeText('Follow up with franchise documents');
    }

    public function test_alerts_have_durable_acknowledge_and_resolve_lifecycle(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = [
            'title' => 'Payment failed for order',
            'type' => 'Payment',
            'category' => 'Payment Failed',
            'severity' => 'critical',
            'assigned_to_name' => 'Finance Team',
            'source' => 'Payment Gateway',
            'entity' => 'ORD-TEST-001',
            'status' => 'unread',
            'record_date' => '2026-09-10',
            'idempotency_key' => 'work-alert-create-1',
        ];

        $this->actingAs($admin)
            ->post(route('admin.communication-center.alerts.store'), $payload)
            ->assertRedirect();

        $alert = CommunicationAlert::query()->firstOrFail();
        $this->assertSame(1, CommunicationAlert::query()->count());
        $this->assertSame('ALT-', substr((string) $alert->reference, 0, 4));

        $this->actingAs($admin)
            ->post(route('admin.communication-center.alerts.action', [
                'record' => $alert->uuid,
                'action' => 'acknowledge',
            ]), [
                'idempotency_key' => 'work-alert-ack-1',
                'expected_version' => 1,
            ])
            ->assertRedirect();
        $this->assertSame('acknowledged', $alert->fresh()->status);
        $this->assertNotNull($alert->fresh()->acknowledged_at);

        $this->actingAs($admin)
            ->post(route('admin.communication-center.alerts.action', [
                'record' => $alert->uuid,
                'action' => 'resolve',
            ]), [
                'idempotency_key' => 'work-alert-resolve-1',
                'expected_version' => 2,
            ])
            ->assertRedirect();
        $this->assertSame('resolved', $alert->fresh()->status);
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_work_items_retain_conversation_links_and_respect_tenant_ownership(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $companyA = Company::create([
            'name' => 'Communication A',
            'code' => 'communication-a',
            'country_code' => 'IE',
            'base_currency' => 'EUR',
            'default_locale' => 'en',
            'active' => true,
        ]);
        $companyB = Company::create([
            'name' => 'Communication B',
            'code' => 'communication-b',
            'country_code' => 'IE',
            'base_currency' => 'EUR',
            'default_locale' => 'en',
            'active' => true,
        ]);
        $conversationA = Conversation::create([
            'company_id' => $companyA->id,
            'channel' => 'email',
            'contact' => 'a@example.test',
            'subject' => 'Tenant A conversation',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $conversationB = Conversation::create([
            'company_id' => $companyB->id,
            'channel' => 'email',
            'contact' => 'b@example.test',
            'subject' => 'Tenant B conversation',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($admin)->withSession(['company_id' => $companyA->id])
            ->post(route('admin.communication-center.actions.store'), [
                'title' => 'Linked follow-up',
                'status' => 'pending',
                'conversation_uuid' => $conversationA->uuid,
                'idempotency_key' => 'linked-follow-up-a',
            ])
            ->assertRedirect();

        $action = CommunicationAction::query()->firstOrFail();
        $this->assertSame($conversationA->id, $action->conversation_id);

        $this->actingAs($admin)->withSession(['company_id' => $companyA->id])
            ->post(route('admin.communication-center.actions.store'), [
                'title' => 'Cross-tenant follow-up',
                'status' => 'pending',
                'conversation_uuid' => $conversationB->uuid,
                'idempotency_key' => 'linked-follow-up-b',
            ])
            ->assertNotFound();

        $this->assertSame(1, CommunicationAction::query()->count());
    }

    public function test_non_admin_cannot_create_or_transition_work_items(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post(route('admin.communication-center.actions.store'), [
                'title' => 'Should not be created',
                'status' => 'pending',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('communication_actions', 0);
    }
}
