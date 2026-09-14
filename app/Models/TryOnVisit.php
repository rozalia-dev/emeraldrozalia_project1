<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TryOnVisit extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
    protected $casts = ['day'=>'date','converted'=>'boolean'];
    public function asset() { return $this->belongsTo(TryOnAsset::class, 'try_on_asset_id'); }
}
