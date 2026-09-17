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
        'base_unit_price' => 'decimal:2',
        'base_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->order_id || ! request()->routeIs('checkout.store')) return;
            $order = Order::withoutGlobalScopes()->find($item->order_id);
            if (! $order) return;

            $item->base_unit_price = Money::round($item->unit_price ?? 0);
            $item->base_total = Money::round($item->total ?? 0);
            $rate = (float) ($order->exchange_rate ?? 1);
            if ($rate <= 0 || abs($rate - 1.0) < 0.00000001) return;

            $item->unit_price = Money::round((float) $item->base_unit_price * $rate);
            $item->total = Money::round((float) $item->base_total * $rate);
        });
    }

    public function getUnitPriceAttribute($value): string
    {
        return $this->reportingAmount('unit_price', $value);
    }

    public function getTotalAttribute($value): string
    {
        return $this->reportingAmount('total', $value);
    }

    private function reportingAmount(string $field, mixed $value): string
    {
        if (app()->bound('request') && (request()->routeIs('admin.sales-reports.*') || request()->routeIs('admin.reports.*'))) {
            $base = $this->attributes['base_'.$field] ?? null;
            if ($base !== null) return Money::round($base);
        }

        return Money::round($value ?? 0);
    }

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
