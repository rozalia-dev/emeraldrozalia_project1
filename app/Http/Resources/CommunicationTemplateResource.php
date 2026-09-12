<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'channel' => $this->channel,
            'name' => $this->name,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'variables' => $this->variables ?: [],
            'version' => (int) $this->version,
            'created_by_uuid' => $this->creator?->public_uuid,
            'updated_by_uuid' => $this->updater?->public_uuid,
            'published_at' => $this->published_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
