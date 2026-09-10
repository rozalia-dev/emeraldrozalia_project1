<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class Role extends Model {
    protected $guarded=[];
    protected $casts=['is_active'=>'boolean','level'=>'integer'];
    protected static function booted():void{static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());}
    public function permissions(){return $this->belongsToMany(Permission::class)->withPivot('access_level');}
    public function users(){return $this->belongsToMany(User::class)->withPivot(['assignment_type','status','assigned_by','assigned_at','expires_at']);}
}
