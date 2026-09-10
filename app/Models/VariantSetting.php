<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VariantSetting extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['auto_generate_sku'=>'boolean','auto_manage_stock'=>'boolean','sync_variant_stock'=>'boolean','track_variant_inventory'=>'boolean','backorder'=>'boolean','price_rounding'=>'integer','low_stock_threshold'=>'integer'];}
    public static function current(bool $persist=false):self
    {
        $companyId=session('company_id');
        $setting=static::query()->when($companyId,fn($q)=>$q->where('company_id',$companyId),fn($q)=>$q->whereNull('company_id'))->first();
        if($setting)return $setting;
        $setting=new static(['company_id'=>$companyId,'auto_generate_sku'=>true,'auto_manage_stock'=>true,'sync_variant_stock'=>true,'track_variant_inventory'=>true,'price_rounding'=>2,'inventory_policy'=>'deny','backorder'=>false,'low_stock_threshold'=>10]);
        if($persist)$setting->save();
        return $setting;
    }
}
