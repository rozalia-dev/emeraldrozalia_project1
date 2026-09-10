<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'is_admin', 'department', 'status',
        'two_factor_enabled', 'locked_at', 'last_login_at', 'reporting_to',
        'employee_code', 'password_changed_at',
    ];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime', 'password' => 'hashed', 'is_admin' => 'boolean',
            'two_factor_enabled' => 'boolean', 'locked_at' => 'datetime', 'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function orders() { return $this->hasMany(Order::class); }
    public function addresses() { return $this->hasMany(Address::class); }
    public function wishlistItems() { return $this->hasMany(Wishlist::class); }
    public function rewards() { return $this->hasMany(RewardTransaction::class); }
    public function customerProfile() { return $this->hasOne(CustomerProfile::class); }
    public function customerGroups() { return $this->belongsToMany(CustomerGroup::class, 'customer_group_user')->withTimestamps(); }
    public function customerSegments() { return $this->belongsToMany(CustomerSegment::class, 'customer_segment_user')->withTimestamps(); }
    public function companies() { return $this->belongsToMany(Company::class)->withPivot(['role', 'is_default']); }
    public function roles() { return $this->belongsToMany(Role::class)->withPivot(['assignment_type','status','assigned_by','assigned_at','expires_at']); }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_admin) return true;
        $roleIds = $this->roles()->wherePivot('status', 'active')->pluck('roles.id');
        if ($roleIds->isEmpty()) return false;
        return Permission::query()
            ->where('name', $permission)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds)->where('permission_role.access_level', '!=', 'none'))
            ->exists();
    }
}
