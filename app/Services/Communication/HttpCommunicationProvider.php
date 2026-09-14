<?php

namespace App\Services\Communication;

use App\Contracts\CommunicationProvider;
use App\Models\ConversationMessage;
use App\Support\CommunicationSendResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class HttpCommunicationProvider implements CommunicationProvider
{
    abstract protected function channel(): string;

    public function send(ConversationMessage $message): CommunicationSendResult
    {
        $channel = $this->channel();
        $endpoint = trim((string) config('communication.endpoints.'.$channel));

        if ($endpoint === '') {
            throw new RuntimeException('The '.$channel.' communication endpoint is not configured.');
        }

        $conversation = $message->relationLoaded('conversation')
            ? $message->conversation
            : $message->conversation()->first();

        if (! $conversation) {
            throw new RuntimeException('The communication message has no conversation.');
        }

        $correlationId = (string) data_get($message->payload, 'correlation_id', $message->uuid);
        $payload = [
            'channel' => $channel,
            'message_uuid' => (string) $message->uuid,
            'conversation_uuid' => (string) $conversation->uuid,
            'to' => (string) $conversation->contact,
            'subject' => (string) ($conversation->subject ?: ''),
            'body' => (string) $message->body,
            'correlation_id' => $correlationId,
            'idempotency_key' => (string) ($message->idempotency_key ?: $message->uuid),
        ];

        $request = Http::acceptJson()
            ->asJson()
            ->timeout(max(1, (int) config('communication.timeout', 15)))
            ->withHeaders([
                'Idempotency-Key' => $payload['idempotency_key'],
                'X-Correlation-ID' => $correlationId,
                'X-Communication-Channel' => $channel,
            ]);

        $token = trim((string) config('communication.tokens.'.$channel));
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->post($endpoint, $payload);
        if (! $response->successful()) {
            return new CommunicationSendResult(
                'failed',
                null,
                'http_'.(string) $response->status(),
                'The communication provider rejected the message.',
            );
        }

        $data = $response->json();
        $providerMessageId = is_array($data)
            ? data_get($data, 'provider_message_id') ?? data_get($data, 'message_id') ?? data_get($data, 'id')
            : null;
        $providerStatus = is_array($data) ? data_get($data, 'status') : null;
        $status = in_array($providerStatus, ['queued', 'accepted', 'delivered', 'failed'], true)
            ? $providerStatus
            : 'accepted';

        return new CommunicationSendResult(
            $status,
            is_scalar($providerMessageId) ? (string) $providerMessageId : null,
            $status === 'failed' && is_array($data) && is_scalar(data_get($data, 'error_code'))
                ? (string) data_get($data, 'error_code')
                : null,
            null,
        );
    }
}
