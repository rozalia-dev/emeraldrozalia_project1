<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FranchiseStoreAction extends Model
{
    use BelongsToTenant;

    protected $table = 'franchise_store_actions';
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'expected_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $action) => $action->uuid ??= Str::uuid()->toString());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(FranchiseStore::class, 'franchise_store_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
