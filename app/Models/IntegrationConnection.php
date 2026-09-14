<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class IntegrationConnection extends Model {use BelongsToTenant; protected $guarded=[];protected $casts=['enabled'=>'boolean','encrypted_credentials'=>'encrypted:array','tested_at'=>'datetime'];protected $hidden=['encrypted_credentials'];protected static function booted():void{static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());}}
