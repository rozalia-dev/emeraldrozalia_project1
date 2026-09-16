<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Communication\WhatsAppHttpCommunicationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_whatsapp_requires_valid_signature(): void
    {
        config(['communication.webhook_secrets.whatsapp' => 'test-secret']);

        $this->postJson('/api/v1/communication/whatsapp/inbound', [
            'event_type' => 'message.received',
            'provider_message_id' => 'wa-1',
            'from_phone' => '353871234567',
            'body' => 'Hello',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_signed_inbound_whatsapp_creates_one_conversation_and_deduplicates_message(): void
    {
        config([
            'communication.webhook_secrets.whatsapp' => 'test-secret',
            'communication.whatsapp_company_id' => 0,
        ]);

        $payload = [
            'event_id' => 'wa-in:message-123',
            'event_type' => 'message.received',
            'provider_message_id' => 'message-123',
            'from_chat_id' => '353871234567@c.us',
            'from_phone' => '353871234567',
            'sender_name' => 'Aoife Customer',
            'body' => 'Can I get a bulk quote?',
            'message_type' => 'chat',
            'has_media' => false,
            'timestamp' => now()->timestamp,
        ];

        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $raw, 'test-secret');

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_COMMUNICATION_SIGNATURE' => $signature,
        ];

        $this->call('POST', '/api/v1/communication/whatsapp/inbound', [], [], [], $server, $raw)
            ->assertOk()
            ->assertJson(['accepted' => true, 'duplicate' => false]);

        $conversation = Conversation::withoutGlobalScopes()->where('channel', 'whatsapp')->firstOrFail();
        $this->assertSame('353871234567', $conversation->contact);
        $this->assertSame('Aoife Customer', data_get($conversation->metadata, 'name'));

        $message = ConversationMessage::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('message-123', $message->provider_message_id);
        $this->assertSame('inbound', $message->direction);
        $this->assertSame('Can I get a bulk quote?', $message->body);

        $this->call('POST', '/api/v1/communication/whatsapp/inbound', [], [], [], $server, $raw)
            ->assertOk()
            ->assertJson(['accepted' => true, 'duplicate' => true]);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_whatsapp_http_provider_sends_existing_communication_payload_to_node_engine(): void
    {
        config([
            'communication.endpoints.whatsapp' => 'http://127.0.0.1:3001/send-message',
            'communication.tokens.whatsapp' => 'engine-token',
        ]);

        Http::fake([
            'http://127.0.0.1:3001/send-message' => Http::response([
                'ok' => true,
                'status' => 'accepted',
                'provider_message_id' => 'provider-123',
            ]),
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'channel' => 'whatsapp',
            'contact' => '353871234567',
            'subject' => 'WhatsApp test',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => 'Hello from Project 1',
            'delivery_status' => 'queued',
            'idempotency_key' => 'wa-test-key',
            'payload' => ['correlation_id' => $messageCorrelation = (string) \Illuminate\Support\Str::uuid()],
            'sent_at' => now(),
        ]);

        $result = app(WhatsAppHttpCommunicationProvider::class)->send($message->fresh(['conversation']));

        $this->assertSame('accepted', $result->status);
        $this->assertSame('provider-123', $result->providerMessageId);

        Http::assertSent(function ($request) use ($conversation, $message, $messageCorrelation): bool {
            return $request->url() === 'http://127.0.0.1:3001/send-message'
                && $request->hasHeader('Authorization', 'Bearer engine-token')
                && $request['channel'] === 'whatsapp'
                && $request['message_uuid'] === $message->uuid
                && $request['conversation_uuid'] === $conversation->uuid
                && $request['to'] === '353871234567'
                && $request['body'] === 'Hello from Project 1'
                && $request['correlation_id'] === $messageCorrelation
                && $request['idempotency_key'] === 'wa-test-key';
        });
    }

    public function test_admin_can_open_whatsapp_setup_and_proxy_engine_status(): void
    {
        config([
            'communication.whatsapp_engine_url' => 'http://127.0.0.1:3001',
            'communication.tokens.whatsapp' => 'engine-token',
            'communication.webhook_secrets.whatsapp' => 'test-secret',
        ]);

        Http::fake([
            'http://127.0.0.1:3001/status' => Http::response([
                'ok' => true,
                'ready' => true,
                'state' => 'ready',
                'whatsapp_state' => 'CONNECTED',
            ]),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.communication-center.whatsapp.setup'))
            ->assertOk()
            ->assertSee(['WhatsApp Web Engine', 'Start a Conversation', 'Open WhatsApp Inbox'], false);

        $this->actingAs($admin)
            ->getJson(route('admin.communication-center.whatsapp.status'))
            ->assertOk()
            ->assertJson(['ready' => true, 'state' => 'ready']);
    }

    public function test_admin_can_start_a_whatsapp_conversation_and_queue_the_first_message(): void
    {
        config([
            'communication.whatsapp_engine_url' => 'http://127.0.0.1:3001',
            'communication.endpoints.whatsapp' => 'http://127.0.0.1:3001/send-message',
            'communication.tokens.whatsapp' => 'engine-token',
            'communication.webhook_secrets.whatsapp' => 'test-secret',
        ]);

        Http::fake([
            'http://127.0.0.1:3001/status' => Http::response([
                'ok' => true,
                'ready' => true,
                'state' => 'ready',
            ]),
            'http://127.0.0.1:3001/send-message' => Http::response([
                'ok' => true,
                'status' => 'accepted',
                'provider_message_id' => 'provider-first-message',
            ]),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'wa-compose:test-first-message')
            ->postJson(route('admin.communication-center.whatsapp.send'), [
                'phone' => '+353 87 123 4567',
                'message' => 'Hello from the Emerald Rozalia Communication Center',
            ])
            ->assertStatus(202)
            ->assertJson(['queued' => true]);

        $conversation = Conversation::withoutGlobalScopes()->where('channel', 'whatsapp')->firstOrFail();
        $this->assertSame('353871234567', $conversation->contact);
        $this->assertSame('open', $conversation->status);

        $message = ConversationMessage::withoutGlobalScopes()->where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame('outbound', $message->direction);
        $this->assertSame('Hello from the Emerald Rozalia Communication Center', $message->body);
        $this->assertSame('wa-compose:test-first-message', $message->idempotency_key);
        $this->assertSame('provider-first-message', $message->provider_message_id);
        $this->assertSame('queued', $message->delivery_status);
    }
}
