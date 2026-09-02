<?php
namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Foundation\Auth\User as Authenticatable; use Illuminate\Notifications\Notifiable;
class User extends Authenticatable implements MustVerifyEmail { use HasFactory,Notifiable; protected $fillable=['name','email','password','phone','is_admin']; protected $hidden=['password','remember_token']; protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','is_admin'=>'boolean']; } public function orders(){return $this->hasMany(Order::class);} public function addresses(){return $this->hasMany(Address::class);} public function wishlistItems(){return $this->hasMany(Wishlist::class);} public function rewards(){return $this->hasMany(RewardTransaction::class);} 
    public function companies(){ return $this->belongsToMany(Company::class)->withPivot(['role','is_default']); }
    public function roles(){ return $this->belongsToMany(Role::class); }
    public function hasPermission(string $permission): bool { return $this->is_admin || $this->roles()->whereHas('permissions',fn($q)=>$q->where('name',$permission))->exists(); }
}
