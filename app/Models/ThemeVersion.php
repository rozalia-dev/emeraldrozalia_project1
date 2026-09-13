<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ThemeVersion extends Model
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_DISABLED = 'disabled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_ACTIVE,
        self::STATUS_SUPERSEDED,
        self::STATUS_DISABLED,
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token_payload' => 'array',
            'asset_references' => 'array',
            'validation_errors' => 'array',
            'validated_at' => 'datetime',
            'approved_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
            'rolled_back_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $theme): void {
            $theme->uuid ??= Str::uuid()->toString();
        });
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

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function disabler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    public function rollbackActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }
}
