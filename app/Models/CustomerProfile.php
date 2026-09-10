<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'marketing_consent' => 'boolean',
            'is_vip' => 'boolean',
            'consent_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'tags' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function primaryGroup()
    {
        return $this->belongsTo(CustomerGroup::class, 'primary_group_id');
    }
}
