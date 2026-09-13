<?php

namespace App\Http\Controllers;

use App\Models\{MediaAsset, ProductMedia, VariantMedia};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicMediaController extends Controller
{
    public function show(Request $request, string $uuid, ?string $variant = null)
    {
        abort_unless(Str::isUuid($uuid), 404);
        abort_unless($variant === null || preg_match('/\A[a-z0-9_-]{1,40}\z/i', $variant), 404);

        $asset = MediaAsset::query()->approvedPublic()->where('uuid', $uuid)->first();
        $productMedia = null;
        if (! $asset) {
            $productMedia = ProductMedia::query()
                ->where('uuid', $uuid)
                ->where('active', true)
                ->where('approval_status', 'approved')
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->first();
        }

        $variantMedia = null;
        if (! $asset && ! $productMedia) {
            $variantMedia = VariantMedia::query()
                ->where('uuid', $uuid)
                ->where('active', true)
                ->where('approval_status', 'approved')
                ->whereHas('variant', fn ($query) => $query->where('is_active', true)->whereHas('product', fn ($productQuery) => $productQuery->where('is_active', true)))
                ->first();
        }

        $record = $asset ?: $productMedia ?: $variantMedia;
        abort_unless($record && $record->approval_status === 'approved' && $record->active, 404);

        $disk = (string) ($record->disk ?: 'local');
        abort_unless(in_array($disk, ['local', 'public'], true), 404);
        $path = $this->pathFor($record, $variant);
        abort_unless($this->safePath($path) && Storage::disk($disk)->exists($path), 404);

        $mime = $record->mime_type ?: Storage::disk($disk)->mimeType($path);
        $headers = [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.addcslashes(basename($path), '"\\').'"',
        ];

        return response()->file(Storage::disk($disk)->path($path), $headers);
    }

    private function pathFor(MediaAsset|ProductMedia|VariantMedia $record, ?string $variant): ?string
    {
        if ($variant === null || $variant === 'original') {
            return is_string($record->path) ? $record->path : null;
        }

        $variants = is_array($record->responsive_variants) ? $record->responsive_variants : [];
        $selected = $variants[$variant] ?? null;
        $path = is_array($selected) ? ($selected['path'] ?? null) : $selected;

        return is_string($path) ? $path : null;
    }

    private function safePath(?string $path): bool
    {
        return is_string($path)
            && $path !== ''
            && ! Str::startsWith($path, ['/','\\'])
            && ! str_contains($path, '..')
            && ! preg_match('/\A(?:https?:)?\/\//i', $path);
    }
}
