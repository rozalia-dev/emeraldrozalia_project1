<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class FranchiseApplication extends Model {protected $guarded=[];protected $casts=['data'=>'array','follow_up_at'=>'datetime'];protected static function booted():void{static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());}}
