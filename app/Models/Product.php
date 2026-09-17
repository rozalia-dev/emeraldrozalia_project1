<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const PUBLIC_STATUSES = ['active', 'published'];

    protected $guarded = [];
    protected $casts = [
        'colours' => 'array',
        'sizes' => 'array',
        'spin_images' => 'array',
        'product_metadata' => 'array',
        'published_at' => 'datetime',
        'seo' => 'array',
        'is_new' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        $query
            ->where($table.'.is_active', true)
            ->whereIn($table.'.status', self::PUBLIC_STATUSES);

        if ((request()->is('shop') || request()->is('category/*')) && request()->filled('country')) {
            $country = strtoupper(trim((string) request()->query('country')));
            $query->whereHas('category.catalogCountry', fn (Builder $countryQuery) => $countryQuery
                ->where('code', $country)
                ->where('is_active', true));
        }

        return $query;
    }

    public function isPubliclyPublished(): bool
    {
        return ! $this->trashed()
            && (bool) $this->is_active
            && in_array((string) $this->status, self::PUBLIC_STATUSES, true);
    }

    public function category() { return $this->belongsTo(Category::class); }
    public function variants() { return $this->hasMany(ProductVariant::class); }
    public function media() { return $this->hasMany(ProductMedia::class)->where('active', true)->where('approval_status', 'approved')->orderBy('sort_order'); }
    public function reviews() { return $this->hasMany(Review::class)->approved(); }
    public function inventoryMovements() { return $this->hasMany(InventoryMovement::class); }
    public function spins() { return $this->hasMany(ProductSpin::class); }
    public function tryOnAssets() { return $this->hasMany(TryOnAsset::class); }
    public function collections() { return $this->belongsToMany(ProductCollection::class, 'collection_product', 'product_id', 'collection_id')->withPivot('sort_order'); }

    public function latestPublicSpin(): ?ProductSpin
    {
        $spins = $this->relationLoaded('spins')
            ? $this->spins
            : $this->spins()
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->latest('updated_at')
                ->get();

        return $spins
            ->filter(fn (ProductSpin $spin): bool => $spin->status === 'published'
                && $spin->visibility === 'public'
                && count($spin->frames ?? []) >= 2)
            ->sortByDesc(fn (ProductSpin $spin): int => $spin->updated_at?->getTimestamp() ?? 0)
            ->first();
    }

    public function getSpinImagesAttribute($value): array
    {
        if ($this->exists && ($managed = $this->latestPublicSpin())) {
            return $managed->viewerData()['frames'];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
