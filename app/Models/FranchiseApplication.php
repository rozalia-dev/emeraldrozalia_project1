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
        static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }
}
