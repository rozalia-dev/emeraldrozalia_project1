<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FranchiseStore extends Model
{
    protected $table = 'franchise_stores';
    protected $guarded = [];
    protected $casts = ['address' => 'array', 'opened_at' => 'date'];

    protected static function booted(): void
    {
        static::creating(fn (self $store) => $store->uuid ??= Str::uuid()->toString());
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(FranchiseApplication::class, 'franchise_application_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
