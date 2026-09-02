<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class ConversationMessage extends Model {protected $guarded=[];protected $casts=['payload'=>'array','sent_at'=>'datetime'];protected static function booted():void{static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());}public function conversation(){return $this->belongsTo(Conversation::class);}}
