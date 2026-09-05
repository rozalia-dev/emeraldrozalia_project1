<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeoIssue extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $issue): void {
            $issue->uuid ??= (string) Str::uuid();
        });
    }
}
