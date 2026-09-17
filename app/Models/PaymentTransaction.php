<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (! $payment->order_id) return;

            $order = Order::withoutGlobalScopes()->find($payment->order_id);
            if (! $order) return;

            $payment->company_id ??= $order->company_id;
            $payment->currency = $order->currency_code ?: $order->currency ?: $order->base_currency_code ?: 'EUR';
            $payment->base_currency = $order->base_currency_code ?: $payment->currency;
            $payment->exchange_rate = $order->exchange_rate ?: 1;
            $payment->base_amount = $order->base_total ?? $order->total;
            $payment->amount = $order->total;
        });
    }

    public function order() { return $this->belongsTo(Order::class); }
}
