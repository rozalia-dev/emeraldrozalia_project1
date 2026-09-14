<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class SpinVisit extends Model
{
    use BelongsToTenant;

    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['engaged'=>'boolean'];
    public function spin() { return $this->belongsTo(ProductSpin::class,'product_spin_id'); }
}
