<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ManagesPermissionMatrix
{
    public function permissionMatrix(Request $request)
    {
        $roles=Role::query()->when($request->integer('role'),fn($q)=>$q->whereKey($request->integer('role')))->orderBy('level')->orderBy('label')->get();
        $permissions=Permission::query()
            ->when($request->filled('module'),fn($q)=>$q->where('module',$request->string('module')->toString()))
            ->when($request->filled('permission'),fn($q)=>$q->where('action',$request->string('permission')->toString()))
            ->orderBy('module')->orderBy('action')->get();
        $matrix=DB::table('permission_role')->get()->keyBy(fn($r)=>$r->role_id.':'.$r->permission_id);
        $totalSlots=max(1,Role::count()*Permission::count());
        $counts=DB::table('permission_role')->select('access_level',DB::raw('count(*) as aggregate'))->groupBy('access_level')->pluck('aggregate','access_level');
        $assigned=(int)$counts->sum();$distribution=[
            'full'=>(int)($counts['full']??0),'view'=>(int)($counts['view']??0),'limited'=>(int)($counts['limited']??0),'none'=>max(0,$totalSlots-$assigned),
        ];
        $moduleTotals=DB::table('permission_role as pr')->join('permissions as p','p.id','=','pr.permission_id')->select('p.module as label',DB::raw('count(*) as aggregate'))->groupBy('p.module')->orderByDesc('aggregate')->limit(8)->get();
        return view('admin.user-system.index',[
            'page'=>'matrix','title'=>'Permission Matrix','subtitle'=>'View and manage permissions for roles across all modules and actions.',
            'roles'=>$roles,'allRoles'=>Role::orderBy('level')->orderBy('label')->get(),'permissions'=>$permissions,'permissionModules'=>$permissions->groupBy(fn($p)=>$p->module?:$p->group),
            'matrix'=>$matrix,'distribution'=>$distribution,'totalPermissionSlots'=>$totalSlots,'moduleTotals'=>$moduleTotals,
            'recentPermissionChanges'=>AuditLog::where(fn($q)=>$q->where('action','like','permissions.%')->orWhere('action','like','roles.%'))->latest('created_at')->limit(6)->get(),
            'modules'=>Permission::whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),'actions'=>Permission::distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function updateMatrix(Request $request)
    {
        $data=$request->validate(['role_id'=>'required|exists:roles,id','permission_id'=>'required|exists:permissions,id','access_level'=>['required',Rule::in(['full','view','limited','none'])]]);
        $before=DB::table('permission_role')->where(['role_id'=>$data['role_id'],'permission_id'=>$data['permission_id']])->value('access_level');
        if($data['access_level']==='none') DB::table('permission_role')->where(['role_id'=>$data['role_id'],'permission_id'=>$data['permission_id']])->delete();
        else DB::table('permission_role')->updateOrInsert(['role_id'=>$data['role_id'],'permission_id'=>$data['permission_id']],['access_level'=>$data['access_level']]);
        $role=Role::find($data['role_id']);
        $this->audit('permissions.updated',$role,['permission_id'=>$data['permission_id'],'access_level'=>$before],['permission_id'=>$data['permission_id'],'access_level'=>$data['access_level']]);
        return back()->with('success','Permission matrix updated.');
    }

    public function exportMatrix()
    {
        $roles=Role::orderBy('label')->get();$permissions=Permission::orderBy('module')->orderBy('action')->get();$matrix=DB::table('permission_role')->get()->keyBy(fn($r)=>$r->role_id.':'.$r->permission_id);
        return response()->streamDownload(function()use($roles,$permissions,$matrix){$out=fopen('php://output','w');fputcsv($out,['Role','Module','Action','Permission','Access Level']);foreach($roles as $role)foreach($permissions as $permission){$row=$matrix->get($role->id.':'.$permission->id);fputcsv($out,[$role->label,$permission->module,$permission->action,$permission->name,$row->access_level??'none']);}fclose($out);},'emerald-rozalia-permission-matrix.csv',['Content-Type'=>'text/csv']);
    }

    public function importMatrix(Request $request)
    {
        $file=$request->validate(['file'=>'required|file|mimes:csv,txt|max:8192'])['file'];$handle=fopen($file->getRealPath(),'r');$headers=array_map(fn($v)=>Str::snake(trim((string)$v)),fgetcsv($handle)?:[]);$count=0;
        while(($row=fgetcsv($handle))!==false){if(count($row)!==count($headers))continue;$data=array_combine($headers,$row);$roleName=trim((string)($data['role']??''));$permissionName=trim((string)($data['permission']??''));$role=Role::where('label',$roleName)->orWhere('name',$roleName)->first();$permission=$permissionName?Permission::where('name',$permissionName)->first():Permission::where('module',$data['module']??'')->where('action',$data['action']??'')->first();if(!$role||!$permission)continue;$level=strtolower(trim((string)($data['access_level']??$data['access']??'none')));if(!in_array($level,['full','view','limited','none'],true))continue;if($level==='none')DB::table('permission_role')->where(['role_id'=>$role->id,'permission_id'=>$permission->id])->delete();else DB::table('permission_role')->updateOrInsert(['role_id'=>$role->id,'permission_id'=>$permission->id],['access_level'=>$level]);$count++;}
        fclose($handle);$this->audit('permissions.matrix-imported',null,null,['count'=>$count]);return back()->with('success',"{$count} permission assignments imported.");
    }
}
