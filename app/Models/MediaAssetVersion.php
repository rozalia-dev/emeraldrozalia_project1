<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaAssetVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'focal_point' => 'array',
            'crop' => 'array',
            'responsive_variants' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->uuid ??= (string) Str::uuid();
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
