<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CatalogClub extends Model
{
    protected $fillable = [
        'public_uuid',
        'catalog_country_id',
        'governing_body',
        'name',
        'slug',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $club): void {
            $club->public_uuid ??= (string) Str::uuid();
            $club->governing_body = strtolower(trim((string) $club->governing_body));
            $club->slug = Str::slug(filled($club->slug) ? $club->slug : $club->name);
        });

        static::saving(function (self $club): void {
            $club->governing_body = strtolower(trim((string) $club->governing_body));
            $club->slug = Str::slug(filled($club->slug) ? $club->slug : $club->name);
        });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(CatalogCountry::class, 'catalog_country_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'catalog_club_id');
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
