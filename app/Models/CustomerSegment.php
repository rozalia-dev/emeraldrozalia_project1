<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rule_definition' => 'array', 'is_active' => 'boolean'];
    }

    public function customers()
    {
        return $this->belongsToMany(User::class, 'customer_segment_user')->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
