<?php

namespace Tests\Feature;

use App\Events\CommunicationConversationChanged;
use App\Jobs\DeliverCommunicationMessage;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationEmailDashboardContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_dashboard_searches_related_records_by_uuid_and_date(): void
    {
        [$company, $admin, $customer, $order] = $this->context();
        $messageUuid = (string) Str::uuid();
        $conversation = Conversation::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'channel' => 'email',
            'contact' => $customer->email,
            'subject' => 'Factory quotation',
            'status' => 'open',
            'priority' => 'normal',
            'metadata' => [
                'name' => $customer->name,
                'order_reference' => $order->number,
                'uid' => 'EMAIL-UID-142',
            ],
            'created_at' => Carbon::parse('2026-09-10 09:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 09:00:00'),
        ]);
        $conversation->messages()->create([
            'uuid' => $messageUuid,
            'direction' => 'inbound',
            'body' => 'Please confirm the factory quotation.',
            'delivery_status' => 'stored',
            'sent_at' => Carbon::parse('2026-09-10 09:01:00'),
        ]);
        $outsideRange = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'email',
            'contact' => 'outside@example.test',
            'subject' => 'Outside date range',
            'status' => 'new',
            'created_at' => Carbon::parse('2026-08-01 09:00:00'),
            'updated_at' => Carbon::parse('2026-08-01 09:00:00'),
        ]);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->get('/admin/resource/email?'.http_build_query([
                'q' => 'factory quotation',
                'customer' => $customer->name,
                'order' => $order->number,
                'uid' => $messageUuid,
                'date_from' => '2026-09-10',
                'date_to' => '2026-09-10',
            ]))
            ->assertOk()
            ->assertSeeText('Factory quotation')
            ->assertSeeText($conversation->uuid)
            ->assertDontSeeText($outsideRange->subject)
            ->assertSee('conversation='.$conversation->uuid, false)
            ->assertSeeText('Customer')
            ->assertSeeText('Approval Required');
    }

    public function test_email_dashboard_actions_and_audit_export_are_uuid_keyed(): void
    {
        [$company, $admin] = $this->context();
        $conversation = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'email',
            'contact' => 'actions@example.test',
            'subject' => 'Action contract',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->post(route('admin.communication-center.email.action', [$conversation, 'escalate']))
            ->assertRedirect();
        $this->assertSame('urgent', $conversation->fresh()->priority);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->post(route('admin.communication-center.email.action', [$conversation, 'resolve']))
            ->assertRedirect();
        $this->assertSame('closed', $conversation->fresh()->status);

        $export = $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->get(route('admin.communication-center.email.audit.export', $conversation))
            ->assertOk();
        $this->assertStringContainsString('Audit UUID', $export->streamedContent());
        $this->assertStringContainsString($conversation->uuid, $export->streamedContent());
        $this->assertTrue(AuditLog::query()->where('subject_uuid', $conversation->uuid)->where('action', 'communication.updated')->exists());
    }

    public function test_email_api_is_tenant_scoped_uuid_keyed_and_supports_reply_idempotency(): void
    {
        [$company, $admin, $customer, $order] = $this->context();
        $conversation = Conversation::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'channel' => 'email',
            'contact' => $customer->email,
            'subject' => 'API contract',
            'status' => 'new',
            'priority' => 'high',
            'metadata' => ['uid' => 'API-EMAIL-142', 'name' => $customer->name],
        ]);
        $conversation->messages()->create([
            'direction' => 'inbound',
            'body' => 'Search this message body.',
            'delivery_status' => 'stored',
            'sent_at' => now(),
        ]);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.email.index', ['q' => 'Search this message body']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $conversation->uuid)
            ->assertJsonMissingPath('data.0.id');

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.email.show', ['conversation' => $conversation->uuid]))
            ->assertOk()
            ->assertJsonPath('data.uuid', $conversation->uuid)
            ->assertJsonPath('data.messages.0.body', 'Search this message body.')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.messages.0.id');

        Event::fake([CommunicationConversationChanged::class]);
        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->patchJson(route('api.v1.communication.email.update', ['conversation' => $conversation->uuid]), [
                'status' => 'pending',
                'priority' => 'urgent',
                'assigned_to' => $admin->id,
                'follow_up_at' => '2026-09-15 10:00:00',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonMissingPath('data.id');
        Event::assertDispatched(CommunicationConversationChanged::class, fn (CommunicationConversationChanged $event): bool => $event->conversation->uuid === $conversation->uuid && $event->action === 'updated');

        Queue::fake();
        $reply = $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->withHeader('Idempotency-Key', 'email-reply-142')
            ->postJson(route('api.v1.communication.email.messages.store', ['conversation' => $conversation->uuid]), [
                'body' => 'A durable API reply.',
            ])
            ->assertOk()
            ->assertJsonPath('data.conversation_uuid', $conversation->uuid)
            ->assertJsonPath('data.body', 'A durable API reply.')
            ->assertJsonMissingPath('data.id');
        $this->assertNotEmpty($reply->json('data.uuid'));

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->withHeader('Idempotency-Key', 'email-reply-142')
            ->postJson(route('api.v1.communication.email.messages.store', ['conversation' => $conversation->uuid]), [
                'body' => 'A durable API reply.',
            ])
            ->assertOk()
            ->assertJsonPath('data.uuid', $reply->json('data.uuid'));
        $this->assertSame(2, ConversationMessage::query()->count());
        Queue::assertPushed(DeliverCommunicationMessage::class);

        $otherCompany = Company::create(['name' => 'Other Company', 'code' => 'EMAIL-OTHER', 'active' => true]);
        $this->withSession(['company_id' => $otherCompany->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.email.show', ['conversation' => $conversation->uuid]))
            ->assertNotFound();
    }

    public function test_email_conversations_soft_delete_without_reappearing_in_the_api(): void
    {
        [$company, $admin] = $this->context();
        $conversation = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'email',
            'contact' => 'deleted@example.test',
            'subject' => 'Soft delete contract',
        ]);
        $conversation->delete();

        $this->assertSoftDeleted('conversations', ['id' => $conversation->id]);
        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.email.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function context(): array
    {
        $company = Company::create(['name' => 'Emerald Rozalia', 'code' => 'EMAIL-142', 'active' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Aoife Customer', 'email' => 'aoife@example.test']);
        $order = Order::create([
            'company_id' => $company->id,
            'user_id' => $customer->id,
            'number' => 'ER-ORDER-142',
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal' => 100,
            'shipping' => 0,
            'total' => 100,
            'currency' => 'EUR',
        ]);

        return [$company, $admin, $customer, $order];
    }
}
