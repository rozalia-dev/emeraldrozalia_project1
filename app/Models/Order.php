<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantContext;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'shipping_address' => 'array',
        'subtotal' => 'decimal:2',
        'shipping' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'version' => 'integer',
        'inventory_released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (! request()->routeIs('checkout.store')) return;

            $context = app(TenantContext::class);
            $currency = $context->currency();
            $rate = $context->exchangeRate('EUR', $currency) ?? 1.0;
            if ($currency !== 'EUR' && $rate <= 0) {
                $currency = 'EUR';
                $rate = 1.0;
            }

            $order->currency = $currency;
            $order->currency_code = $currency;
            $order->exchange_rate = $rate;

            if ($rate !== 1.0) {
                foreach (['subtotal', 'shipping', 'discount', 'total'] as $field) {
                    $order->{$field} = Money::round((float) $order->{$field} * $rate);
                }
            }
        });
    }

    public function items() { return $this->hasMany(OrderItem::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function payments() { return $this->hasMany(PaymentTransaction::class); }
    public function returns() { return $this->hasMany(ReturnRequest::class); }
    public function quote() { return $this->belongsTo(SalesQuote::class, 'quote_id'); }
    public function inquiry() { return $this->belongsTo(Inquiry::class, 'inquiry_id'); }
}
