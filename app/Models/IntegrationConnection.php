<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntegrationConnection extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'encrypted_credentials' => 'encrypted:array',
        'tested_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    protected $hidden = ['encrypted_credentials'];

    protected static function booted(): void
    {
        static::creating(fn (self $connection): string => $connection->uuid ??= Str::uuid()->toString());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
