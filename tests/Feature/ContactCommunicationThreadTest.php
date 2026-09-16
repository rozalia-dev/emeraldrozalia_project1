<?php

namespace Tests\Feature;

use App\Contracts\CommunicationProvider;
use App\Jobs\DeliverCommunicationMessage;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Inquiry;
use App\Services\CommunicationCenter;
use App\Services\CommunicationProviderRegistry;
use App\Support\CommunicationSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContactEmailDeliveryFakeProvider implements CommunicationProvider
{
    public static array $messageUuids = [];

    public function send(ConversationMessage $message): CommunicationSendResult
    {
        self::$messageUuids[] = (string) $message->uuid;

        return new CommunicationSendResult('accepted', 'contact-email-provider-001');
    }
}

class ContactCommunicationThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_contact_email_appends_to_active_thread_and_closed_thread_starts_new_conversation(): void
    {
        $company = Company::create([
            'name' => 'Contact Thread Company',
            'code' => 'CONTACT-THREAD',
            'active' => true,
        ]);

        $first = [
            'type' => 'contact',
            'name' => 'Aoife Customer',
            'email' => 'Aoife.Customer@Example.Test',
            'subject' => 'First question',
            'message' => 'My first contact message.',
            'consent' => '1',
        ];
        $second = array_replace($first, [
            'email' => 'aoife.customer@example.test',
            'subject' => 'Follow-up question',
            'message' => 'My second contact message.',
        ]);

        $this->withSession(['company_id' => $company->id])
            ->withHeader('Idempotency-Key', 'contact-thread-001')
            ->post('/enquiry', $first)
            ->assertRedirect();
        $this->withSession(['company_id' => $company->id])
            ->withHeader('Idempotency-Key', 'contact-thread-002')
            ->post('/enquiry', $second)
            ->assertRedirect();

        $this->assertSame(2, Inquiry::withoutGlobalScopes()->count());
        $this->assertSame(1, Conversation::withoutGlobalScopes()->count());

        $conversation = Conversation::withoutGlobalScopes()->with('messages')->firstOrFail();
        $this->assertSame('web', $conversation->channel);
        $this->assertSame('aoife.customer@example.test', $conversation->contact);
        $this->assertSame('new', $conversation->status);
        $this->assertSame('contact', data_get($conversation->metadata, 'type'));
        $this->assertSame('Follow-up question', $conversation->subject);
        $this->assertCount(2, $conversation->messages);
        $this->assertSame(
            ['My first contact message.', 'My second contact message.'],
            $conversation->messages->pluck('body')->all(),
        );

        $conversation->update(['status' => 'closed']);

        $this->withSession(['company_id' => $company->id])
            ->withHeader('Idempotency-Key', 'contact-thread-003')
            ->post('/enquiry', array_replace($second, [
                'subject' => 'New case after resolution',
                'message' => 'This should start a new resolved-case follow-up.',
            ]))
            ->assertRedirect();

        $this->assertSame(3, Inquiry::withoutGlobalScopes()->count());
        $this->assertSame(2, Conversation::withoutGlobalScopes()->count());
        $this->assertSame(
            1,
            Conversation::withoutGlobalScopes()
                ->where('status', 'new')
                ->where('subject', 'New case after resolution')
                ->count(),
        );
    }

    public function test_web_contact_reply_uses_email_provider_without_changing_conversation_origin(): void
    {
        config(['communication.channels.email' => ContactEmailDeliveryFakeProvider::class]);
        ContactEmailDeliveryFakeProvider::$messageUuids = [];

        $conversation = Conversation::create([
            'channel' => 'web',
            'contact' => 'customer@example.test',
            'subject' => 'Website contact enquiry',
            'status' => 'open',
            'metadata' => ['type' => 'contact'],
        ]);
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => 'This reply must be delivered by email.',
            'delivery_status' => 'queued',
        ]);

        (new DeliverCommunicationMessage($message->id))->handle(
            app(CommunicationProviderRegistry::class),
            app(CommunicationCenter::class),
        );

        $message->refresh();
        $this->assertSame([$message->uuid], ContactEmailDeliveryFakeProvider::$messageUuids);
        $this->assertSame('queued', $message->delivery_status);
        $this->assertSame('contact-email-provider-001', $message->provider_message_id);
        $this->assertSame('email', data_get($message->payload, 'delivery_channel'));
        $this->assertSame(1, $message->delivery_attempts);
        $this->assertSame('web', $conversation->fresh()->channel);
    }

    public function test_web_conversation_without_email_recipient_is_not_misrouted(): void
    {
        config(['communication.channels.email' => ContactEmailDeliveryFakeProvider::class]);
        ContactEmailDeliveryFakeProvider::$messageUuids = [];

        $conversation = Conversation::create([
            'channel' => 'web',
            'contact' => '+353870000000',
            'subject' => 'Non-email web contact',
            'status' => 'open',
        ]);
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => 'This must not be sent as email.',
            'delivery_status' => 'queued',
        ]);

        (new DeliverCommunicationMessage($message->id))->handle(
            app(CommunicationProviderRegistry::class),
            app(CommunicationCenter::class),
        );

        $message->refresh();
        $this->assertSame([], ContactEmailDeliveryFakeProvider::$messageUuids);
        $this->assertSame('awaiting_provider', $message->delivery_status);
        $this->assertSame('channel_not_supported', $message->failure_code);
    }
}
