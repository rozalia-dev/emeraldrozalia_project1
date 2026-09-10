<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock' => 'integer',
            'stock_total' => 'integer',
            'low_stock_threshold' => 'integer',
            'sort_order' => 'integer',
            'option_values' => 'array',
            'track_inventory' => 'boolean',
            'backorder' => 'boolean',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant): void {
            $variant->public_uuid ??= (string) Str::uuid();
            $variant->status ??= $variant->is_active === false ? 'inactive' : 'active';
            $variant->stock_total ??= max(0, (int) $variant->stock);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_variant_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(VariantMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDisplayPriceAttribute(): float
    {
        return (float) ($this->price ?? $this->product?->price ?? 0);
    }

    public function getStockStateAttribute(): string
    {
        if ((int) $this->stock <= 0) return 'out_of_stock';
        if ((int) $this->stock <= (int) $this->low_stock_threshold) return 'low_stock';
        return 'in_stock';
    }
}
