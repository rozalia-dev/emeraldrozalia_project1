<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class VariantMedia extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['metadata'=>'array','active'=>'boolean'];}
    protected static function booted():void{static::creating(function(VariantMedia $media):void{$media->uuid??=(string)Str::uuid();});}
    public function variant():BelongsTo{return $this->belongsTo(ProductVariant::class,'product_variant_id');}
}
