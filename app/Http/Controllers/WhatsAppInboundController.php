<?php

namespace App\Http\Controllers;

use App\Events\CommunicationConversationChanged;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

class WhatsAppInboundController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $secret = trim((string) config('communication.webhook_secrets.whatsapp'));
        abort_if($secret === '', 503, 'The WhatsApp webhook secret is not configured.');

        $signature = trim((string) $request->header('X-Communication-Signature', ''));
        if (str_starts_with(strtolower($signature), 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        abort_if($signature === '' || ! hash_equals($expected, $signature), 401, 'Invalid WhatsApp webhook signature.');

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(422, 'The WhatsApp webhook payload is invalid JSON.');
        }
        abort_unless(is_array($payload), 422, 'The WhatsApp webhook payload must be an object.');
        abort_unless(($payload['event_type'] ?? null) === 'message.received', 422, 'Unsupported WhatsApp event type.');

        $providerMessageId = $this->scalar($payload['provider_message_id'] ?? null, 180);
        $phone = $this->phone($payload['from_phone'] ?? null);
        abort_if($providerMessageId === null, 422, 'The WhatsApp provider message id is required.');
        abort_if($phone === null, 422, 'A valid WhatsApp sender phone number is required.');

        $senderName = $this->scalar($payload['sender_name'] ?? null, 180);
        $chatId = $this->scalar($payload['from_chat_id'] ?? null, 180);
        $messageType = $this->scalar($payload['message_type'] ?? 'chat', 50) ?? 'chat';
        $hasMedia = (bool) ($payload['has_media'] ?? false);
        $body = trim((string) ($payload['body'] ?? ''));
        if ($body === '') {
            $body = $hasMedia ? '[WhatsApp media message]' : '[Empty WhatsApp message]';
        }

        $timestamp = filter_var($payload['timestamp'] ?? null, FILTER_VALIDATE_INT);
        $sentAt = $timestamp && (int) $timestamp > 0
            ? now()->setTimestamp((int) $timestamp)
            : now();
        $companyId = (int) config('communication.whatsapp_company_id', 0);
        $correlationId = (string) Str::uuid();
        $duplicate = false;
        $conversation = null;
        $message = null;

        DB::transaction(function () use (
            &$duplicate,
            &$conversation,
            &$message,
            $providerMessageId,
            $phone,
            $senderName,
            $chatId,
            $messageType,
            $hasMedia,
            $body,
            $sentAt,
            $companyId,
            $correlationId,
            $payload,
        ): void {
            $existing = ConversationMessage::withoutGlobalScopes()
                ->where('provider_message_id', $providerMessageId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $duplicate = true;
                $message = $existing;
                $conversation = Conversation::withoutGlobalScopes()->find($existing->conversation_id);
                return;
            }

            $conversationQuery = Conversation::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('channel', 'whatsapp')
                ->where('contact', $phone);

            $companyId > 0
                ? $conversationQuery->where('company_id', $companyId)
                : $conversationQuery->whereNull('company_id');

            $conversation = $conversationQuery->lockForUpdate()->first();
            $metadata = [
                'name' => $senderName ?: $phone,
                'whatsapp_chat_id' => $chatId,
                'whatsapp_last_message_type' => $messageType,
                'whatsapp_last_received_at' => $sentAt->toISOString(),
                'source' => 'whatsapp_web_engine',
            ];

            if (! $conversation) {
                $conversation = Conversation::withoutGlobalScopes()->create([
                    'company_id' => $companyId > 0 ? $companyId : null,
                    'channel' => 'whatsapp',
                    'contact' => $phone,
                    'subject' => 'WhatsApp · '.($senderName ?: $phone),
                    'priority' => 'normal',
                    'status' => 'new',
                    'correlation_id' => $correlationId,
                    'metadata' => $metadata,
                ]);
            } else {
                $conversation->update([
                    'status' => in_array($conversation->status, ['closed', 'resolved'], true) ? 'open' : $conversation->status,
                    'correlation_id' => $conversation->correlation_id ?: $correlationId,
                    'metadata' => array_filter(array_merge((array) $conversation->metadata, $metadata), static fn ($value) => $value !== null),
                ]);
            }

            $message = ConversationMessage::withoutGlobalScopes()->create([
                'company_id' => $conversation->company_id,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => $body,
                'delivery_status' => 'delivered',
                'provider_message_id' => $providerMessageId,
                'sent_at' => $sentAt,
                'delivered_at' => now(),
                'payload' => [
                    'source' => 'whatsapp_web_engine',
                    'event_id' => $this->scalar($payload['event_id'] ?? null, 180),
                    'chat_id' => $chatId,
                    'message_type' => $messageType,
                    'has_media' => $hasMedia,
                    'correlation_id' => $correlationId,
                ],
            ]);

            AuditTrail::record('communication.whatsapp.inbound', $message, null, [
                'message_uuid' => $message->uuid,
                'conversation_uuid' => $conversation->uuid,
                'provider_message_id' => $providerMessageId,
                'direction' => 'inbound',
                'delivery_status' => 'delivered',
            ]);
        });

        if ($conversation && ! $duplicate) {
            event(new CommunicationConversationChanged(
                $conversation->fresh(),
                'whatsapp_inbound',
                (string) ($conversation->correlation_id ?: $correlationId),
            ));
        }

        return response()->json([
            'accepted' => true,
            'duplicate' => $duplicate,
            'conversation_uuid' => $conversation?->uuid,
            'message_uuid' => $message?->uuid,
        ]);
    }

    private function scalar(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : Str::limit($value, $max, '');
    }

    private function phone(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $value);
        return is_string($digits) && preg_match('/^\d{7,15}$/', $digits) ? $digits : null;
    }
}
