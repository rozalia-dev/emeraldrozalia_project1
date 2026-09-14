<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_vip' => 'boolean'];
    }

    public function customers()
    {
        return $this->belongsToMany(User::class, 'customer_group_user')->withTimestamps();
    }

    public function primaryCustomers()
    {
        return $this->hasMany(CustomerProfile::class, 'primary_group_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
