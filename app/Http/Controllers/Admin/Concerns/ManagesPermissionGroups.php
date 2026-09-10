<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ManagesPermissionGroups
{
    public function permissionGroups(Request $request)
    {
        $query = DB::table('permission_groups as pg')->select('pg.*')
            ->selectSub(fn($q)=>$q->from('permission_group_permission')->selectRaw('count(*)')->whereColumn('permission_group_id','pg.id'),'permissions_count')
            ->selectSub(fn($q)=>$q->from('permission_group_role')->selectRaw('count(*)')->whereColumn('permission_group_id','pg.id'),'roles_count');
        if($q=trim((string)$request->q)) $query->where(fn($b)=>$b->where('pg.name','ilike',"%{$q}%")->orWhere('pg.description','ilike',"%{$q}%"));
        if($type=$request->string('type')->toString()) $query->where('pg.type',$type);
        if($request->filled('status')) $query->where('pg.is_active',$request->boolean('status'));
        $groups=$query->orderBy('pg.name')->paginate(10)->withQueryString();
        $selectedId=$request->integer('selected') ?: optional($groups->first())->id;
        $selected=$selectedId ? DB::table('permission_groups')->where('id',$selectedId)->first() : null;
        $groupPermissionIds=$selected ? DB::table('permission_group_permission')->where('permission_group_id',$selected->id)->pluck('permission_id')->map(fn($v)=>(int)$v)->all() : [];
        $groupRoleIds=$selected ? DB::table('permission_group_role')->where('permission_group_id',$selected->id)->pluck('role_id')->map(fn($v)=>(int)$v)->all() : [];
        $total=DB::table('permission_groups')->count();
        $active=DB::table('permission_groups')->where('is_active',true)->count();
        $metrics=[
            ['label'=>'Total Groups','value'=>$total,'tone'=>'green','sub'=>'+'.DB::table('permission_groups')->where('created_at','>=',now()->subDays(30))->count().' vs last 30 days'],
            ['label'=>'System Groups','value'=>DB::table('permission_groups')->where('type','system')->count(),'tone'=>'blue','sub'=>$this->percentage(DB::table('permission_groups')->where('type','system')->count(),$total).'% of total'],
            ['label'=>'Custom Groups','value'=>DB::table('permission_groups')->where('type','custom')->count(),'tone'=>'purple','sub'=>$this->percentage(DB::table('permission_groups')->where('type','custom')->count(),$total).'% of total'],
            ['label'=>'Groups in Use','value'=>DB::table('permission_group_role')->distinct('permission_group_id')->count('permission_group_id'),'tone'=>'orange','sub'=>'Assigned to roles'],
            ['label'=>'Unused Groups','value'=>max(0,$total-DB::table('permission_group_role')->distinct('permission_group_id')->count('permission_group_id')),'tone'=>'red','sub'=>'Not assigned'],
            ['label'=>'Total Permissions','value'=>Permission::count(),'tone'=>'teal','sub'=>'Across all groups'],
        ];
        return view('admin.user-system.index',[
            'page'=>'permission-groups','title'=>'Permission Groups','subtitle'=>'Group related permissions to simplify role management and access control.',
            'metrics'=>$metrics,'groups'=>$groups,'selectedGroup'=>$selected,'groupPermissionIds'=>$groupPermissionIds,'groupRoleIds'=>$groupRoleIds,
            'permissions'=>Permission::orderBy('module')->orderBy('action')->get(),'roles'=>Role::orderBy('level')->orderBy('label')->get(),
            'groupAnalytics'=>$this->permissionGroupAnalytics(),'recentGroupChanges'=>AuditLog::where('action','like','permission-groups.%')->latest('created_at')->limit(6)->get(),
        ]);
    }

    public function storePermissionGroup(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:140|unique:permission_groups,name','type'=>['required',Rule::in(['system','custom'])],'description'=>'nullable|string|max:1000','permission_ids'=>'array','permission_ids.*'=>'exists:permissions,id','role_ids'=>'array','role_ids.*'=>'exists:roles,id']);
        $id=DB::transaction(function()use($data){
            $id=DB::table('permission_groups')->insertGetId(['uuid'=>(string)Str::uuid(),'name'=>$data['name'],'type'=>$data['type'],'description'=>$data['description']??null,'is_active'=>true,'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            $this->syncPermissionGroup($id,$data['permission_ids']??[],$data['role_ids']??[]);
            return $id;
        });
        $this->audit('permission-groups.created',null,null,['group_id'=>$id,'name'=>$data['name']]);
        return redirect()->route('admin.user-system.permission-groups',['selected'=>$id])->with('success','Permission group created and applied.');
    }

    public function updatePermissionGroup(Request $request, int $group)
    {
        abort_unless(DB::table('permission_groups')->where('id',$group)->exists(),404);
        $data=$request->validate(['name'=>['required','string','max:140',Rule::unique('permission_groups','name')->ignore($group)],'type'=>['required',Rule::in(['system','custom'])],'description'=>'nullable|string|max:1000','permission_ids'=>'array','permission_ids.*'=>'exists:permissions,id','role_ids'=>'array','role_ids.*'=>'exists:roles,id']);
        $before=(array)DB::table('permission_groups')->where('id',$group)->first();
        DB::transaction(function()use($group,$data){DB::table('permission_groups')->where('id',$group)->update(['name'=>$data['name'],'type'=>$data['type'],'description'=>$data['description']??null,'updated_at'=>now()]);$this->syncPermissionGroup($group,$data['permission_ids']??[],$data['role_ids']??[]);});
        $this->audit('permission-groups.updated',null,$before,['group_id'=>$group]+$data);
        return back()->with('success','Permission group updated.');
    }

    public function clonePermissionGroup(int $group)
    {
        $source=DB::table('permission_groups')->where('id',$group)->first(); abort_unless($source,404);
        $permissions=DB::table('permission_group_permission')->where('permission_group_id',$group)->pluck('permission_id')->all();
        $roles=DB::table('permission_group_role')->where('permission_group_id',$group)->pluck('role_id')->all();
        $name=$source->name.' Copy '.now()->format('His');
        $id=DB::transaction(function()use($source,$name,$permissions,$roles){$id=DB::table('permission_groups')->insertGetId(['uuid'=>(string)Str::uuid(),'name'=>$name,'type'=>'custom','description'=>$source->description,'is_active'=>true,'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);$this->syncPermissionGroup($id,$permissions,$roles);return $id;});
        $this->audit('permission-groups.cloned',null,null,['source_group'=>$group,'group_id'=>$id]);
        return redirect()->route('admin.user-system.permission-groups',['selected'=>$id])->with('success','Permission group cloned.');
    }

    public function togglePermissionGroup(int $group)
    {
        $row=DB::table('permission_groups')->where('id',$group)->first(); abort_unless($row,404);
        DB::table('permission_groups')->where('id',$group)->update(['is_active'=>!$row->is_active,'updated_at'=>now()]);
        $this->audit('permission-groups.status-changed',null,['is_active'=>$row->is_active],['group_id'=>$group,'is_active'=>!$row->is_active]);
        return back()->with('success','Permission group status updated.');
    }

    public function exportPermissionGroups()
    {
        $rows=DB::table('permission_groups')->orderBy('name')->get();
        return response()->streamDownload(function()use($rows){$out=fopen('php://output','w');fputcsv($out,['Name','Type','Status','Description','UUID']);foreach($rows as $r)fputcsv($out,[$r->name,$r->type,$r->is_active?'Active':'Inactive',$r->description,$r->uuid]);fclose($out);},'emerald-rozalia-permission-groups.csv',['Content-Type'=>'text/csv']);
    }

    public function importPermissionGroups(Request $request)
    {
        $file=$request->validate(['file'=>'required|file|mimes:csv,txt|max:4096'])['file'];$handle=fopen($file->getRealPath(),'r');$headers=array_map(fn($v)=>Str::snake(trim((string)$v)),fgetcsv($handle)?:[]);$count=0;
        while(($row=fgetcsv($handle))!==false){if(count($row)!==count($headers))continue;$data=array_combine($headers,$row);if(empty($data['name']))continue;$type=in_array(strtolower($data['type']??'custom'),['system','custom'],true)?strtolower($data['type']):'custom';DB::table('permission_groups')->updateOrInsert(['name'=>trim($data['name'])],['uuid'=>(string)($data['uuid']??Str::uuid()),'type'=>$type,'description'=>$data['description']??null,'is_active'=>strtolower($data['status']??'active')!=='inactive','updated_at'=>now(),'created_at'=>now()]);$count++;}
        fclose($handle);$this->audit('permission-groups.imported',null,null,['count'=>$count]);return back()->with('success',"{$count} permission groups imported.");
    }

    private function syncPermissionGroup(int $groupId, array $permissionIds, array $roleIds): void
    {
        DB::table('permission_group_permission')->where('permission_group_id',$groupId)->delete();
        DB::table('permission_group_role')->where('permission_group_id',$groupId)->delete();
        foreach(array_unique(array_map('intval',$permissionIds)) as $permissionId) DB::table('permission_group_permission')->insertOrIgnore(['permission_group_id'=>$groupId,'permission_id'=>$permissionId]);
        foreach(array_unique(array_map('intval',$roleIds)) as $roleId){DB::table('permission_group_role')->insertOrIgnore(['permission_group_id'=>$groupId,'role_id'=>$roleId]);foreach($permissionIds as $permissionId)DB::table('permission_role')->updateOrInsert(['permission_id'=>(int)$permissionId,'role_id'=>$roleId],['access_level'=>'full']);}
    }

    private function permissionGroupAnalytics(): array
    {
        $types=DB::table('permission_groups')->select('type as label',DB::raw('count(*) as aggregate'))->groupBy('type')->get();
        $status=DB::table('permission_groups')->selectRaw("CASE WHEN is_active THEN 'Active' ELSE 'Inactive' END as label, count(*) as aggregate")->groupBy('is_active')->get();
        $top=DB::table('permission_groups as pg')->leftJoin('permission_group_permission as p','p.permission_group_id','=','pg.id')->select('pg.name as label',DB::raw('count(p.permission_id) as aggregate'))->groupBy('pg.id','pg.name')->orderByDesc('aggregate')->limit(6)->get();
        return ['types'=>$types->toArray(),'status'=>$status->toArray(),'top'=>$top->toArray()];
    }
}
