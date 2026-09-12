<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
class Conversation extends Model
{
    protected $guarded=[];
    protected $casts=['metadata'=>'array','follow_up_at'=>'datetime'];

    protected static function booted():void
    {
        static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class,'assigned_to');
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function franchiseApplication(): BelongsTo
    {
        return $this->belongsTo(FranchiseApplication::class);
    }
}
