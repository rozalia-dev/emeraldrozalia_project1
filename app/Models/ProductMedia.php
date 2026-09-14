<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'active' => 'boolean',
            'approved_at' => 'datetime',
            'focal_point' => 'array',
            'crop' => 'array',
            'responsive_variants' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApprovedPublic(Builder $query): Builder
    {
        return $query
            ->where('approval_status', 'approved')
            ->where('active', true)
            ->whereHas('product', fn (Builder $productQuery) => $productQuery->published());
    }

    public function isApprovedPublic(): bool
    {
        return $this->approval_status === 'approved'
            && (bool) $this->active
            && (bool) $this->product?->isPubliclyPublished();
    }
}
