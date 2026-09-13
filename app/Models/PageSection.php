<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class PageSection extends Model {
    protected $guarded=[];
    protected $casts=['settings'=>'array','visible'=>'boolean','focal_point'=>'array','devices'=>'array','validation_errors'=>'array'];
    protected static function booted():void{static::creating(fn($row)=>$row->uuid??=Str::uuid()->toString());}
    public function page(){return $this->belongsTo(ContentPage::class,'content_page_id');}
}
