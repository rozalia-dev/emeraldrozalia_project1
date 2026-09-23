<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CatalogCountry extends Model
{
    protected $fillable = [
        'public_uuid',
        'code',
        'name',
        'is_eu',
        'is_uefa',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_eu' => 'boolean',
            'is_uefa' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $country): void {
            $country->public_uuid ??= (string) Str::uuid();
            $country->code = strtoupper(trim((string) $country->code));
        });
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(CatalogClub::class);
    }

    public function counties(): HasMany
    {
        return $this->hasMany(CatalogCounty::class, 'catalog_country_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'catalog_country_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTaxonomy(Builder $query, ?string $taxonomy): Builder
    {
        return match ($taxonomy) {
            'uefa' => $query->where('is_uefa', true),
            'traditional', 'heritage' => $query->where('is_eu', true),
            default => $query,
        };
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }
}
