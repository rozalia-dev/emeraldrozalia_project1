<?php

namespace App\Http\Controllers;

use App\Events\CommunicationConversationChanged;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\AuditTrail;
use App\Services\Chat24SevenAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PublicChatController extends Controller
{
    public function start(Request $request, Chat24SevenAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'context_product_slug' => ['nullable', 'string', 'max:180'],
        ]);

        $companyId = (int) Company::query()->where('active', true)->orderBy('id')->value('id');
        $visitorId = (string) Str::uuid();
        $conversation = Conversation::withoutGlobalScopes()->create([
            'company_id' => $companyId > 0 ? $companyId : null,
            'channel' => 'chat',
            'contact' => filled($data['email'] ?? null) ? Str::lower($data['email']) : 'Website visitor '.$visitorId,
            'subject' => 'Website Chat 24/7',
            'status' => 'open',
            'priority' => 'normal',
            'customer_id' => auth()->id(),
            'metadata' => [
                'source' => 'website_chat_24_7',
                'visitor_id' => $visitorId,
                'visitor_name' => trim((string) ($data['name'] ?? '')) ?: null,
                'context_product_slug' => trim((string) ($data['context_product_slug'] ?? '')) ?: null,
                'ai_mode' => 'grounded',
                'ai_paused' => false,
                'human_requested' => false,
            ],
        ]);

        $this->ownConversation($request, $conversation);
        $reply = $assistant->greeting();
        $message = $this->storeAssistantMessage($conversation, $reply);

        AuditTrail::record('communication.chat.started', $conversation, null, [
            'uuid' => $conversation->uuid,
            'channel' => 'chat',
            'source' => 'website_chat_24_7',
        ]);
        $this->dispatchChanged($conversation, 'started');

        return response()->json([
            'ok' => true,
            'conversation_uuid' => $conversation->uuid,
            'messages' => [$this->messagePayload($message)],
            'quick_actions' => Chat24SevenAssistant::QUICK_ACTIONS,
        ], 201);
    }

    public function send(Request $request, string $conversation, Chat24SevenAssistant $assistant): JsonResponse
    {
        $chat = $this->ownedConversation($request, $conversation);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2500'],
            'context_product_slug' => ['nullable', 'string', 'max:180'],
        ]);

        $inbound = $chat->messages()->create([
            'company_id' => $chat->company_id,
            'direction' => 'inbound',
            'body' => trim($data['message']),
            'delivery_status' => 'delivered',
            'payload' => [
                'source' => 'website_chat_24_7',
                'visitor_id' => data_get($chat->metadata, 'visitor_id'),
            ],
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $metadata = (array) $chat->metadata;
        $metadata['context_product_slug'] = trim((string) ($data['context_product_slug'] ?? data_get($metadata, 'context_product_slug', ''))) ?: null;
        $chat->update(['metadata' => $metadata, 'status' => $chat->status === 'closed' ? 'open' : $chat->status]);

        AuditTrail::record('communication.chat.customer_message', $inbound, null, [
            'conversation_uuid' => $chat->uuid,
            'message_uuid' => $inbound->uuid,
        ]);

        $messages = [$this->messagePayload($inbound)];
        $aiPaused = (bool) data_get($metadata, 'ai_paused', false)
            || (bool) data_get($metadata, 'human_requested', false)
            || $chat->assigned_to !== null;

        if (! $aiPaused) {
            $reply = $assistant->answer($data['message'], $metadata['context_product_slug'] ?? null);
            $assistantMessage = $this->storeAssistantMessage($chat, $reply);
            $messages[] = $this->messagePayload($assistantMessage);

            if ($reply['requires_human']) {
                $metadata = (array) $chat->fresh()->metadata;
                $metadata['human_requested'] = true;
                $metadata['human_reason'] = $reply['intent'];
                $metadata['human_requested_at'] = now()->toIso8601String();
                $chat->update(['metadata' => $metadata, 'status' => 'pending', 'priority' => 'high']);
            }
        }

        $this->dispatchChanged($chat->fresh(), 'message');

        return response()->json([
            'ok' => true,
            'messages' => $messages,
            'human_requested' => (bool) data_get($chat->fresh()->metadata, 'human_requested', false),
            'ai_paused' => $aiPaused,
            'quick_actions' => Chat24SevenAssistant::QUICK_ACTIONS,
        ]);
    }

    public function messages(Request $request, string $conversation): JsonResponse
    {
        $chat = $this->ownedConversation($request, $conversation);
        $afterId = max(0, (int) $request->query('after_id', 0));

        $messages = $chat->messages()
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (ConversationMessage $message) => $this->messagePayload($message))
            ->values();

        return response()->json([
            'ok' => true,
            'messages' => $messages,
            'human_requested' => (bool) data_get($chat->metadata, 'human_requested', false),
            'ai_paused' => (bool) data_get($chat->metadata, 'ai_paused', false) || $chat->assigned_to !== null,
        ]);
    }

    public function human(Request $request, string $conversation): JsonResponse
    {
        $chat = $this->ownedConversation($request, $conversation);
        $metadata = (array) $chat->metadata;
        $metadata['human_requested'] = true;
        $metadata['human_reason'] = 'customer_requested';
        $metadata['human_requested_at'] = now()->toIso8601String();
        $chat->update(['metadata' => $metadata, 'status' => 'pending', 'priority' => 'high']);

        $message = $this->storeAssistantMessage($chat, [
            'body' => 'I’ve asked an Emerald Rozalia team member to join this conversation. You can keep this chat open and their reply will appear here.',
            'intent' => 'human',
            'requires_human' => true,
            'products' => [],
            'quick_actions' => [],
        ]);

        $this->dispatchChanged($chat->fresh(), 'human_requested');

        return response()->json(['ok' => true, 'message' => $this->messagePayload($message)]);
    }

    private function storeAssistantMessage(Conversation $conversation, array $reply): ConversationMessage
    {
        return $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'direction' => 'outbound',
            'body' => (string) $reply['body'],
            'delivery_status' => 'delivered',
            'payload' => [
                'source' => 'chat_24_7_ai',
                'actor' => 'ai_assistant',
                'intent' => $reply['intent'],
                'requires_human' => (bool) $reply['requires_human'],
                'products' => $reply['products'] ?? [],
            ],
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);
    }

    private function ownedConversation(Request $request, string $uuid): Conversation
    {
        $owned = (array) $request->session()->get('chat24_conversations', []);
        abort_unless(in_array($uuid, $owned, true), 404);

        return Conversation::withoutGlobalScopes()
            ->where('uuid', $uuid)
            ->where('channel', 'chat')
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    private function ownConversation(Request $request, Conversation $conversation): void
    {
        $owned = (array) $request->session()->get('chat24_conversations', []);
        $owned[] = $conversation->uuid;
        $request->session()->put('chat24_conversations', array_values(array_unique(array_slice($owned, -10))));
    }

    private function messagePayload(ConversationMessage $message): array
    {
        $payload = (array) $message->payload;

        return [
            'id' => (int) $message->id,
            'uuid' => (string) $message->uuid,
            'direction' => (string) $message->direction,
            'body' => (string) $message->body,
            'actor' => data_get($payload, 'actor', $message->direction === 'outbound' ? 'team' : 'customer'),
            'products' => data_get($payload, 'products', []),
            'sent_at' => optional($message->sent_at ?: $message->created_at)->toIso8601String(),
        ];
    }

    private function dispatchChanged(Conversation $conversation, string $action): void
    {
        event(new CommunicationConversationChanged(
            $conversation,
            $action,
            (string) ($conversation->correlation_id ?: Str::uuid()),
        ));
    }
}
