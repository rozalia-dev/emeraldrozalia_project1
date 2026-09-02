<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class AdminRecord extends Model {protected $guarded=[]; protected $casts=['data'=>'array','record_date'=>'date'];}
