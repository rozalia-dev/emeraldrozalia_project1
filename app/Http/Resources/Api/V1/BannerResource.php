<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_uuid' => $this->public_uuid,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'type' => $this->type,
            'position' => $this->position,
            'target_url' => $this->target_url,
            'target_type' => $this->target_type,
            'image_url' => $this->imageUrl(),
            'alt_text' => $this->alt_text ?: $this->title,
            'aria_label' => $this->aria_label,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'priority' => (int) $this->priority,
            'device_visibility' => $this->device_visibility ?: [],
        ];
    }
}
