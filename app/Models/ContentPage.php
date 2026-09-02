<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
class ContentPage extends Model {use SoftDeletes;protected $guarded=[];protected $casts=['meta'=>'array','navigation_visible'=>'boolean','scheduled_for'=>'datetime','published_at'=>'datetime','archived_at'=>'datetime'];protected static function booted():void{static::creating(fn($page)=>$page->uuid??=Str::uuid()->toString());}public function sections(){return $this->hasMany(PageSection::class)->orderBy('sort_order');}public function revisions(){return $this->hasMany(PageRevision::class)->latest('version');}}
