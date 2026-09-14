<?php

namespace App\Services;

use App\Models\{MediaAsset, Product, ProductMedia, VariantMedia};
use Illuminate\Support\Str;

class PublicMediaResolver
{
    public function forUuid(?string $uuid, ?string $fallbackAlt = null): ?array
    {
        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            return null;
        }

        $record = MediaAsset::query()->approvedPublic()->where('uuid', $uuid)->first();
        if ($record) {
            return $this->describe($record, $fallbackAlt);
        }

        $record = ProductMedia::query()
            ->where('uuid', $uuid)
            ->where('active', true)
            ->where('approval_status', 'approved')
            ->whereHas('product', fn ($query) => $query->published())
            ->first();

        if ($record) {
            return $this->describe($record, $fallbackAlt);
        }

        $record = VariantMedia::query()
            ->where('uuid', $uuid)
            ->where('active', true)
            ->where('approval_status', 'approved')
            ->whereHas('variant', fn ($query) => $query->where('is_active', true)->whereHas('product', fn ($productQuery) => $productQuery->published()))
            ->first();

        return $record ? $this->describe($record, $fallbackAlt) : null;
    }

    public function forLegacyPath(string $path, ?string $fallbackAlt = null): ?array
    {
        $path = ltrim(trim($path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $record = MediaAsset::query()
            ->approvedPublic()
            ->where('asset_key', 'legacy:'.$path)
            ->first();

        return $record ? $this->describe($record, $fallbackAlt) : null;
    }

    /** @param iterable<string|null> $uuids */
    public function forUuids(iterable $uuids): array
    {
        $values = collect($uuids)
            ->filter(fn ($uuid): bool => is_string($uuid) && Str::isUuid($uuid))
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return [];
        }

        $assets = MediaAsset::query()->approvedPublic()->whereIn('uuid', $values)->get();
        $productMedia = ProductMedia::query()
            ->whereIn('uuid', $values)
            ->where('active', true)
            ->where('approval_status', 'approved')
            ->whereHas('product', fn ($query) => $query->published())
            ->get();

        $variantMedia = VariantMedia::query()
            ->whereIn('uuid', $values)
            ->where('active', true)
            ->where('approval_status', 'approved')
            ->whereHas('variant', fn ($query) => $query->where('is_active', true)->whereHas('product', fn ($productQuery) => $productQuery->published()))
            ->get();

        return $assets->concat($productMedia)->concat($variantMedia)->mapWithKeys(function ($record): array {
            return [$record->uuid => $this->describe($record)];
        })->all();
    }

    public function forProduct(Product $product, string $type = 'image'): ?array
    {
        if (! $product->isPubliclyPublished()) {
            return null;
        }

        $media = $product->relationLoaded('media')
            ? $product->media->firstWhere('type', $type)
            : $product->media()->where('type', $type)->first();

        return $media instanceof ProductMedia
            ? $this->forProductMedia($media, $product->name)
            : null;
    }

    public function forProductMedia(ProductMedia $media, ?string $fallbackAlt = null): ?array
    {
        if ($media->approval_status !== 'approved' || ! $media->active || ! $media->product?->isPubliclyPublished()) {
            return null;
        }

        return $this->describe($media, $fallbackAlt);
    }

    public function forVariantMedia(VariantMedia $media, ?string $fallbackAlt = null): ?array
    {
        if (! $media->isApprovedPublic()) {
            return null;
        }

        return $this->describe($media, $fallbackAlt);
    }

    public function describe(MediaAsset|ProductMedia|VariantMedia $record, ?string $fallbackAlt = null): array
    {
        $variants = $this->variants($record);
        $originalUrl = route('media.public', ['uuid' => $record->uuid]);
        $srcset = collect($variants)
            ->sortBy('width')
            ->map(fn (array $variant): string => $variant['url'].' '.$variant['width'].'w')
            ->implode(', ');

        return [
            'uuid' => $record->uuid,
            'url' => $originalUrl,
            'srcset' => $srcset ?: null,
            'sizes' => '(max-width: 720px) 100vw, (max-width: 1200px) 50vw, 1200px',
            'alt' => trim((string) ($record->alt_text ?: $fallbackAlt ?: 'Emerald Rozalia media')),
            'width' => $this->integer($record->width),
            'height' => $this->integer($record->height),
            'focal_point' => is_array($record->focal_point) ? $record->focal_point : null,
            'crop' => is_array($record->crop) ? $record->crop : null,
            'original_name' => is_string(data_get($record->metadata, 'original_name'))
                ? data_get($record->metadata, 'original_name')
                : basename((string) $record->path),
            'mime_type' => $record->mime_type,
            'variants' => $variants,
        ];
    }

    /** @return array<string, array{url: string, width: int, height: int|null}> */
    private function variants(MediaAsset|ProductMedia|VariantMedia $record): array
    {
        $source = is_array($record->responsive_variants) ? $record->responsive_variants : [];
        $result = [];

        foreach ($source as $key => $value) {
            $path = is_array($value) ? ($value['path'] ?? null) : $value;
            $width = is_array($value) ? (int) ($value['width'] ?? 0) : 0;
            if (! is_string($path) || $path === '' || $width < 1) {
                continue;
            }

            $result[(string) $key] = [
                'url' => route('media.public', ['uuid' => $record->uuid, 'variant' => (string) $key]),
                'width' => $width,
                'height' => is_array($value) && isset($value['height']) ? (int) $value['height'] : null,
            ];
        }

        return $result;
    }

    private function integer(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}
