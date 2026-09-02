<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class Permission extends Model {protected $guarded=[];protected static function booted():void{static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());}public function roles(){return $this->belongsToMany(Role::class);}}
