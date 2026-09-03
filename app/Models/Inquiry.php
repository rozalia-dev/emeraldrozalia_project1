<?php
namespace App\Models; use App\Models\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Model;
class Inquiry extends Model { use BelongsToTenant; protected $fillable=['company_id','type','name','email','phone','company','subject','message','meta','status']; protected function casts():array{return ['meta'=>'array'];} }
