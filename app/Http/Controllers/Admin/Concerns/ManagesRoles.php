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

trait ManagesRoles
{
    public function roles(Request $request)
    {
        return $this->renderRolesPage($request, 'roles');
    }

    public function rolesPermissions(Request $request)
    {
        return $this->renderRolesPage($request, 'roles-permissions');
    }

    private function renderRolesPage(Request $request, string $page)
    {
        $query = Role::withCount(['users','permissions'])->with('permissions');
        if ($q = trim((string)$request->q)) $query->where(fn($b)=>$b->where('name','ilike',"%{$q}%")->orWhere('label','ilike',"%{$q}%")->orWhere('description','ilike',"%{$q}%"));
        if ($type = $request->string('type')->toString()) $query->where('type',$type);
        if ($request->filled('status')) $query->where('is_active',$request->boolean('status'));
        $roles = $query->orderBy('level')->orderBy('label')->paginate(12)->withQueryString();
        $selectedId = $request->integer('selected');
        $selected = Role::with(['permissions'=>fn($q)=>$q->orderBy('module')->orderBy('action'),'users'])->withCount(['users','permissions'])->find($selectedId ?: optional($roles->first())->id);
        $total = Role::count();
        $metrics = [
            ['label'=>'Total Roles','value'=>$total,'tone'=>'green','sub'=>'+'.Role::where('created_at','>=',now()->subDays(30))->count().' vs last 30 days'],
            ['label'=>'System Roles','value'=>Role::where('type','system')->count(),'tone'=>'blue','sub'=>'Core system roles'],
            ['label'=>'Custom Roles','value'=>Role::where('type','custom')->count(),'tone'=>'purple','sub'=>'Created by organization'],
            ['label'=>'Active Roles','value'=>Role::where('is_active',true)->count(),'tone'=>'orange','sub'=>$this->percentage(Role::where('is_active',true)->count(),$total).'% of total'],
            ['label'=>'Inactive Roles','value'=>Role::where('is_active',false)->count(),'tone'=>'red','sub'=>$this->percentage(Role::where('is_active',false)->count(),$total).'% of total'],
            ['label'=>'Roles with Users','value'=>Role::has('users')->count(),'tone'=>'teal','sub'=>$this->percentage(Role::has('users')->count(),$total).'% of total'],
        ];
        return view('admin.user-system.index', [
            'page'=>$page,'title'=>$page==='roles'?'Roles Management':'User Roles & Permissions',
            'subtitle'=>$page==='roles'?'Manage user roles and define access levels.':'Manage user roles, permissions and access control across the system.',
            'metrics'=>$metrics,'roles'=>$roles,'selectedRole'=>$selected,
            'permissions'=>Permission::orderBy('module')->orderBy('action')->get(),
            'permissionModules'=>Permission::orderBy('module')->get()->groupBy(fn($p)=>$p->module ?: $p->group),
            'roleAnalytics'=>$this->roleAnalytics(),
            'recentRoleChanges'=>AuditLog::where('action','like','roles.%')->latest('created_at')->limit(6)->get(),
        ]);
    }

    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string|max:100|unique:roles,name','label'=>'required|string|max:120','description'=>'nullable|string|max:1000',
            'type'=>['required',Rule::in(['system','custom','api'])],'level'=>'required|integer|min:1|max:9','permission_ids'=>'array','permission_ids.*'=>'exists:permissions,id',
        ]);
        $role = Role::create($data + ['is_active'=>true,'created_by'=>auth()->id()]);
        $sync = []; foreach ($data['permission_ids'] ?? [] as $id) $sync[$id] = ['access_level'=>'full'];
        $role->permissions()->sync($sync);
        $this->audit('roles.created',$role,null,$role->toArray());
        return redirect()->route('admin.user-system.roles',['selected'=>$role->id])->with('success','Role created successfully.');
    }

    public function updateRole(Request $request, Role $role)
    {
        $before = $role->toArray();
        $data = $request->validate([
            'name'=>['required','string','max:100',Rule::unique('roles','name')->ignore($role->id)],'label'=>'required|string|max:120','description'=>'nullable|string|max:1000',
            'type'=>['required',Rule::in(['system','custom','api'])],'level'=>'required|integer|min:1|max:9','permission_ids'=>'array','permission_ids.*'=>'exists:permissions,id',
        ]);
        $role->update(collect($data)->except('permission_ids')->all());
        if (array_key_exists('permission_ids',$data)) { $sync=[]; foreach($data['permission_ids'] as $id)$sync[$id]=['access_level'=>'full']; $role->permissions()->sync($sync); }
        $this->audit('roles.updated',$role,$before,$role->fresh()->toArray());
        return back()->with('success','Role and permissions updated.');
    }

    public function cloneRole(Role $role)
    {
        $copy = Role::create(['uuid'=>(string)Str::uuid(),'name'=>$role->name.' Copy '.now()->format('His'),'label'=>$role->label.' Copy','description'=>$role->description,'type'=>'custom','level'=>$role->level,'is_active'=>true,'created_by'=>auth()->id()]);
        $sync=[]; foreach($role->permissions as $permission)$sync[$permission->id]=['access_level'=>$permission->pivot->access_level ?: 'full']; $copy->permissions()->sync($sync);
        $this->audit('roles.cloned',$copy,null,['source_role'=>$role->id]);
        return redirect()->route('admin.user-system.roles',['selected'=>$copy->id])->with('success','Role cloned successfully.');
    }

    public function toggleRole(Role $role)
    {
        $before=['is_active'=>$role->is_active]; $role->update(['is_active'=>!$role->is_active]);
        $this->audit('roles.status_changed',$role,$before,['is_active'=>$role->is_active]);
        return back()->with('success','Role status updated.');
    }

    public function exportAssignments()
    {
        $rows = DB::table('role_user')->join('users','users.id','=','role_user.user_id')->join('roles','roles.id','=','role_user.role_id')->select('users.name','users.email','users.department','roles.label as role','role_user.assignment_type','role_user.status','role_user.assigned_at','role_user.expires_at')->orderBy('users.name')->get();
        return response()->streamDownload(function()use($rows){$out=fopen('php://output','w');fputcsv($out,['User','Email','Department','Role','Assignment Type','Status','Assigned At','Expires At']);foreach($rows as $r)fputcsv($out,[$r->name,$r->email,$r->department,$r->role,$r->assignment_type,$r->status,$r->assigned_at,$r->expires_at]);fclose($out);},'emerald-rozalia-role-assignments.csv',['Content-Type'=>'text/csv']);
    }

    public function exportRoles()
    {
        $rows=Role::withCount(['users','permissions'])->orderBy('level')->get();
        return response()->streamDownload(function()use($rows){$out=fopen('php://output','w');fputcsv($out,['Role','Type','Level','Status','Users','Permissions','Description']);foreach($rows as $r)fputcsv($out,[$r->label,$r->type,$r->level,$r->is_active?'Active':'Inactive',$r->users_count,$r->permissions_count,$r->description]);fclose($out);},'emerald-rozalia-roles.csv',['Content-Type'=>'text/csv']);
    }

    public function assignments(Request $request)
    {
        $rows = DB::table('role_user')->join('users','users.id','=','role_user.user_id')->join('roles','roles.id','=','role_user.role_id')
            ->select('users.id as user_id','users.name as user_name','users.email','users.department','roles.id as role_id','roles.label as role_label','roles.type as role_type','role_user.assignment_type','role_user.status as assignment_status','role_user.assigned_by','role_user.assigned_at','role_user.expires_at');
        if($q=trim((string)$request->q))$rows->where(fn($b)=>$b->where('users.name','ilike',"%{$q}%")->orWhere('users.email','ilike',"%{$q}%"));
        if($request->integer('role'))$rows->where('roles.id',$request->integer('role'));
        if($department=$request->string('department')->toString())$rows->where('users.department',$department);
        if($status=$request->string('status')->toString())$rows->where('role_user.status',$status);
        if($type=$request->string('assignment_type')->toString())$rows->where('role_user.assignment_type',$type);
        $assignments=$rows->orderByDesc('role_user.assigned_at')->paginate(10)->withQueryString();
        $selectedUserId=$request->integer('selected') ?: optional($assignments->first())->user_id;
        $selected= $selectedUserId ? User::with(['roles'=>fn($q)=>$q->orderBy('level')])->find($selectedUserId) : null;
        $totalUsers=User::count(); $usersWithRoles=DB::table('role_user')->distinct('user_id')->count('user_id'); $totalAssignments=DB::table('role_user')->count();
        $multiRole=DB::table('role_user')->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->get()->count();
        $metrics=[
            ['label'=>'Total Users','value'=>$totalUsers,'tone'=>'green','sub'=>'+'.User::where('created_at','>=',now()->subDays(30))->count().' vs last 30 days'],
            ['label'=>'Users with Roles','value'=>$usersWithRoles,'tone'=>'blue','sub'=>$this->percentage($usersWithRoles,$totalUsers).'% of total users'],
            ['label'=>'Total Assignments','value'=>$totalAssignments,'tone'=>'purple','sub'=>'All role assignments'],
            ['label'=>'Users with Multiple Roles','value'=>$multiRole,'tone'=>'orange','sub'=>$this->percentage($multiRole,$totalUsers).'% of users'],
            ['label'=>'Unassigned Users','value'=>max(0,$totalUsers-$usersWithRoles),'tone'=>'red','sub'=>$this->percentage(max(0,$totalUsers-$usersWithRoles),$totalUsers).'% of total users'],
            ['label'=>'Active Assignments','value'=>DB::table('role_user')->where('status','active')->count(),'tone'=>'teal','sub'=>$this->percentage(DB::table('role_user')->where('status','active')->count(),$totalAssignments).'% of assignments'],
        ];
        return view('admin.user-system.index',[
            'page'=>'assignments','title'=>'Role Assignments','subtitle'=>'View and manage role assignments for users across the organization.',
            'metrics'=>$metrics,'assignments'=>$assignments,'selectedUser'=>$selected,'roles'=>Role::where('is_active',true)->orderBy('level')->get(),
            'usersForAssignment'=>User::orderBy('name')->get(['id','name','email']),'departments'=>User::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'assignmentAnalytics'=>$this->assignmentAnalytics(),
        ]);
    }

    public function assignRole(Request $request)
    {
        $data=$request->validate(['user_id'=>'required|exists:users,id','role_id'=>'required|exists:roles,id','assignment_type'=>['required',Rule::in(['primary','secondary','temporary'])],'expires_at'=>'nullable|date|after:now']);
        $user=User::findOrFail($data['user_id']);
        $user->roles()->syncWithoutDetaching([$data['role_id']=>['assignment_type'=>$data['assignment_type'],'status'=>'active','assigned_by'=>auth()->id(),'assigned_at'=>now(),'expires_at'=>$data['expires_at']??null]]);
        $this->audit('roles.assigned',$user,null,$data);
        return redirect()->route('admin.user-system.assignments',['selected'=>$user->id])->with('success','Role assigned successfully.');
    }

    public function removeRole(User $user, Role $role)
    {
        $user->roles()->detach($role->id); $this->audit('roles.removed',$user,['role_id'=>$role->id],null);
        return back()->with('success','Role removed from user.');
    }


    private function roleAnalytics(): array
    {
        return ['levels'=>Role::select('level',DB::raw('count(*) as aggregate'))->groupBy('level')->orderBy('level')->get()->map(fn($r)=>['label'=>'Level '.$r->level,'value'=>$r->aggregate])->all(),'users'=>Role::withCount('users')->orderByDesc('users_count')->limit(6)->get()->map(fn($r)=>['label'=>$r->label,'value'=>$r->users_count])->all()];
    }
    private function assignmentAnalytics(): array
    {
        $roles=DB::table('role_user')->join('roles','roles.id','=','role_user.role_id')->select('roles.label',DB::raw('count(*) as aggregate'))->groupBy('roles.label')->orderByDesc('aggregate')->limit(7)->get();
        $types=DB::table('role_user')->select('assignment_type as label',DB::raw('count(*) as aggregate'))->groupBy('assignment_type')->orderByDesc('aggregate')->get();
        $departments=DB::table('role_user')->join('users','users.id','=','role_user.user_id')->whereNotNull('users.department')->select('users.department as label',DB::raw('count(*) as aggregate'))->groupBy('users.department')->orderByDesc('aggregate')->limit(8)->get();
        return ['roles'=>$roles->toArray(),'types'=>$types->toArray(),'departments'=>$departments->toArray()];
    }
}
