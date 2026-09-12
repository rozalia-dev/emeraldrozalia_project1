<?php

namespace Tests\Feature;

use App\Jobs\DeliverCommunicationMessage;
use App\Models\CommunicationWebhookEvent;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\CommunicationCenter;
use App\Services\CommunicationProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_submission_stores_shared_conversation_contract_and_is_idempotent(): void
    {
        $company = Company::create([
            'name' => 'Emerald Contract Company',
            'code' => 'COMM-CONTRACT',
            'active' => true,
        ]);
        $payload = [
            'type' => 'contact',
            'name' => 'Aoife Contract',
            'email' => 'aoife-contract@example.test',
            'subject' => 'Communication contract',
            'message' => 'Please keep this enquiry correlated.',
            'consent' => '1',
        ];

        $this->withSession(['company_id' => $company->id])
            ->withHeader('Idempotency-Key', 'public-contract-001')
            ->post('/enquiry', $payload)
            ->assertRedirect();
        $this->withSession(['company_id' => $company->id])
            ->withHeader('Idempotency-Key', 'public-contract-001')
            ->post('/enquiry', $payload)
            ->assertRedirect();

        $conversation = Conversation::with('messages')->firstOrFail();
        $this->assertSame($company->id, $conversation->company_id);
        $this->assertSame('public-contract-001', $conversation->idempotency_key);
        $this->assertSame($conversation->inquiry?->correlation_id, $conversation->correlation_id);
        $this->assertNotNull($conversation->consent_captured_at);
        $this->assertSame('public-enquiry-v1', $conversation->consent_version);
        $this->assertCount(1, $conversation->messages);
        $this->assertSame('stored', $conversation->messages->first()->delivery_status);
        $this->assertSame(1, Conversation::count());
        $this->assertSame(1, ConversationMessage::count());
    }

    public function test_admin_reply_is_queued_and_key_reuse_is_safe(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $conversation = Conversation::create([
            'channel' => 'whatsapp',
            'contact' => '+353 87 000 0000',
            'subject' => 'Queued reply',
            'status' => 'new',
        ]);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'reply-contract-001')
            ->post(route('admin.communication.message.store', $conversation), ['body' => 'A queued reply.'])
            ->assertRedirect();
        $message = ConversationMessage::firstOrFail();
        $this->assertSame('queued', $message->delivery_status);
        $this->assertSame('open', $conversation->fresh()->status);
        Queue::assertPushed(DeliverCommunicationMessage::class, fn (DeliverCommunicationMessage $job): bool => $job->messageId === $message->id);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'reply-contract-001')
            ->post(route('admin.communication.message.store', $conversation), ['body' => 'A queued reply.'])
            ->assertRedirect();
        $this->assertSame(1, ConversationMessage::count());

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'reply-contract-001')
            ->post(route('admin.communication.message.store', $conversation), ['body' => 'A different reply.'])
            ->assertStatus(409);
        $this->assertSame(1, ConversationMessage::count());
    }

    public function test_missing_provider_never_reports_delivery_success(): void
    {
        config([
            'communication.channels.whatsapp' => null,
            'communication.channels.email' => null,
            'communication.channels.chat' => null,
        ]);
        $conversation = Conversation::create([
            'channel' => 'whatsapp',
            'contact' => '+353 87 000 0001',
            'subject' => 'Provider not configured',
            'status' => 'open',
        ]);
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => 'Waiting for adapter.',
            'delivery_status' => 'queued',
        ]);

        (new DeliverCommunicationMessage($message->id))->handle(
            app(CommunicationProviderRegistry::class),
            app(CommunicationCenter::class),
        );

        $this->assertSame('awaiting_provider', $message->fresh()->delivery_status);
        $this->assertSame(1, $message->fresh()->delivery_attempts);
        $this->assertNull($message->fresh()->delivered_at);
    }

    public function test_signed_webhook_updates_delivery_once_and_redacts_payload(): void
    {
        config(['communication.webhook_secrets.whatsapp' => 'test-secret']);
        $conversation = Conversation::create([
            'channel' => 'whatsapp',
            'contact' => '+353 87 000 0002',
            'subject' => 'Webhook delivery',
            'status' => 'open',
        ]);
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => 'Private reply body.',
            'delivery_status' => 'queued',
        ]);
        $payload = [
            'event_id' => 'provider-event-001',
            'event_type' => 'message.delivered',
            'message_uuid' => $message->uuid,
            'provider_message_id' => 'wamid-contract-001',
            'status' => 'delivered',
            'email' => 'private@example.test',
            'message' => ['body' => 'Private webhook body.'],
        ];
        $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawPayload, 'test-secret');
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_COMMUNICATION_SIGNATURE' => $signature,
        ];

        $this->call('POST', '/api/v1/communication/webhooks/whatsapp', [], [], [], $server, $rawPayload)
            ->assertOk()
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('delivery_status', 'delivered');
        $this->assertSame('delivered', $message->fresh()->delivery_status);
        $this->assertSame('wamid-contract-001', $message->fresh()->provider_message_id);
        $this->assertSame(1, CommunicationWebhookEvent::count());
        $event = CommunicationWebhookEvent::firstOrFail();
        $this->assertSame('[REDACTED]', data_get($event->payload, 'email'));
        $this->assertSame('[REDACTED]', data_get($event->payload, 'message'));

        $this->call('POST', '/api/v1/communication/webhooks/whatsapp', [], [], [], $server, $rawPayload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);
        $this->assertSame(1, CommunicationWebhookEvent::count());

        $badServer = array_replace($server, ['HTTP_X_COMMUNICATION_SIGNATURE' => 'bad-signature']);
        $this->call('POST', '/api/v1/communication/webhooks/whatsapp', [], [], [], $badServer, $rawPayload)
            ->assertStatus(401);
    }

    public function test_webhook_requires_a_configured_secret(): void
    {
        config(['communication.webhook_secrets.whatsapp' => null]);

        $this->call(
            'POST',
            '/api/v1/communication/webhooks/whatsapp',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_COMMUNICATION_SIGNATURE' => 'anything'],
            '{}',
        )->assertStatus(503);
    }
}
