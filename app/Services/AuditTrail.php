<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class AuditTrail {
    public static function record(string $action,?Model $subject=null,?array $before=null,?array $after=null):void {
        AuditLog::create(['user_id'=>auth()->id(),'action'=>$action,'subject_type'=>$subject?->getMorphClass(),'subject_id'=>$subject?->getKey(),'request_id'=>(string)Str::uuid(),'ip_address'=>request()->ip(),'before'=>$before,'after'=>$after]);
    }
}
