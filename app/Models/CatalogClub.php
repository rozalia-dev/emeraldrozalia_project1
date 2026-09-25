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
        'catalog_county_code',
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
            $club->catalog_county_code = filled($club->catalog_county_code)
                ? strtoupper(trim((string) $club->catalog_county_code))
                : null;
            $club->slug = Str::slug(filled($club->slug) ? $club->slug : $club->name);
        });

        static::saving(function (self $club): void {
            $club->governing_body = strtolower(trim((string) $club->governing_body));
            $club->catalog_county_code = filled($club->catalog_county_code)
                ? strtoupper(trim((string) $club->catalog_county_code))
                : null;
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

    public function organizations(): HasMany
    {
        return $this->hasMany(CatalogClubOrganization::class, 'catalog_club_id');
    }

    public function scopeForOrganization(Builder $query, string $taxonomy): Builder
    {
        $taxonomy = strtolower(trim($taxonomy));

        return $query->where(function (Builder $organizationQuery) use ($taxonomy): void {
            $organizationQuery
                ->where('governing_body', $taxonomy)
                ->orWhereHas('organizations', fn (Builder $membershipQuery) => $membershipQuery->where('taxonomy_type', $taxonomy));
        });
    }

    public function belongsToOrganization(string $taxonomy): bool
    {
        $taxonomy = strtolower(trim($taxonomy));

        if ($this->governing_body === $taxonomy) {
            return true;
        }

        if ($this->relationLoaded('organizations')) {
            return $this->organizations->contains('taxonomy_type', $taxonomy);
        }

        return $this->organizations()->where('taxonomy_type', $taxonomy)->exists();
    }

    public function syncOrganizations(array $taxonomies): void
    {
        $taxonomies = array_values(array_unique(array_filter(array_map(
            static fn ($taxonomy): string => strtolower(trim((string) $taxonomy)),
            $taxonomies,
        ))));

        $this->organizations()->whereNotIn('taxonomy_type', $taxonomies)->delete();

        foreach ($taxonomies as $taxonomy) {
            $this->organizations()->firstOrCreate(['taxonomy_type' => $taxonomy]);
        }
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
