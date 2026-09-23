<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CatalogCounty extends Model
{
    protected $fillable = [
        'public_uuid',
        'catalog_country_id',
        'code',
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $county): void {
            $county->public_uuid ??= (string) Str::uuid();
            $county->code = strtoupper(trim((string) $county->code));
        });

        static::saving(function (self $county): void {
            $county->code = strtoupper(trim((string) $county->code));
            $county->name = trim((string) $county->name);
        });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(CatalogCountry::class, 'catalog_country_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }
}
