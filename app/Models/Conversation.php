<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class Conversation extends Model {protected $guarded=[];protected $casts=['metadata'=>'array','follow_up_at'=>'datetime'];protected static function booted():void{static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());}public function messages(){return $this->hasMany(ConversationMessage::class);}public function assignee(){return $this->belongsTo(User::class,'assigned_to');}}
