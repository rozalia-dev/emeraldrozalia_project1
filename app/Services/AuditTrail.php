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

        $actor = auth()->user();
        $subjectUuid = $subject?->getAttribute('uuid') ?: $subject?->getAttribute('public_uuid');

        AuditLog::create([
            'user_id' => $actor?->getKey(),
            'actor_uuid' => $actor?->getAttribute('public_uuid') ?: $actor?->getAttribute('uuid'),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_uuid' => $subjectUuid,
            'request_id' => $requestId,
            'ip_address' => request()->ip(),
            'before' => $before,
            'after' => $after,
        ]);
    }
}
