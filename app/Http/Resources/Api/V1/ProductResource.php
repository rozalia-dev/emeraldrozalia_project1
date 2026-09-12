<?php

namespace App\Http\Resources\Api\V1;

use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $image = $this->image;
        if (filled($image) && ! Str::startsWith((string) $image, ['http://', 'https://', '/'])) {
            $image = Storage::disk('public')->url($image);
        }

        return [
            'public_uuid' => $this->public_uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'currency' => app(TenantContext::class)->currency(),
            'image_url' => $image,
            'stock_status' => $this->stock > 0 ? 'in_stock' : 'out_of_stock',
            'category' => $this->when($this->relationLoaded('category'), fn () => new CategoryResource($this->category)),
            'media' => $this->when($this->relationLoaded('media'), fn () => ProductMediaResource::collection($this->media)),
            'variants' => $this->when($this->relationLoaded('variants'), fn () => $this->variants->map(fn ($variant): array => [
                'public_uuid' => $variant->public_uuid,
                'sku' => $variant->sku,
                'colour' => $variant->colour,
                'size' => $variant->size,
                'price' => $variant->price,
                'stock_status' => (int) $variant->stock > 0 ? 'in_stock' : 'out_of_stock',
            ])->values()),
        ];
    }
}
