<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class ExchangeRate extends Model { protected $guarded=[]; protected $casts=['rate'=>'decimal:8','rate_date'=>'date']; }
