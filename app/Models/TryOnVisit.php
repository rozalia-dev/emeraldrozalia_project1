<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TryOnVisit extends Model
{
    protected $guarded = [];
    protected $casts = ['day'=>'date','converted'=>'boolean'];
    public function asset() { return $this->belongsTo(TryOnAsset::class, 'try_on_asset_id'); }
}
