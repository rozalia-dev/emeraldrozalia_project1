<?php

namespace App\Services;

use App\Jobs\DeliverCommunicationMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunicationCenter
{
    private const IDEMPOTENCY_PATTERN = '/\A[A-Za-z0-9._:-]{1,100}\z/D';

    /**
     * Persist one outbound reply and enqueue delivery after the transaction.
     * A repeated key is safe only when it describes the same conversation/body.
     */
    public function sendReply(Conversation $conversation, string $body, ?string $idempotencyKey = null): ConversationMessage
    {
        if (trim($body) === '') {
            throw ValidationException::withMessages(['body' => 'A reply body is required.']);
        }

        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);
        $message = null;

        DB::transaction(function () use (&$message, $conversation, $body, $idempotencyKey): void {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->find($conversation->getKey());

            if (! $lockedConversation) {
                throw (new ModelNotFoundException())->setModel(Conversation::class, [$conversation->getKey()]);
            }

            $requestHash = $this->replyHash($lockedConversation, $body);
            if ($idempotencyKey !== null) {
                $existing = ConversationMessage::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existingHash = (string) data_get($existing->payload, 'request_hash');
                    if (! hash_equals($existingHash, $requestHash)) {
                        abort(409, 'The Idempotency-Key was already used for a different reply.');
                    }

                    $message = $existing;
                    return;
                }
            }

            $correlationId = $this->correlationId();
            $message = $lockedConversation->messages()->create([
                'user_id' => auth()->id(),
                'direction' => 'outbound',
                'body' => $body,
                'delivery_status' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'payload' => [
                    'source' => 'communication_center_reply',
                    'correlation_id' => $correlationId,
                    'request_hash' => $requestHash,
                ],
                'sent_at' => now(),
            ]);
            $lockedConversation->update(['status' => 'open']);

            \App\Services\AuditTrail::record(
                'communication.message.created',
                $message,
                null,
                $this->messageState($message),
            );

            DeliverCommunicationMessage::dispatch($message->getKey())->afterCommit();
        });

        return $message->fresh();
    }

    /**
     * Apply the cPanel conversation transition under a row lock.
     * Only fields exposed by the admin form are accepted here.
     */
    public function updateConversation(Conversation $conversation, array $data): Conversation
    {
        $updated = null;

        DB::transaction(function () use (&$updated, $conversation, $data): void {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->find($conversation->getKey());

            if (! $lockedConversation) {
                throw (new ModelNotFoundException())->setModel(Conversation::class, [$conversation->getKey()]);
            }

            $before = $this->conversationState($lockedConversation);
            $attributes = [];
            foreach (['status', 'priority', 'assigned_to'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }
            if (array_key_exists('follow_up_at', $data)) {
                $attributes['follow_up_at'] = filled($data['follow_up_at'])
                    ? Carbon::parse($data['follow_up_at'])
                    : null;
            }

            $lockedConversation->update($attributes);
            $updated = $lockedConversation->fresh();

            \App\Services\AuditTrail::record(
                'communication.updated',
                $updated,
                $before,
                $this->conversationState($updated),
            );
        });

        return $updated;
    }

    public function normalizeIdempotencyKey(?string $key): ?string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }

        if (! preg_match(self::IDEMPOTENCY_PATTERN, $key)) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => 'Use up to 100 letters, numbers, dots, underscores, colons or hyphens.',
            ]);
        }

        return $key;
    }

    public function replyHash(Conversation $conversation, string $body): string
    {
        return hash('sha256', (string) json_encode([
            'conversation_uuid' => $conversation->uuid,
            'body' => $body,
        ], JSON_UNESCAPED_SLASHES));
    }

    public function messageState(ConversationMessage $message): array
    {
        return [
            'uuid' => (string) $message->uuid,
            'conversation_uuid' => (string) ($message->conversation?->uuid ?? ''),
            'direction' => $message->direction,
            'delivery_status' => $message->delivery_status,
            'provider_message_id' => $message->provider_message_id,
            'delivery_attempts' => (int) $message->delivery_attempts,
            'idempotency_key' => $message->idempotency_key,
            'sent_at' => optional($message->sent_at)->toISOString(),
        ];
    }

    public function conversationState(Conversation $conversation): array
    {
        return [
            'uuid' => (string) $conversation->uuid,
            'channel' => $conversation->channel,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
            'assigned_to' => $conversation->assigned_to,
            'follow_up_at' => optional($conversation->follow_up_at)->toISOString(),
            'correlation_id' => $conversation->correlation_id,
        ];
    }

    private function correlationId(): string
    {
        $candidate = app()->bound('request') ? request()->attributes->get('correlation_id') : null;

        return is_string($candidate) && Str::isUuid($candidate)
            ? $candidate
            : (string) Str::uuid();
    }
}
