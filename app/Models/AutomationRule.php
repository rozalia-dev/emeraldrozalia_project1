<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AutomationRule extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($rule) => $rule->uuid ??= Str::uuid()->toString());
    }
}
