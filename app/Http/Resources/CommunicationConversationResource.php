<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class CommunicationConversationResource extends JsonResource
{
    private const PUBLIC_METADATA_KEYS = [
        'name', 'customer_name', 'customer_uuid', 'order_uuid', 'order_reference',
        'order_status', 'order_category', 'tracking_number', 'topic', 'category',
        'source', 'uid', 'business_activity', 'csat', 'first_response_seconds',
        'resolution_seconds', 'consent_version',
    ];

    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'channel' => $this->channel,
            'contact' => $this->contact,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'consent' => [
                'captured_at' => $this->consent_captured_at?->toIso8601String(),
                'version' => $this->consent_version,
            ],
            'customer' => $this->whenLoaded('customer', function (): ?array {
                if (! $this->customer) {
                    return null;
                }

                return [
                    'uuid' => $this->customer->public_uuid ?: $this->customer->uuid,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                ];
            }),
            'order' => $this->whenLoaded('order', function (): ?array {
                if (! $this->order) {
                    return null;
                }

                return [
                    'uuid' => $this->order->public_uuid ?: $this->order->uuid,
                    'number' => $this->order->number,
                    'status' => $this->order->status,
                ];
            }),
            'assigned_agent' => $this->whenLoaded('assignee', function (): ?array {
                if (! $this->assignee) {
                    return null;
                }

                return [
                    'uuid' => $this->assignee->public_uuid ?: $this->assignee->uuid,
                    'name' => $this->assignee->name,
                ];
            }),
            'follow_up_at' => $this->follow_up_at?->toIso8601String(),
            'metadata' => Arr::only((array) $this->metadata, self::PUBLIC_METADATA_KEYS),
            'message_count' => isset($this->messages_count)
                ? (int) $this->messages_count
                : ($this->relationLoaded('messages') ? $this->messages->count() : null),
            'messages' => $this->when(
                $this->relationLoaded('messages'),
                fn () => CommunicationConversationMessageResource::collection($this->messages),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
