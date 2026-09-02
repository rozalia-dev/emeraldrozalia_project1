<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class PageRevision extends Model {protected $guarded=[];protected $casts=['snapshot'=>'array'];protected static function booted():void{static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());}public function page(){return $this->belongsTo(ContentPage::class,'content_page_id');}}
