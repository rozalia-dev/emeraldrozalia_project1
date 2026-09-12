<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PublishedSiteSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PublishedSiteSetting $setting): void {
            $setting->uuid ??= (string) Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
