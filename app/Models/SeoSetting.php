<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeoSetting extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $setting): void {
            $setting->uuid ??= (string) Str::uuid();
        });
    }
}
