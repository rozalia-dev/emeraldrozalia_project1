<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class RewardTransaction extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['points' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
