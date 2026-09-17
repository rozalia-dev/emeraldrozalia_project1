<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->order_id || ! request()->routeIs('checkout.store')) return;
            $rate = (float) (Order::withoutGlobalScopes()->whereKey($item->order_id)->value('exchange_rate') ?? 1);
            if ($rate <= 0 || abs($rate - 1.0) < 0.00000001) return;

            $item->unit_price = Money::round((float) $item->unit_price * $rate);
            $item->total = Money::round((float) $item->total * $rate);
        });
    }

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
