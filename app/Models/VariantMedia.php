<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class VariantMedia extends Model
{
    use BelongsToTenant;

    protected $guarded=[];
    protected function casts():array{return ['metadata'=>'array','active'=>'boolean','approved_at'=>'datetime','focal_point'=>'array','crop'=>'array','responsive_variants'=>'array'];}
    protected static function booted():void{static::creating(function(VariantMedia $media):void{$media->uuid??=(string)Str::uuid();});}
    public function variant():BelongsTo{return $this->belongsTo(ProductVariant::class,'product_variant_id');}
    public function isApprovedPublic():bool{return $this->approval_status==='approved' && (bool)$this->active && (bool)$this->variant?->is_active && (bool)$this->variant?->product?->is_active;}
}
