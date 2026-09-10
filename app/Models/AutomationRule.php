<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AutomationRule extends Model
{
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
