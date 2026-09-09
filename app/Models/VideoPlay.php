<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoPlay extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'seconds' => 'integer'];
    }
}
