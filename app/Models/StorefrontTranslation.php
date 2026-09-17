<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StorefrontTranslation extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];
}
