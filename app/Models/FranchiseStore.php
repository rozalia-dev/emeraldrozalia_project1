<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FranchiseStore extends Model
{
    use BelongsToTenant;

    public const STATUSES = [
        'pending',
        'onboarding',
        'active',
        'open',
        'inactive',
        'suspended',
        'terminated',
    ];

    protected $table = 'franchise_stores';
    protected $guarded = [];

    protected $casts = [
        'address' => 'array',
        'opened_at' => 'date',
        'status_changed_at' => 'datetime',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $store) => $store->uuid ??= Str::uuid()->toString());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(FranchiseApplication::class, 'franchise_application_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function lifecycleActions(): HasMany
    {
        return $this->hasMany(FranchiseStoreAction::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
