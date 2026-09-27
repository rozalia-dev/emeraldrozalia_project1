<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiFashionGeneration extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function product() { return $this->belongsTo(Product::class); }
    public function media() { return $this->belongsTo(ProductMedia::class, 'product_media_id'); }
}
