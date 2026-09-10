<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\ManagesActivity;
use App\Http\Controllers\Admin\Concerns\ManagesPermissionGroups;
use App\Http\Controllers\Admin\Concerns\ManagesPermissionMatrix;
use App\Http\Controllers\Admin\Concerns\ManagesRoles;
use App\Http\Controllers\Admin\Concerns\ManagesUsers;
use App\Models\AuditLog;
use Illuminate\Support\Str;

class UserSystemController extends Controller
{
    use ManagesUsers, ManagesRoles, ManagesPermissionGroups, ManagesPermissionMatrix, ManagesActivity;

    private function audit(string $action, mixed $subject = null, ?array $before = null, ?array $after = null): void
    {
        $payload=['user_id'=>auth()->id(),'action'=>$action,'request_id'=>(string)Str::uuid(),'ip_address'=>request()->ip(),'before'=>$before,'after'=>$after];
        if($subject instanceof \Illuminate\Database\Eloquent\Model){$payload['subject_type']=$subject::class;$payload['subject_id']=$subject->getKey();}
        AuditLog::create($payload);
    }

    private function percentage(int $value, int $total): string { return $total ? number_format(($value/$total)*100,2) : '0.00'; }
    public function severityForAction(string $action): string { return preg_match('/failed|blocked|revoked|deactivated|locked|deleted/i',$action)?'high':(preg_match('/role|permission|password|security/i',$action)?'medium':'low'); }
    public function moduleForAction(string $action): string { return Str::headline(Str::before($action,'.')); }
}
