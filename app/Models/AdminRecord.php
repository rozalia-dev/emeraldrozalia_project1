<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminRecord extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'record_date' => 'date',
        'deleted_at' => 'datetime',
    ];
}
