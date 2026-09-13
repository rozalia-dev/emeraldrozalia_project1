<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SiteLayoutVersion extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_DISABLED = 'disabled';

    protected $guarded = [];

    protected $casts = [
        'regions' => 'array',
        'validation_errors' => 'array',
        'validated_at' => 'datetime',
        'approved_at' => 'datetime',
        'activated_at' => 'datetime',
        'disabled_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (SiteLayoutVersion $layout): ?string => $layout->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
