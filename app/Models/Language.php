<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Language extends Model { protected $primaryKey='locale'; public $incrementing=false; protected $keyType='string'; protected $guarded=[]; }
