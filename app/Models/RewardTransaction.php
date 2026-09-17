<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class RewardTransaction extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
    protected $casts = ['points' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (self $reward): void {
            if ($reward->type !== 'earn' || blank($reward->reference)) return;
            $order = Order::withoutGlobalScopes()->where('number', $reward->reference)->first();
            if (! $order) return;

            $baseTotal = $order->base_total;
            if ($baseTotal === null) {
                $rate = (float) ($order->exchange_rate ?? 1);
                $baseTotal = $rate > 0 ? (float) $order->total / $rate : (float) $order->total;
            }
            $reward->points = intdiv(max(0, Money::toMinor($baseTotal)), 100);
        });
    }

    public function user() { return $this->belongsTo(User::class); }
}
