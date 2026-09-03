<?php
namespace App\Models; use App\Models\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany;
class Category extends Model { use BelongsToTenant; protected $fillable=['company_id','name','slug','description','is_active','sort_order']; protected function casts(): array{return ['is_active'=>'boolean'];} public function products(): HasMany{return $this->hasMany(Product::class);} }
