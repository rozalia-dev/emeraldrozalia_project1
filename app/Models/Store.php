<?php
namespace App\Models; use App\Models\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Store extends Model { use BelongsToTenant; protected $fillable=['company_id','owner_id','name','slug','business_name','business_type','email','phone','description','branding','settings','status']; protected function casts():array{return ['branding'=>'array','settings'=>'array'];} public function products():BelongsToMany{return $this->belongsToMany(Product::class)->withPivot(['price','is_active'])->withTimestamps();} }
