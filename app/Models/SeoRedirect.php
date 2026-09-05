<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeoRedirect extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $redirect): void {
            $redirect->uuid ??= (string) Str::uuid();
        });
    }
}
