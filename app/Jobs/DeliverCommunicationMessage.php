<?php

namespace App\Jobs;

use App\Models\ConversationMessage;
use App\Services\AuditTrail;
use App\Services\CommunicationCenter;
use App\Services\CommunicationProviderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class DeliverCommunicationMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $messageId)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(CommunicationProviderRegistry $providers, CommunicationCenter $communication): void
    {
        $message = ConversationMessage::query()->with('conversation')->find($this->messageId);
        if (! $message || ! $message->conversation) {
            return;
        }

        if (in_array($message->delivery_status, ['delivered', 'failed'], true)) {
            return;
        }

        $message->increment('delivery_attempts');
        $message->refresh();

        $provider = $providers->for((string) $message->conversation->channel);
        if (! $provider) {
            $message->update([
                'delivery_status' => 'awaiting_provider',
                'failure_code' => 'provider_not_configured',
                'failure_message' => 'A provider adapter is not configured for this channel.',
                'failed_at' => null,
            ]);
            AuditTrail::record('communication.message.awaiting_provider', $message, null, $communication->messageState($message->fresh()));
            return;
        }

        $message->update([
            'delivery_status' => 'sending',
            'failure_code' => null,
            'failure_message' => null,
            'failed_at' => null,
        ]);

        try {
            $result = $provider->send($message->fresh(['conversation']));
        } catch (Throwable $exception) {
            $message->update([
                'delivery_status' => 'retrying',
                'failure_code' => 'provider_exception',
                'failure_message' => 'The communication provider failed while accepting the message.',
            ]);
            AuditTrail::record('communication.message.delivery_retrying', $message->fresh(), null, $communication->messageState($message->fresh()));
            throw $exception;
        }

        $status = $result->status === 'accepted' ? 'queued' : $result->status;
        $message->update([
            'delivery_status' => $status,
            'provider_message_id' => $result->providerMessageId !== null
                ? Str::limit($result->providerMessageId, 180, '')
                : null,
            'delivered_at' => $status === 'delivered' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
            'failure_code' => $status === 'failed' && $result->errorCode !== null
                ? Str::limit(preg_replace('/[^A-Za-z0-9_.:-]/', '_', $result->errorCode), 80, '')
                : null,
            'failure_message' => $status === 'failed'
                ? 'The communication provider rejected the message.'
                : null,
        ]);

        AuditTrail::record('communication.message.delivery_updated', $message->fresh(), null, $communication->messageState($message->fresh()));
    }

    public function failed(Throwable $exception): void
    {
        $message = ConversationMessage::query()->find($this->messageId);
        if (! $message) {
            return;
        }

        $message->update([
            'delivery_status' => 'failed',
            'failed_at' => now(),
            'failure_code' => 'provider_exception',
            'failure_message' => 'The communication provider could not deliver the message after retries.',
        ]);

        AuditTrail::record(
            'communication.message.failed',
            $message->fresh(),
            null,
            app(CommunicationCenter::class)->messageState($message->fresh()),
        );
    }
}
