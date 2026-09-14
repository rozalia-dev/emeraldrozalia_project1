<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    use BelongsToTenant;

    protected $table = 'returns';

    protected $guarded = [];

    protected $casts = [
        'correlation_id' => 'string',
        'version' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
