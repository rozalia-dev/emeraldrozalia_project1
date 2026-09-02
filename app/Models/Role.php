<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class Role extends Model {protected $guarded=[];protected static function booted():void{static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());}public function permissions(){return $this->belongsToMany(Permission::class);}public function users(){return $this->belongsToMany(User::class);}}
