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
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if ($payment->company_id || ! $payment->order_id) {
                return;
            }

            $payment->company_id = Order::query()
                ->whereKey($payment->order_id)
                ->value('company_id');
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
