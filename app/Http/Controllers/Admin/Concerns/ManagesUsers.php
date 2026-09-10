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

trait ManagesUsers
{
    public function users(Request $request)
    {
        $query = User::query()->with(['roles' => fn ($q) => $q->orderBy('level')]);
        if ($q = trim((string) $request->q)) {
            $query->where(fn ($builder) => $builder->where('name', 'ilike', "%{$q}%")->orWhere('email', 'ilike', "%{$q}%")->orWhere('employee_code', 'ilike', "%{$q}%"));
        }
        if ($role = $request->integer('role')) $query->whereHas('roles', fn ($q) => $q->where('roles.id', $role));
        if ($department = $request->string('department')->toString()) $query->where('department', $department);
        if ($status = $request->string('status')->toString()) $query->where('status', $status);
        if ($request->filled('two_factor')) $query->where('two_factor_enabled', $request->boolean('two_factor'));

        $users = $query->latest('id')->paginate(10)->withQueryString();
        $selected = $request->integer('selected') ? User::with('roles')->find($request->integer('selected')) : $users->first();
        $allUsers = User::query();
        $metrics = [
            ['label'=>'Total Users','value'=>(clone $allUsers)->count(),'tone'=>'green','sub'=>'+'.(clone $allUsers)->where('created_at','>=',now()->subDays(30))->count().' vs last 30 days'],
            ['label'=>'Active Users','value'=>(clone $allUsers)->where('status','active')->count(),'tone'=>'blue','sub'=>$this->percentage((clone $allUsers)->where('status','active')->count(), (clone $allUsers)->count()).'% of total'],
            ['label'=>'Inactive Users','value'=>(clone $allUsers)->where('status','inactive')->count(),'tone'=>'orange','sub'=>$this->percentage((clone $allUsers)->where('status','inactive')->count(), (clone $allUsers)->count()).'% of total'],
            ['label'=>'Locked Users','value'=>(clone $allUsers)->whereNotNull('locked_at')->count(),'tone'=>'red','sub'=>$this->percentage((clone $allUsers)->whereNotNull('locked_at')->count(), (clone $allUsers)->count()).'% of total'],
            ['label'=>'New This Month','value'=>(clone $allUsers)->where('created_at','>=',now()->startOfMonth())->count(),'tone'=>'purple','sub'=>'Created this month'],
            ['label'=>'2FA Enabled','value'=>(clone $allUsers)->where('two_factor_enabled',true)->count(),'tone'=>'teal','sub'=>$this->percentage((clone $allUsers)->where('two_factor_enabled',true)->count(), (clone $allUsers)->count()).'% of total'],
        ];

        return view('admin.user-system.index', [
            'page'=>'users','title'=>'Users Management','subtitle'=>'Manage system users, their roles, status and access.',
            'metrics'=>$metrics,'users'=>$users,'selectedUser'=>$selected,
            'roles'=>Role::where('is_active',true)->orderBy('level')->orderBy('label')->get(),
            'departments'=>User::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'analytics'=>$this->userAnalytics(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string|max:120','email'=>'required|email|max:180|unique:users,email','phone'=>'nullable|string|max:40',
            'department'=>'nullable|string|max:120','employee_code'=>'nullable|string|max:60|unique:users,employee_code',
            'status'=>['required', Rule::in(['active','inactive'])], 'two_factor_enabled'=>'nullable|boolean',
            'password'=>'required|string|min:10','role_ids'=>'array','role_ids.*'=>'exists:roles,id',
        ]);
        $user = User::create([
            'name'=>$data['name'],'email'=>$data['email'],'phone'=>$data['phone'] ?? null,'password'=>Hash::make($data['password']),
            'department'=>$data['department'] ?? null,'employee_code'=>$data['employee_code'] ?? null,'status'=>$data['status'],
            'two_factor_enabled'=>(bool)($data['two_factor_enabled'] ?? false),
        ]);
        foreach ($data['role_ids'] ?? [] as $index => $roleId) {
            $user->roles()->attach($roleId, ['assignment_type'=>$index === 0 ? 'primary' : 'secondary','status'=>'active','assigned_by'=>auth()->id(),'assigned_at'=>now()]);
        }
        $this->audit('users.created', $user, null, $user->only(['name','email','department','status']));
        return redirect()->route('admin.user-system.users', ['selected'=>$user->id])->with('success','User created and roles assigned.');
    }

    public function updateUser(Request $request, User $user)
    {
        $before = $user->only(['name','email','phone','department','employee_code','status','two_factor_enabled','locked_at']);
        $data = $request->validate([
            'name'=>'required|string|max:120','email'=>['required','email','max:180',Rule::unique('users','email')->ignore($user->id)],
            'phone'=>'nullable|string|max:40','department'=>'nullable|string|max:120',
            'employee_code'=>['nullable','string','max:60',Rule::unique('users','employee_code')->ignore($user->id)],
            'status'=>['required',Rule::in(['active','inactive'])],'two_factor_enabled'=>'nullable|boolean','role_ids'=>'array','role_ids.*'=>'exists:roles,id',
        ]);
        $user->update([
            'name'=>$data['name'],'email'=>$data['email'],'phone'=>$data['phone'] ?? null,'department'=>$data['department'] ?? null,
            'employee_code'=>$data['employee_code'] ?? null,'status'=>$data['status'],'two_factor_enabled'=>(bool)($data['two_factor_enabled'] ?? false),
        ]);
        $sync = [];
        foreach ($data['role_ids'] ?? [] as $index => $roleId) $sync[$roleId] = ['assignment_type'=>$index === 0 ? 'primary' : 'secondary','status'=>'active','assigned_by'=>auth()->id(),'assigned_at'=>now()];
        $user->roles()->sync($sync);
        $this->audit('users.updated', $user, $before, $user->fresh()->only(array_keys($before)));
        return back()->with('success','User details and role assignments updated.');
    }

    public function userAction(Request $request, User $user)
    {
        $action = $request->validate(['action'=>['required',Rule::in(['lock','unlock','activate','deactivate','enable-2fa','disable-2fa','reset-password'])]])['action'];
        $before = $user->only(['status','locked_at','two_factor_enabled']);
        match ($action) {
            'lock' => $user->update(['locked_at'=>now()]),
            'unlock' => $user->update(['locked_at'=>null]),
            'activate' => $user->update(['status'=>'active']),
            'deactivate' => $user->update(['status'=>'inactive']),
            'enable-2fa' => $user->update(['two_factor_enabled'=>true]),
            'disable-2fa' => $user->update(['two_factor_enabled'=>false]),
            'reset-password' => Password::sendResetLink(['email'=>$user->email]),
        };
        $this->audit('users.'.$action, $user, $before, $user->fresh()->only(array_keys($before)));
        return back()->with('success', $action === 'reset-password' ? 'Password reset link requested.' : 'User access updated.');
    }

    public function importUsers(Request $request)
    {
        $file = $request->validate(['file'=>'required|file|mimes:csv,txt|max:4096'])['file'];
        $handle = fopen($file->getRealPath(), 'r');
        $headers = array_map(fn ($v) => Str::snake(trim((string)$v)), fgetcsv($handle) ?: []);
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) continue;
            $data = array_combine($headers, $row);
            if (empty($data['email']) || empty($data['name'])) continue;
            $user = User::firstOrNew(['email'=>trim($data['email'])]);
            if (!$user->exists) $user->password = Hash::make(Str::password(20));
            $user->fill([
                'name'=>trim($data['name']),'phone'=>$data['phone'] ?? null,'department'=>$data['department'] ?? null,
                'status'=>in_array(($data['status'] ?? 'active'),['active','inactive'],true) ? $data['status'] : 'active',
            ])->save();
            $roleNames = array_filter(array_map('trim', explode('|', $data['roles'] ?? '')));
            $roleIds = Role::whereIn('name',$roleNames)->orWhereIn('label',$roleNames)->pluck('id');
            foreach ($roleIds as $index => $roleId) $user->roles()->syncWithoutDetaching([$roleId=>['assignment_type'=>$index===0?'primary':'secondary','status'=>'active','assigned_by'=>auth()->id(),'assigned_at'=>now()]]);
            $count++;
        }
        fclose($handle);
        $this->audit('users.imported', null, null, ['count'=>$count]);
        return back()->with('success',"{$count} users imported successfully.");
    }

    public function exportUsers(Request $request)
    {
        $rows = User::with('roles')->orderBy('id')->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output','w'); fputcsv($out,['Name','Email','Phone','Department','Status','2FA','Roles','Created']);
            foreach ($rows as $user) fputcsv($out,[$user->name,$user->email,$user->phone,$user->department,$user->status,$user->two_factor_enabled?'Enabled':'Disabled',$user->roles->pluck('label')->join('|'),optional($user->created_at)->toDateTimeString()]);
            fclose($out);
        }, 'emerald-rozalia-users.csv', ['Content-Type'=>'text/csv']);
    }


    private function userAnalytics(): array
    {
        return [
            'roles'=>Role::withCount('users')->orderByDesc('users_count')->limit(6)->get()->map(fn($r)=>['label'=>$r->label,'value'=>$r->users_count])->all(),
            'departments'=>User::select('department',DB::raw('count(*) as aggregate'))->whereNotNull('department')->groupBy('department')->orderByDesc('aggregate')->limit(8)->get()->map(fn($r)=>['label'=>$r->department,'value'=>$r->aggregate])->all(),
        ];
    }
}
