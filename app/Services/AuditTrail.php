<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class AuditTrail {
    public static function record(string $action,?Model $subject=null,?array $before=null,?array $after=null):void {
        $requestId = request()->attributes->get('correlation_id') ?: request()->header('X-Correlation-ID');
        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        AuditLog::create(['user_id'=>auth()->id(),'action'=>$action,'subject_type'=>$subject?->getMorphClass(),'subject_id'=>$subject?->getKey(),'request_id'=>$requestId,'ip_address'=>request()->ip(),'before'=>$before,'after'=>$after]);
    }
}
