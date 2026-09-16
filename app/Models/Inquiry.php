<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inquiry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'customer_id',
        'type',
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'message',
        'meta',
        'status',
        'correlation_id',
        'idempotency_key',
        'request_hash',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $inquiry): void {
            if ($inquiry->customer_id) {
                return;
            }

            $user = auth()->user();
            if (! $user || ! $user->hasVerifiedEmail()) {
                return;
            }

            if (mb_strtolower(trim((string) $user->email)) === mb_strtolower(trim((string) $inquiry->email))) {
                $inquiry->customer_id = $user->id;
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function franchiseApplication(): HasOne
    {
        return $this->hasOne(FranchiseApplication::class);
    }

    public function salesQuote(): HasOne
    {
        return $this->hasOne(SalesQuote::class);
    }
}
