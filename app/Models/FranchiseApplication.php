<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class FranchiseApplication extends Model
{
    use BelongsToTenant;

    protected $guarded=[];
    protected $casts=['data'=>'array','follow_up_at'=>'datetime'];

    protected static function booted():void
    {
        static::creating(function (self $row): void {
            $row->uuid ??= Str::uuid()->toString();

            if (! $row->customer_id && $row->inquiry_id) {
                $row->customer_id = Inquiry::withoutGlobalScopes()
                    ->whereKey($row->inquiry_id)
                    ->value('customer_id');
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function salesQuote(): HasOne
    {
        return $this->hasOne(SalesQuote::class);
    }
}
