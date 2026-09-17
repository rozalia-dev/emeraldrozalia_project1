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
        'base_subtotal' => 'decimal:2',
        'base_shipping' => 'decimal:2',
        'base_discount' => 'decimal:2',
        'base_total' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'version' => 'integer',
        'inventory_released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (! request()->routeIs('checkout.store')) return;

            $context = app(TenantContext::class);
            $base = $context->baseCurrency();
            $currency = $context->currency();
            $rate = $context->exchangeRate($base, $currency);
            if ($currency !== $base && ($rate === null || $rate <= 0)) {
                $currency = $base;
                $rate = 1.0;
            }
            $rate ??= 1.0;

            $order->base_currency_code = $base;
            foreach (['subtotal','shipping','discount','total'] as $field) {
                $baseField = 'base_'.$field;
                $order->{$baseField} = Money::round($order->{$field} ?? 0);
            }

            $order->currency = $currency;
            $order->currency_code = $currency;
            $order->exchange_rate = $rate;

            if ($currency !== $base) {
                foreach (['subtotal','shipping','discount','total'] as $field) {
                    $order->{$field} = $context->convertAmount($order->{'base_'.$field}, $base, $currency);
                }
            }
        });
    }

    public function getSubtotalAttribute($value): string
    {
        return $this->reportingAmount('subtotal', $value);
    }

    public function getShippingAttribute($value): string
    {
        return $this->reportingAmount('shipping', $value);
    }

    public function getDiscountAttribute($value): string
    {
        return $this->reportingAmount('discount', $value);
    }

    public function getTotalAttribute($value): string
    {
        return $this->reportingAmount('total', $value);
    }

    public function getCurrencyAttribute($value): string
    {
        if ($this->isBaseCurrencyReportingRequest()) {
            return (string) ($this->attributes['base_currency_code'] ?? $value ?? 'EUR');
        }

        return (string) ($value ?? 'EUR');
    }

    public function transactionCurrency(): string
    {
        return strtoupper((string) ($this->attributes['currency_code'] ?? $this->attributes['currency'] ?? 'EUR'));
    }

    public function baseCurrency(): string
    {
        return strtoupper((string) ($this->attributes['base_currency_code'] ?? $this->attributes['currency'] ?? 'EUR'));
    }

    private function reportingAmount(string $field, mixed $value): string
    {
        if ($this->isBaseCurrencyReportingRequest()) {
            $base = $this->attributes['base_'.$field] ?? null;
            if ($base !== null) return Money::round($base);
        }

        return Money::round($value ?? 0);
    }

    private function isBaseCurrencyReportingRequest(): bool
    {
        if (! app()->bound('request')) return false;

        return request()->routeIs('admin.sales-reports.*')
            || request()->routeIs('admin.reports.*');
    }

    public function items() { return $this->hasMany(OrderItem::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function payments() { return $this->hasMany(PaymentTransaction::class); }
    public function returns() { return $this->hasMany(ReturnRequest::class); }
    public function quote() { return $this->belongsTo(SalesQuote::class, 'quote_id'); }
    public function inquiry() { return $this->belongsTo(Inquiry::class, 'inquiry_id'); }
}
