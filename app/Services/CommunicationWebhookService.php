<?php

namespace App\Services;

use App\Models\CommunicationWebhookEvent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

class CommunicationWebhookService
{
    private const PROVIDERS = ['email', 'whatsapp', 'chat'];

    private const DELIVERY_STATUSES = [
        'stored',
        'queued',
        'sending',
        'delivered',
        'failed',
        'awaiting_provider',
        'retrying',
    ];

    private const REDACTED_KEYS = [
        'address',
        'authorization',
        'body',
        'content',
        'email',
        'from',
        'message',
        'password',
        'phone',
        'recipient',
        'secret',
        'sender',
        'text',
        'token',
        'to',
    ];

    public function handle(string $provider, string $rawPayload, string $signature): array
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, self::PROVIDERS, true)) {
            abort(422, 'Unsupported communication provider.');
        }

        $secret = config('communication.webhook_secrets.'.$provider);
        if (! is_string($secret) || trim($secret) === '') {
            abort(503, 'The communication provider webhook is not configured.');
        }

        $signature = trim($signature);
        if (str_starts_with(strtolower($signature), 'sha256=')) {
            $signature = substr($signature, 7);
        }
        $expectedSignature = hash_hmac('sha256', $rawPayload, $secret);
        if ($signature === '' || ! hash_equals($expectedSignature, $signature)) {
            abort(401, 'Invalid communication webhook signature.');
        }

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(422, 'The communication webhook payload is invalid JSON.');
        }

        if (! is_array($payload)) {
            abort(422, 'The communication webhook payload must be an object.');
        }

        $externalEventId = $this->stringValue(
            $payload['event_id'] ?? $payload['id'] ?? data_get($payload, 'event.id'),
        );
        if ($externalEventId === null) {
            abort(422, 'The communication webhook event id is required.');
        }

        $eventType = $this->stringValue(
            $payload['event_type'] ?? $payload['type'] ?? data_get($payload, 'event.type'),
        );
        $messageUuid = $this->uuidValue(
            $payload['message_uuid']
                ?? data_get($payload, 'data.message_uuid')
                ?? data_get($payload, 'message.uuid')
                ?? data_get($payload, 'data.message.uuid'),
        );
        $providerMessageId = $this->stringValue(
            $payload['provider_message_id']
                ?? $payload['message_id']
                ?? data_get($payload, 'data.provider_message_id')
                ?? data_get($payload, 'data.message_id')
                ?? data_get($payload, 'message.id')
                ?? data_get($payload, 'data.message.id'),
        );
        $deliveryStatus = $this->deliveryStatus(
            $payload['delivery_status']
                ?? $payload['status']
                ?? data_get($payload, 'data.delivery_status')
                ?? data_get($payload, 'data.status'),
        );
        $redactedPayload = $this->redact($payload);

        return DB::transaction(function () use (
            $provider,
            $externalEventId,
            $eventType,
            $messageUuid,
            $providerMessageId,
            $deliveryStatus,
            $redactedPayload,
            $expectedSignature,
        ): array {
            $event = CommunicationWebhookEvent::withoutGlobalScopes()
                ->where('provider', $provider)
                ->where('external_event_id', $externalEventId)
                ->lockForUpdate()
                ->first();

            if ($event) {
                return [
                    'accepted' => true,
                    'duplicate' => true,
                    'status' => $event->status,
                    'event_uuid' => $event->uuid,
                    'message_uuid' => $event->message_uuid,
                ];
            }

            $message = null;
            if ($messageUuid !== null) {
                $message = ConversationMessage::query()
                    ->where('uuid', $messageUuid)
                    ->lockForUpdate()
                    ->first();
            }
            if (! $message && $providerMessageId !== null) {
                $message = ConversationMessage::query()
                    ->where('provider_message_id', $providerMessageId)
                    ->lockForUpdate()
                    ->first();
            }

            $conversation = $message
                ? Conversation::withoutGlobalScopes()->find($message->conversation_id)
                : null;
            $event = CommunicationWebhookEvent::withoutGlobalScopes()->create([
                'company_id' => $conversation?->company_id,
                'provider' => $provider,
                'external_event_id' => $externalEventId,
                'event_type' => $eventType,
                'message_uuid' => $message?->uuid ?? $messageUuid,
                'signature_digest' => $expectedSignature,
                'payload' => $redactedPayload,
                'status' => 'received',
                'attempts' => 1,
            ]);

            if (! $message) {
                $event->update([
                    'status' => 'ignored',
                    'failure_reason' => 'Message was not found for this provider event.',
                    'processed_at' => now(),
                ]);
                AuditTrail::record('communication.webhook.ignored', $event, null, $this->eventState($event->fresh()));

                return [
                    'accepted' => true,
                    'duplicate' => false,
                    'status' => 'ignored',
                    'event_uuid' => $event->uuid,
                    'message_uuid' => $event->message_uuid,
                ];
            }

            if ($deliveryStatus === null) {
                $event->update([
                    'status' => 'ignored',
                    'failure_reason' => 'The provider event did not contain a supported delivery status.',
                    'processed_at' => now(),
                ]);
                AuditTrail::record('communication.webhook.ignored', $event, null, $this->eventState($event->fresh()));

                return [
                    'accepted' => true,
                    'duplicate' => false,
                    'status' => 'ignored',
                    'event_uuid' => $event->uuid,
                    'message_uuid' => $message->uuid,
                ];
            }

            $currentStatus = (string) $message->delivery_status;
            $shouldApply = ! ($currentStatus === 'delivered' && $deliveryStatus !== 'delivered')
                && ! ($currentStatus === 'failed' && in_array($deliveryStatus, ['queued', 'sending', 'retrying'], true));

            if ($shouldApply) {
                $updates = [
                    'delivery_status' => $deliveryStatus,
                    'provider_message_id' => $providerMessageId ?? $message->provider_message_id,
                ];
                if ($deliveryStatus === 'delivered') {
                    $updates['delivered_at'] = $message->delivered_at ?: now();
                    $updates['failed_at'] = null;
                    $updates['failure_code'] = null;
                    $updates['failure_message'] = null;
                } elseif ($deliveryStatus === 'failed') {
                    $updates['failed_at'] = now();
                    $updates['failure_code'] = 'provider_reported_failure';
                    $updates['failure_message'] = 'The communication provider reported a delivery failure.';
                }
                $message->update($updates);
            }

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'failure_reason' => null,
            ]);
            AuditTrail::record('communication.webhook.processed', $event->fresh(), null, $this->eventState($event->fresh()));

            return [
                'accepted' => true,
                'duplicate' => false,
                'status' => 'processed',
                'event_uuid' => $event->uuid,
                'message_uuid' => $message->uuid,
                'delivery_status' => $message->fresh()->delivery_status,
            ];
        });
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 180, '');
    }

    private function deliveryStatus(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = strtolower(trim((string) $value));
        $value = match ($value) {
            'accepted', 'accept', 'pending', 'sent' => 'queued',
            'delivered', 'delivery_delivered', 'success' => 'delivered',
            'failed', 'failure', 'undelivered', 'delivery_failed' => 'failed',
            default => $value,
        };

        return in_array($value, self::DELIVERY_STATUSES, true) ? $value : null;
    }

    private function uuidValue(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value !== null && Str::isUuid($value) ? $value : null;
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
            return '[REDACTED]';
        }

        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $childKey => $childValue) {
            $redacted[$childKey] = $this->redact($childValue, is_string($childKey) ? $childKey : null);
        }

        return $redacted;
    }

    private function eventState(CommunicationWebhookEvent $event): array
    {
        return [
            'uuid' => (string) $event->uuid,
            'provider' => $event->provider,
            'external_event_id' => $event->external_event_id,
            'event_type' => $event->event_type,
            'message_uuid' => $event->message_uuid,
            'status' => $event->status,
            'attempts' => (int) $event->attempts,
            'processed_at' => optional($event->processed_at)->toISOString(),
        ];
    }
}
