<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ManagesActivity
{
    public function activity(Request $request)
    {
        $query=AuditLog::query();
        if($q=trim((string)$request->q))$query->where(fn($b)=>$b->where('action','ilike',"%{$q}%")->orWhere('ip_address','ilike',"%{$q}%"));
        if($type=$request->string('activity_type')->toString())$query->where('action','like',$type.'.%');
        if($request->integer('user'))$query->where('user_id',$request->integer('user'));
        if($request->filled('from'))$query->whereDate('created_at','>=',$request->date('from'));
        if($request->filled('to'))$query->whereDate('created_at','<=',$request->date('to'));
        $logs=$query->latest('created_at')->paginate(10)->withQueryString();
        $userIds=$logs->pluck('user_id')->filter()->unique();$logUsers=User::whereIn('id',$userIds)->get()->keyBy('id');
        $selected=$request->integer('selected')?AuditLog::find($request->integer('selected')):$logs->first();
        $total=AuditLog::count();$roleChanges=AuditLog::where(fn($q)=>$q->where('action','like','roles.%')->orWhere('action','like','permissions.%'))->count();$failed=AuditLog::where(fn($q)=>$q->where('action','ilike','%failed%')->orWhere('action','ilike','%blocked%'))->count();$critical=AuditLog::where(fn($q)=>$q->where('action','ilike','%revoked%')->orWhere('action','ilike','%deactivated%')->orWhere('action','ilike','%locked%'))->count();
        $metrics=[
            ['label'=>'Total Activities','value'=>$total,'tone'=>'green','sub'=>'+'.AuditLog::where('created_at','>=',now()->subDays(30))->count().' vs last 30 days'],
            ['label'=>'User Activities','value'=>AuditLog::whereNotNull('user_id')->count(),'tone'=>'blue','sub'=>$this->percentage(AuditLog::whereNotNull('user_id')->count(),$total).'% of total'],
            ['label'=>'Role & Permission Changes','value'=>$roleChanges,'tone'=>'purple','sub'=>$this->percentage($roleChanges,$total).'% of total'],
            ['label'=>'Security Events','value'=>AuditLog::where(fn($q)=>$q->where('action','ilike','%login%')->orWhere('action','ilike','%security%')->orWhere('action','ilike','%password%'))->count(),'tone'=>'orange','sub'=>'Authentication and security'],
            ['label'=>'Failed/Blocked Attempts','value'=>$failed,'tone'=>'red','sub'=>$this->percentage($failed,$total).'% of total'],
            ['label'=>'Critical Events','value'=>$critical,'tone'=>'teal','sub'=>$this->percentage($critical,$total).'% of total'],
        ];
        return view('admin.user-system.index',[
            'page'=>'activity','title'=>'Role & Security Activity Log','subtitle'=>'Track all user, role, permission and security related activities.','metrics'=>$metrics,'logs'=>$logs,'logUsers'=>$logUsers,'selectedLog'=>$selected,
            'users'=>User::orderBy('name')->get(['id','name','email']),'activityAnalytics'=>$this->activityAnalytics(),
        ]);
    }

    public function exportActivity()
    {
        $rows=AuditLog::latest('created_at')->get();$users=User::whereIn('id',$rows->pluck('user_id')->filter()->unique())->get()->keyBy('id');
        return response()->streamDownload(function()use($rows,$users){$out=fopen('php://output','w');fputcsv($out,['Date & Time','User','Action','Module','IP Address','Severity']);foreach($rows as $log){fputcsv($out,[optional($log->created_at)->toDateTimeString(),optional($users->get($log->user_id))->email,$log->action,$this->moduleForAction($log->action),$log->ip_address,$this->severityForAction($log->action)]);}fclose($out);},'emerald-rozalia-security-activity.csv',['Content-Type'=>'text/csv']);
    }


    private function activityAnalytics(): array
    {
        $days=collect(range(13,0))->map(function($offset){$day=now()->subDays($offset);return ['label'=>$day->format('j M'),'value'=>AuditLog::whereDate('created_at',$day->toDateString())->count()];})->all();
        $users=AuditLog::whereNotNull('user_id')->select('user_id',DB::raw('count(*) as aggregate'))->groupBy('user_id')->orderByDesc('aggregate')->limit(5)->get();$names=User::whereIn('id',$users->pluck('user_id'))->pluck('name','id');
        return ['days'=>$days,'users'=>$users->map(fn($r)=>['label'=>$names[$r->user_id]??'System','value'=>$r->aggregate])->all()];
    }
}
