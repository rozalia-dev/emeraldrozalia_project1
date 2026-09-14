<?php

namespace Tests\Feature;

use App\Contracts\CommunicationProvider;
use App\Jobs\DeliverCommunicationMessage;
use App\Models\CommunicationWebhookEvent;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\CommunicationCenter;
use App\Services\CommunicationProviderRegistry;
use App\Support\CommunicationSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;


final class CommunicationContractFakeProvider implements CommunicationProvider
{
    public static array $messageUuids = [];

    public function send(ConversationMessage $message): CommunicationSendResult
    {
        self::$messageUuids[] = (string) $message->uuid;

        return new CommunicationSendResult('accepted', 'provider-message-001');
    }
    public function test_configured_fake_provider_updates_delivery_state_without_fabricating_delivery(): void
    {
        config(['communication.channels.whatsapp' => CommunicationContractFakeProvider::class]);
        CommunicationContractFakeProvider::$messageUuids = [];

        $conversation = Conversation::create([
            'channel' => 'whatsapp',
            'contact' => '+353 87 000 0003',
            'subject' => 'Configured provider',
            'status' => 'open',
        ]);
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => 'Provider-backed reply.',
            'delivery_status' => 'queued',
            'idempotency_key' => 'provider-contract-001',
        ]);

        (new DeliverCommunicationMessage($message->id))->handle(
            app(CommunicationProviderRegistry::class),
            app(CommunicationCenter::class),
        );

        $message->refresh();
        $this->assertSame([$message->uuid], CommunicationContractFakeProvider::$messageUuids);
        $this->assertSame('queued', $message->delivery_status);
        $this->assertSame('provider-message-001', $message->provider_message_id);
        $this->assertSame(1, $message->delivery_attempts);
        $this->assertNull($message->delivered_at);
    }

    public function test_default_http_provider_sends_a_correlated_idempotent_payload(): void
    {
        config([
            'communication.channels.email' => null,
            'communication.endpoints.email' => 'https://email-provider.test/messages',
            'communication.tokens.email' => 'test-provider-token',
        ]);
        Http::fake([
            'https://email-provider.test/*' => Http::response([
                'status' => 'accepted',
                'id' => 'email-provider-001',
            ], 202),
        ]);

        $conversation = Conversation::create([
            'channel' => 'email',
            'contact' => 'customer@example.test',
            'subject' => 'Provider payload',
            'status' => 'open',
        ]);
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => 'A real adapter boundary.',
            'delivery_status' => 'queued',
            'idempotency_key' => 'provider-http-001',
            'payload' => ['correlation_id' => '11111111-2222-4333-8444-555555555555'],
        ]);

        $provider = app(CommunicationProviderRegistry::class)->for('email');
        $this->assertNotNull($provider);
        $result = $provider->send($message);

        $this->assertSame('accepted', $result->status);
        $this->assertSame('email-provider-001', $result->providerMessageId);
        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        /** @var ClientRequest $request */
        $request = $recorded[0][0];
        $this->assertSame('https://email-provider.test/messages', $request->url());
        $this->assertSame('email', $request->data()['channel']);
        $this->assertSame('customer@example.test', $request->data()['to']);
        $this->assertSame('provider-http-001', $request->data()['idempotency_key']);
        $this->assertSame('11111111-2222-4333-8444-555555555555', $request->data()['correlation_id']);
        $this->assertSame(['Bearer test-provider-token'], $request->header('Authorization'));
    }

    public function test_assignment_and_explicit_approval_users_are_company_scoped(): void
    {
        $company = \App\Models\Company::create(['name' => 'Selected Company', 'code' => 'COMM-SCOPE-1', 'active' => true]);
        $otherCompany = \App\Models\Company::create(['name' => 'Other Company', 'code' => 'COMM-SCOPE-2', 'active' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $foreignAdmin = User::factory()->create(['is_admin' => true]);
        $admin->companies()->attach($company->id, ['role' => 'owner', 'is_default' => true]);
        $foreignAdmin->companies()->attach($otherCompany->id, ['role' => 'owner', 'is_default' => true]);
        $conversation = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'email',
            'contact' => 'customer@example.test',
            'subject' => 'Company scoped assignment',
            'status' => 'open',
        ]);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->patch(route('admin.communication.update', $conversation), [
                'status' => 'open',
                'priority' => 'normal',
                'assigned_to' => $foreignAdmin->id,
            ])
            ->assertSessionHasErrors('assigned_to');

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->postJson(route('api.v1.communication.approvals.store'), [
                'title' => 'Foreign approver must be rejected',
                'request_type' => 'Operations',
                'approver_id' => $foreignAdmin->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('approver_id');
    }

}

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
    public function test_configured_fake_provider_updates_delivery_state_without_fabricating_delivery(): void
    {
        config(['communication.channels.whatsapp' => CommunicationContractFakeProvider::class]);
        CommunicationContractFakeProvider::$messageUuids = [];

        $conversation = Conversation::create([
            'channel' => 'whatsapp',
            'contact' => '+353 87 000 0003',
            'subject' => 'Configured provider',
            'status' => 'open',
        ]);
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => 'Provider-backed reply.',
            'delivery_status' => 'queued',
            'idempotency_key' => 'provider-contract-001',
        ]);

        (new DeliverCommunicationMessage($message->id))->handle(
            app(CommunicationProviderRegistry::class),
            app(CommunicationCenter::class),
        );

        $message->refresh();
        $this->assertSame([$message->uuid], CommunicationContractFakeProvider::$messageUuids);
        $this->assertSame('queued', $message->delivery_status);
        $this->assertSame('provider-message-001', $message->provider_message_id);
        $this->assertSame(1, $message->delivery_attempts);
        $this->assertNull($message->delivered_at);
    }

    public function test_default_http_provider_sends_a_correlated_idempotent_payload(): void
    {
        config([
            'communication.channels.email' => null,
            'communication.endpoints.email' => 'https://email-provider.test/messages',
            'communication.tokens.email' => 'test-provider-token',
        ]);
        Http::fake([
            'https://email-provider.test/*' => Http::response([
                'status' => 'accepted',
                'id' => 'email-provider-001',
            ], 202),
        ]);

        $conversation = Conversation::create([
            'channel' => 'email',
            'contact' => 'customer@example.test',
            'subject' => 'Provider payload',
            'status' => 'open',
        ]);
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => 'A real adapter boundary.',
            'delivery_status' => 'queued',
            'idempotency_key' => 'provider-http-001',
            'payload' => ['correlation_id' => '11111111-2222-4333-8444-555555555555'],
        ]);

        $provider = app(CommunicationProviderRegistry::class)->for('email');
        $this->assertNotNull($provider);
        $result = $provider->send($message);

        $this->assertSame('accepted', $result->status);
        $this->assertSame('email-provider-001', $result->providerMessageId);
        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        /** @var ClientRequest $request */
        $request = $recorded[0][0];
        $this->assertSame('https://email-provider.test/messages', $request->url());
        $this->assertSame('email', $request->data()['channel']);
        $this->assertSame('customer@example.test', $request->data()['to']);
        $this->assertSame('provider-http-001', $request->data()['idempotency_key']);
        $this->assertSame('11111111-2222-4333-8444-555555555555', $request->data()['correlation_id']);
        $this->assertSame(['Bearer test-provider-token'], $request->header('Authorization'));
    }

    public function test_assignment_and_explicit_approval_users_are_company_scoped(): void
    {
        $company = \App\Models\Company::create(['name' => 'Selected Company', 'code' => 'COMM-SCOPE-1', 'active' => true]);
        $otherCompany = \App\Models\Company::create(['name' => 'Other Company', 'code' => 'COMM-SCOPE-2', 'active' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $foreignAdmin = User::factory()->create(['is_admin' => true]);
        $admin->companies()->attach($company->id, ['role' => 'owner', 'is_default' => true]);
        $foreignAdmin->companies()->attach($otherCompany->id, ['role' => 'owner', 'is_default' => true]);
        $conversation = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'email',
            'contact' => 'customer@example.test',
            'subject' => 'Company scoped assignment',
            'status' => 'open',
        ]);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->patch(route('admin.communication.update', $conversation), [
                'status' => 'open',
                'priority' => 'normal',
                'assigned_to' => $foreignAdmin->id,
            ])
            ->assertSessionHasErrors('assigned_to');

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->postJson(route('api.v1.communication.approvals.store'), [
                'title' => 'Foreign approver must be rejected',
                'request_type' => 'Operations',
                'approver_id' => $foreignAdmin->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('approver_id');
    }

}
