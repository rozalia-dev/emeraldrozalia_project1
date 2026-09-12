<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class AuditLog extends Model {
    public $timestamps=false;
    protected $guarded=[];
    protected $casts=['before'=>'array','after'=>'array','created_at'=>'datetime'];
    protected static function booted():void { static::creating(function($row){$row->uuid??=Str::uuid()->toString();$row->created_at??=now();}); }
    public function user():BelongsTo { return $this->belongsTo(User::class); }
}
