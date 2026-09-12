<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationConversationMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'conversation_uuid' => $this->conversation?->uuid,
            'direction' => $this->direction,
            'body' => $this->body,
            'delivery_status' => $this->delivery_status,
            'provider_message_id' => $this->provider_message_id,
            'delivery_attempts' => (int) $this->delivery_attempts,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
