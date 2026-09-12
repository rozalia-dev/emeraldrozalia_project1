<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = $this->path;
        if (filled($url) && ! Str::startsWith((string) $url, ['http://', 'https://', '/'])) {
            $url = Storage::disk($this->disk ?: 'public')->url($url);
        }

        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'url' => $url,
            'alt_text' => $this->alt_text,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
