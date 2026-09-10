<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductCollection extends Model
{
    use BelongsToTenant;

    protected $table = 'product_collections';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'show_on_homepage' => 'boolean',
            'allow_in_filters' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $collection): void {
            $collection->public_uuid ??= (string) Str::uuid();
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_product', 'collection_id', 'product_id')
            ->withPivot('sort_order')
            ->orderBy('collection_product.sort_order')
            ->orderBy('products.name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
