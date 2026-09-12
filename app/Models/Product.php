<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToTenant;

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
    ];

    public function category() { return $this->belongsTo(Category::class); }
    public function variants() { return $this->hasMany(ProductVariant::class); }
    public function media() { return $this->hasMany(ProductMedia::class)->where('active', true)->where('disk', 'public')->orderBy('sort_order'); }
    public function reviews() { return $this->hasMany(Review::class)->where('status', 'approved'); }
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
