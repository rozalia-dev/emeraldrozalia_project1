<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Banner extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'device_visibility' => 'array',
            'specific_pages' => 'array',
            'settings' => 'array',
            'autoplay' => 'boolean',
            'show_arrows' => 'boolean',
            'show_dots' => 'boolean',
            'pause_on_hover' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $banner): void {
            $banner->public_uuid ??= (string) Str::uuid();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BannerRevision::class)->latest('version');
    }

    public function imageUrl(): ?string
    {
        if (! filled($this->image_path)) {
            return null;
        }

        if (Str::startsWith((string) $this->image_path, ['http://', 'https://', '/'])) {
            return (string) $this->image_path;
        }

        return Storage::disk($this->image_disk ?: 'public')->url($this->image_path);
    }
}
