<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Inquiry extends Model { protected $fillable=['type','name','email','phone','company','subject','message','meta','status']; protected function casts():array{return ['meta'=>'array'];} }
