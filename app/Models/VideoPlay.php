<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoPlay extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'seconds' => 'integer'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(ProductMedia::class, 'product_media_id');
    }
}
