<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeoAudit extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $audit): void {
            $audit->uuid ??= (string) Str::uuid();
        });
    }
}
