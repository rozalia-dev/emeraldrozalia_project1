<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\PublicMediaResolver;

class ProductMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $descriptor = app(PublicMediaResolver::class)->forProductMedia($this->resource, $this->alt_text);

        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'url' => $descriptor['url'] ?? null,
            'srcset' => $descriptor['srcset'] ?? null,
            'sizes' => $descriptor['sizes'] ?? null,
            'width' => $descriptor['width'] ?? null,
            'height' => $descriptor['height'] ?? null,
            'alt_text' => $this->alt_text,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
