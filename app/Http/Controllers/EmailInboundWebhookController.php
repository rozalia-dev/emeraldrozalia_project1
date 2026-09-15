<?php

namespace App\Http\Controllers;

use App\Models\CommunicationWebhookEvent;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

class EmailInboundWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = trim((string) config('communication.webhook_secrets.email'));
        abort_if($secret === '', 503, 'The inbound email webhook is not configured.');

        $raw = $request->getContent();
        $signature = trim((string) $request->header('X-Communication-Signature', ''));
        if (str_starts_with(strtolower($signature), 'sha256=')) {
            $signature = substr($signature, 7);
        }
        $expected = hash_hmac('sha256', $raw, $secret);
        abort_if($signature === '' || ! hash_equals($expected, $signature), 401, 'Invalid communication webhook signature.');

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(422, 'The inbound email payload is invalid JSON.');
        }
        abort_unless(is_array($payload), 422, 'The inbound email payload must be an object.');

        $eventId = $this->scalar($payload['event_id'] ?? $payload['id'] ?? data_get($payload, 'event.id'));
        $from = strtolower($this->scalar($payload['from'] ?? data_get($payload, 'data.from') ?? data_get($payload, 'message.from')));
        $to = strtolower($this->scalar($payload['to'] ?? data_get($payload, 'data.to') ?? data_get($payload, 'message.to')));
        $subject = $this->scalar($payload['subject'] ?? data_get($payload, 'data.subject') ?? data_get($payload, 'message.subject'));
        $body = $this->scalar($payload['body'] ?? $payload['text'] ?? data_get($payload, 'data.body') ?? data_get($payload, 'message.body'), 100000);
        $providerMessageId = $this->scalar($payload['provider_message_id'] ?? $payload['message_id'] ?? data_get($payload, 'message.id'));

        abort_if($eventId === '', 422, 'event_id is required.');
        abort_unless(filter_var($from, FILTER_VALIDATE_EMAIL), 422, 'A valid from address is required.');
        abort_if($body === '', 422, 'Email body is required.');

        $result = DB::transaction(function () use ($eventId, $from, $to, $subject, $body, $providerMessageId, $expected): array {
            $existing = CommunicationWebhookEvent::withoutGlobalScopes()
                ->where('provider', 'email')
                ->where('external_event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['accepted' => true, 'duplicate' => true, 'event_uuid' => $existing->uuid, 'message_uuid' => $existing->message_uuid];
            }

            $conversation = Conversation::withoutGlobalScopes()->create([
                'channel' => 'email',
                'contact' => $from,
                'subject' => $subject !== '' ? $subject : '(no subject)',
                'status' => 'open',
                'priority' => 'normal',
                'metadata' => [
                    'source' => 'email_inbound_webhook',
                    'recipient' => $to !== '' ? $to : null,
                ],
            ]);

            $message = $conversation->messages()->create([
                'direction' => 'inbound',
                'body' => $body,
                'delivery_status' => 'stored',
                'provider_message_id' => $providerMessageId !== '' ? Str::limit($providerMessageId, 180, '') : null,
                'payload' => ['source' => 'email_inbound_webhook'],
                'sent_at' => now(),
            ]);

            $event = CommunicationWebhookEvent::withoutGlobalScopes()->create([
                'company_id' => $conversation->company_id,
                'provider' => 'email',
                'external_event_id' => $eventId,
                'event_type' => 'inbound',
                'message_uuid' => $message->uuid,
                'signature_digest' => $expected,
                'payload' => ['inbound' => true, 'content_redacted' => true],
                'status' => 'processed',
                'attempts' => 1,
                'processed_at' => now(),
            ]);

            return ['accepted' => true, 'duplicate' => false, 'event_uuid' => $event->uuid, 'message_uuid' => $message->uuid, 'conversation_uuid' => $conversation->uuid];
        });

        return response()->json($result, 202);
    }

    private function scalar(mixed $value, int $limit = 1000): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return Str::limit(trim((string) $value), $limit, '');
    }
}
