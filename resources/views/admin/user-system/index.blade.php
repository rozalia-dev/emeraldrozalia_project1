@extends('layouts.admin')
@section('title', $title)
@push('styles')
<link rel="stylesheet" href="/css/user-system-admin.css?v=20260910-3">
@endpush
@push('scripts')
<script src="/js/user-system-admin.js?v=20260910-3"></script>
@endpush

@section('content')
<div class="us-page">
    <div class="us-breadcrumb">
        <span>Project 1 Control Panel (cPanel)</span><span>›</span><span>Users &amp; System</span><span>›</span><span>Users &amp; Roles</span><span>›</span><b>{{ $title }}</b>
    </div>

    <section class="us-titlebar">
        <div class="us-title-left">
            <span class="us-title-icon"><x-icon name="users" size="20" /></span>
            <div><h1>{{ $title }}</h1><p>{{ $subtitle }}</p></div>
        </div>
        <div class="us-title-actions">
            @if ($page === 'users')
                <button class="us-btn" type="button" data-us-open="import-users">Import Users</button>
                <a class="us-btn" href="{{ route('admin.user-system.users.export') }}">Export</a>
                <button class="us-btn us-btn-primary" type="button" data-us-open="add-user">+ Add User</button>
            @endif
            @if ($page === 'roles' || $page === 'roles-permissions')
                <a class="us-btn" href="{{ route('admin.user-system.matrix') }}">Permission Matrix</a>
                <a class="us-btn" href="{{ route('admin.user-system.roles.export') }}">Export Roles</a>
                <button class="us-btn us-btn-primary" type="button" data-us-open="add-role">+ Create Role</button>
            @endif
            @if ($page === 'assignments')
                <a class="us-btn" href="{{ route('admin.user-system.assignments.export') }}">Export Assignments</a>
                <button class="us-btn us-btn-primary" type="button" data-us-open="assign-role">+ Assign Role</button>
            @endif
            @if ($page === 'permission-groups')
                <button class="us-btn" type="button" data-us-open="import-groups">Import Groups</button>
                <a class="us-btn" href="{{ route('admin.user-system.permission-groups.export') }}">Export Groups</a>
                <button class="us-btn us-btn-primary" type="button" data-us-open="add-group">+ Create Group</button>
            @endif
            @if ($page === 'matrix')
                <a class="us-btn" href="{{ route('admin.user-system.matrix.export') }}">Export Matrix</a>
                <button class="us-btn" type="button" data-us-open="import-matrix">Import Matrix</button>
                <a class="us-btn us-btn-primary" href="{{ route('admin.user-system.matrix') }}">Refresh</a>
            @endif
            @if ($page === 'activity')
                <a class="us-btn" href="{{ route('admin.user-system.activity.export') }}">Export Log</a>
                <a class="us-btn us-btn-primary" href="{{ route('admin.user-system.activity') }}">Refresh</a>
            @endif
        </div>
    </section>

    @isset($metrics)
    <div class="us-metrics">
        @foreach ($metrics as $metric)
            <article class="us-metric">
                <span class="us-metric-icon tone-{{ $metric['tone'] }}"><x-icon name="users" size="18" /></span>
                <div><small>{{ $metric['label'] }}</small><strong>{{ number_format((float) $metric['value']) }}</strong><em>{{ $metric['sub'] }}</em></div>
            </article>
        @endforeach
    </div>
    @endisset

    @if ($page === 'users')
        <form class="us-filterbar" method="get">
            <label><span>Search Users</span><input name="q" value="{{ request('q') }}" placeholder="Search by name, email..."></label>
            <label><span>Role</span><select name="role"><option value="">All Roles</option>@foreach ($roles as $role)<option value="{{ $role->id }}" @selected((string) request('role') === (string) $role->id)>{{ $role->label }}</option>@endforeach</select></label>
            <label><span>Department</span><select name="department"><option value="">All Departments</option>@foreach ($departments as $department)<option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>@endforeach</select></label>
            <label><span>Status</span><select name="status"><option value="">All Status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></label>
            <label><span>2FA Status</span><select name="two_factor"><option value="">All</option><option value="1" @selected(request('two_factor') === '1')>Enabled</option><option value="0" @selected(request('two_factor') === '0')>Disabled</option></select></label>
            <button class="us-btn">Filters</button><a class="us-btn" href="{{ route('admin.user-system.users') }}">Reset</a>
        </form>
        <div class="us-main-grid">
            <section class="us-panel us-table-panel">
                <div class="us-panel-title"><h2>Users <span>({{ $users->total() }} Results)</span></h2></div>
                <div class="us-table-wrap"><table class="us-table"><thead><tr><th>User</th><th>Role</th><th>Department</th><th>Status</th><th>2FA</th><th>Last Login</th><th>Created On</th><th>Actions</th></tr></thead><tbody>
                @forelse ($users as $user)
                    <tr class="{{ $selectedUser?->id === $user->id ? 'selected' : '' }}">
                        <td><a class="us-person" href="{{ route('admin.user-system.users', ['selected' => $user->id]) }}"><span class="us-avatar">{{ strtoupper(substr($user->name, 0, 2)) }}</span><span><b>{{ $user->name }}</b><small>{{ $user->email }}</small></span></a></td>
                        <td>{{ $user->roles->first()?->label ?? 'Unassigned' }}</td><td>{{ $user->department ?: '—' }}</td>
                        <td><span class="us-pill {{ $user->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($user->status) }}</span></td><td>{{ $user->two_factor_enabled ? '✓' : '—' }}</td>
                        <td>{{ optional($user->last_login_at)->format('d M Y, H:i') ?: '—' }}</td><td>{{ optional($user->created_at)->format('d M Y') }}</td>
                        <td class="us-row-actions"><a href="{{ route('admin.user-system.users', ['selected' => $user->id]) }}">◉</a><button type="button" data-us-open="edit-user-{{ $user->id }}">✎</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="us-empty">No users match these filters.</td></tr>
                @endforelse
                </tbody></table></div>
                <div class="us-pagination"><span>Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} entries</span>{{ $users->links() }}</div>
            </section>
            <aside class="us-panel us-detail"><h2>User Details</h2>
                @if ($selectedUser)
                    <div class="us-profile-head"><span class="us-profile-avatar">{{ strtoupper(substr($selectedUser->name,0,2)) }}</span><div><h3>{{ $selectedUser->name }} <span class="us-pill {{ $selectedUser->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($selectedUser->status) }}</span></h3><p>{{ $selectedUser->roles->first()?->label ?? 'Unassigned' }}</p><small>{{ $selectedUser->email }}</small><small>{{ $selectedUser->phone ?: '—' }}</small></div></div>
                    <nav class="us-tabs"><b>Overview</b><span>Roles &amp; Permissions</span><span>Activity</span><span>Security</span></nav>
                    <dl class="us-kv"><dt>User ID</dt><dd>{{ $selectedUser->employee_code ?: 'USR-'.str_pad($selectedUser->id,6,'0',STR_PAD_LEFT) }}</dd><dt>Department</dt><dd>{{ $selectedUser->department ?: '—' }}</dd><dt>Status</dt><dd>{{ ucfirst($selectedUser->status) }}</dd><dt>2FA Status</dt><dd>{{ $selectedUser->two_factor_enabled ? 'Enabled' : 'Disabled' }}</dd><dt>Last Login</dt><dd>{{ optional($selectedUser->last_login_at)->format('d M Y H:i') ?: 'Never' }}</dd></dl>
                    <div class="us-detail-actions"><button class="us-btn us-btn-primary" type="button" data-us-open="edit-user-{{ $selectedUser->id }}">Edit User</button><form method="post" action="{{ route('admin.user-system.users.action',$selectedUser) }}">@csrf<input type="hidden" name="action" value="reset-password"><button class="us-btn">Reset Password</button></form></div>
                @endif
            </aside>
        </div>
        <div class="us-bottom-grid four"><section class="us-panel"><h3>Users by Role</h3>@include('admin.user-system.partials.bars',['items'=>$analytics['roles']])</section><section class="us-panel"><h3>Users by Department</h3>@include('admin.user-system.partials.bars',['items'=>$analytics['departments']])</section><section class="us-panel"><h3>2FA Adoption</h3><div class="us-donut"><b>{{ \App\Models\User::where('two_factor_enabled',true)->count() }}</b><span>Enabled</span></div></section><section class="us-panel"><h3>Account Status</h3><div class="us-donut"><b>{{ \App\Models\User::count() }}</b><span>Total Users</span></div></section></div>
        <dialog id="add-user" class="us-modal"><form method="post" action="{{ route('admin.user-system.users.store') }}">@csrf<h2>Add User</h2>@include('admin.user-system.partials.user-form',['editUser'=>null])<div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Create User</button></div></form></dialog>
        <dialog id="import-users" class="us-modal"><form method="post" enctype="multipart/form-data" action="{{ route('admin.user-system.users.import') }}">@csrf<h2>Import Users</h2><label class="us-field">CSV File<input type="file" name="file" accept=".csv,text/csv" required></label><div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Import</button></div></form></dialog>
        @foreach ($users as $user)
            <dialog id="edit-user-{{ $user->id }}" class="us-modal"><form method="post" action="{{ route('admin.user-system.users.update',$user) }}">@csrf @method('PATCH')<h2>Edit {{ $user->name }}</h2>@include('admin.user-system.partials.user-form',['editUser'=>$user])<div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Save User</button></div></form></dialog>
        @endforeach
    @endif

    @if ($page === 'roles' || $page === 'roles-permissions')
        <nav class="us-tabs top"><a class="{{ $page === 'roles' ? 'active' : '' }}" href="{{ route('admin.user-system.roles') }}">Roles</a><a href="{{ route('admin.user-system.matrix') }}">Permissions</a><a href="{{ route('admin.user-system.matrix') }}">Permission Matrix</a><a href="{{ route('admin.user-system.users') }}">Users</a><a class="{{ $page === 'roles-permissions' ? 'active' : '' }}" href="{{ route('admin.user-system.roles-permissions') }}">Access Control</a><a href="{{ route('admin.user-system.roles') }}">Role Hierarchy</a></nav>
        <div class="us-main-grid">
            <section class="us-panel us-table-panel"><div class="us-panel-title"><h2>Roles <span>({{ $roles->total() }} Results)</span></h2></div><div class="us-table-wrap"><table class="us-table"><thead><tr><th>Role Name</th><th>Type</th><th>Users</th><th>Level</th><th>Status</th><th>Description</th><th>Actions</th></tr></thead><tbody>
            @foreach ($roles as $role)
                <tr class="{{ $selectedRole?->id === $role->id ? 'selected' : '' }}"><td><a href="{{ route($page === 'roles' ? 'admin.user-system.roles' : 'admin.user-system.roles-permissions',['selected'=>$role->id]) }}"><b>{{ $role->label }}</b></a></td><td><span class="us-pill info">{{ ucfirst($role->type) }}</span></td><td>{{ $role->users_count }}</td><td>{{ $role->level }}</td><td><span class="us-pill {{ $role->is_active ? 'success':'danger' }}">{{ $role->is_active?'Active':'Inactive' }}</span></td><td>{{ $role->description }}</td><td class="us-row-actions"><button type="button" data-us-open="edit-role-{{ $role->id }}">✎</button><form method="post" action="{{ route('admin.user-system.roles.clone',$role) }}">@csrf<button>⧉</button></form></td></tr>
            @endforeach
            </tbody></table></div><div class="us-pagination">{{ $roles->links() }}</div></section>
            <aside class="us-panel us-detail"><h2>Role Details &amp; Permissions</h2>
                @if ($selectedRole)
                    <div class="us-role-hero"><span class="us-role-crown">♛</span><div><h3>{{ $selectedRole->label }}</h3><p>{{ ucfirst($selectedRole->type) }} Role</p></div></div>
                    <dl class="us-kv"><dt>Level</dt><dd>{{ $selectedRole->level }}</dd><dt>Users</dt><dd>{{ $selectedRole->users_count }}</dd><dt>Permissions</dt><dd>{{ $selectedRole->permissions_count }}</dd><dt>Status</dt><dd>{{ $selectedRole->is_active?'Active':'Inactive' }}</dd><dt>Description</dt><dd>{{ $selectedRole->description }}</dd></dl>
                    <div class="us-action-list"><button type="button" data-us-open="edit-role-{{ $selectedRole->id }}">✎ <span>Edit Role</span></button><a href="{{ route('admin.user-system.assignments',['role'=>$selectedRole->id]) }}">♙ <span>Assign Users</span></a><a href="{{ route('admin.user-system.matrix',['role'=>$selectedRole->id]) }}">▦ <span>Permission Matrix</span></a></div>
                @endif
            </aside>
        </div>
        @if ($page === 'roles-permissions' && $selectedRole)
            <section class="us-panel us-table-panel"><div class="us-panel-title"><h2>Module Access</h2></div><div class="us-table-wrap"><table class="us-table"><thead><tr><th>Module</th><th>View</th><th>Create</th><th>Edit</th><th>Delete</th><th>Export</th><th>Access</th></tr></thead><tbody>
            @foreach ($permissionModules as $module => $modulePermissions)
                <tr><td><b>{{ $module }}</b></td>
                @foreach (['view','create','edit','delete','export'] as $action)
                    @php $permission = $modulePermissions->firstWhere('action',$action); $grant = $permission ? $selectedRole->permissions->firstWhere('id',$permission->id) : null; @endphp
                    <td class="center">{{ $grant ? '✓' : '—' }}</td>
                @endforeach
                <td><span class="us-pill {{ $selectedRole->permissions->where('module',$module)->isNotEmpty() ? 'success':'danger' }}">{{ $selectedRole->permissions->where('module',$module)->isNotEmpty() ? 'Access':'None' }}</span></td></tr>
            @endforeach
            </tbody></table></div></section>
        @endif
        <div class="us-bottom-grid four"><section class="us-panel"><h3>Roles by Level</h3>@include('admin.user-system.partials.bars',['items'=>$roleAnalytics['levels']])</section><section class="us-panel"><h3>Top Roles by Users</h3>@include('admin.user-system.partials.bars',['items'=>$roleAnalytics['users']])</section><section class="us-panel"><h3>Permission Overview</h3><div class="us-donut"><b>{{ \App\Models\Permission::count() }}</b><span>Total Permissions</span></div></section><section class="us-panel"><h3>Recent Role Changes</h3><div class="us-activity-list">@foreach($recentRoleChanges as $log)<p>{{ \Illuminate\Support\Str::headline($log->action) }}<span>{{ optional($log->created_at)->format('d M H:i') }}</span></p>@endforeach</div></section></div>
        <dialog id="add-role" class="us-modal wide"><form method="post" action="{{ route('admin.user-system.roles.store') }}">@csrf<h2>Create Role</h2>@include('admin.user-system.partials.role-form',['editRole'=>null])<div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Create Role</button></div></form></dialog>
        @foreach ($roles as $role)<dialog id="edit-role-{{ $role->id }}" class="us-modal wide"><form method="post" action="{{ route('admin.user-system.roles.update',$role) }}">@csrf @method('PATCH')<h2>Edit {{ $role->label }}</h2>@include('admin.user-system.partials.role-form',['editRole'=>$role])<div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Save Role</button></div></form></dialog>@endforeach
    @endif

    @if ($page === 'assignments')
        <form class="us-filterbar" method="get"><label><span>Search Users</span><input name="q" value="{{ request('q') }}" placeholder="Search by name, email or user ID..."></label><label><span>Role</span><select name="role"><option value="">All Roles</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->label }}</option>@endforeach</select></label><label><span>Department</span><select name="department"><option value="">All Departments</option>@foreach($departments as $department)<option>{{ $department }}</option>@endforeach</select></label><button class="us-btn">Filters</button><a class="us-btn" href="{{ route('admin.user-system.assignments') }}">Reset</a></form>
        <div class="us-main-grid"><section class="us-panel us-table-panel"><div class="us-panel-title"><h2>Role Assignments <span>({{ $assignments->total() }} Results)</span></h2></div><div class="us-table-wrap"><table class="us-table"><thead><tr><th>User</th><th>Role</th><th>Department</th><th>Assigned On</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @forelse ($assignments as $row)
            <tr><td><a class="us-person" href="{{ route('admin.user-system.assignments',['selected'=>$row->user_id]) }}"><span class="us-avatar">{{ strtoupper(substr($row->user_name,0,2)) }}</span><span><b>{{ $row->user_name }}</b><small>{{ $row->email }}</small></span></a></td><td>{{ $row->role_label }}</td><td>{{ $row->department ?: '—' }}</td><td>{{ $row->assigned_at ? \Illuminate\Support\Carbon::parse($row->assigned_at)->format('d M Y, H:i') : '—' }}</td><td><span class="us-pill info">{{ ucfirst($row->assignment_type) }}</span></td><td><span class="us-pill {{ $row->assignment_status === 'active' ? 'success':'danger' }}">{{ ucfirst($row->assignment_status) }}</span></td><td><form method="post" action="{{ route('admin.user-system.assignments.destroy',[$row->user_id,$row->role_id]) }}">@csrf @method('DELETE')<button>⌫</button></form></td></tr>
        @empty <tr><td colspan="7" class="us-empty">No assignments found.</td></tr> @endforelse
        </tbody></table></div><div class="us-pagination">{{ $assignments->links() }}</div></section>
        <aside class="us-panel us-detail"><h2>Assignment Details</h2>@if($selectedUser)<div class="us-profile-head"><span class="us-profile-avatar">{{ strtoupper(substr($selectedUser->name,0,2)) }}</span><div><h3>{{ $selectedUser->name }}</h3><p>{{ $selectedUser->email }}</p><small>{{ $selectedUser->department ?: 'No department' }}</small></div></div><nav class="us-tabs"><b>Current Roles ({{ $selectedUser->roles->count() }})</b><span>Assignment Info</span></nav>@foreach($selectedUser->roles as $role)<article class="us-assignment-card"><b>{{ $role->label }}</b><span class="us-pill info">{{ ucfirst($role->pivot->assignment_type) }}</span><small>{{ ucfirst($role->pivot->status) }}</small></article>@endforeach@endif</aside></div>
        <div class="us-bottom-grid four"><section class="us-panel"><h3>Assignments by Role</h3>@include('admin.user-system.partials.bars',['items'=>collect($assignmentAnalytics['roles'])->map(fn($x)=>(array)$x)->all()])</section><section class="us-panel"><h3>Assignments by Type</h3>@include('admin.user-system.partials.bars',['items'=>collect($assignmentAnalytics['types'])->map(fn($x)=>(array)$x)->all()])</section><section class="us-panel"><h3>Assignments by Department</h3>@include('admin.user-system.partials.bars',['items'=>collect($assignmentAnalytics['departments'])->map(fn($x)=>(array)$x)->all()])</section><section class="us-panel"><h3>Quick Actions</h3><button class="us-btn us-btn-primary" type="button" data-us-open="assign-role">Assign Role</button></section></div>
        <dialog id="assign-role" class="us-modal"><form method="post" action="{{ route('admin.user-system.assignments.store') }}">@csrf<h2>Assign Role</h2><div class="us-form-grid"><label class="us-field">User<select name="user_id" required>@foreach($usersForAssignment as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></label><label class="us-field">Role<select name="role_id" required>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->label }}</option>@endforeach</select></label><label class="us-field">Assignment Type<select name="assignment_type"><option value="primary">Primary</option><option value="secondary">Secondary</option><option value="temporary">Temporary</option></select></label></div><div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Assign Role</button></div></form></dialog>
    @endif

    @if ($page === 'permission-groups')
        <form class="us-filterbar compact" method="get"><label><span>Search Groups</span><input name="q" value="{{ request('q') }}" placeholder="Search by group name or description..."></label><label><span>Group Type</span><select name="type"><option value="">All Types</option><option value="system">System</option><option value="custom">Custom</option></select></label><label><span>Status</span><select name="status"><option value="">All Status</option><option value="1">Active</option><option value="0">Inactive</option></select></label><button class="us-btn">Filters</button><a class="us-btn" href="{{ route('admin.user-system.permission-groups') }}">Reset</a></form>
        <div class="us-main-grid"><section class="us-panel us-table-panel"><div class="us-panel-title"><h2>Permission Groups <span>({{ $groups->total() }} Results)</span></h2></div><div class="us-table-wrap"><table class="us-table"><thead><tr><th>Group Name</th><th>Type</th><th>Description</th><th>Permissions</th><th>Roles Using</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @foreach ($groups as $group)<tr><td><a href="{{ route('admin.user-system.permission-groups',['selected'=>$group->id]) }}"><b>{{ $group->name }}</b></a></td><td>{{ ucfirst($group->type) }}</td><td>{{ $group->description }}</td><td>{{ $group->permissions_count }}</td><td>{{ $group->roles_count }}</td><td><span class="us-pill {{ $group->is_active?'success':'danger' }}">{{ $group->is_active?'Active':'Inactive' }}</span></td><td><button type="button" data-us-open="edit-group-{{ $group->id }}">✎</button></td></tr>@endforeach
        </tbody></table></div><div class="us-pagination">{{ $groups->links() }}</div></section><aside class="us-panel us-detail"><h2>Group Details</h2>@if($selectedGroup)<div class="us-role-hero"><span class="us-role-crown">◔</span><div><h3>{{ $selectedGroup->name }}</h3><p>{{ ucfirst($selectedGroup->type) }} Group</p></div></div><dl class="us-kv"><dt>Description</dt><dd>{{ $selectedGroup->description }}</dd><dt>Status</dt><dd>{{ $selectedGroup->is_active?'Active':'Inactive' }}</dd><dt>UUID</dt><dd><code>{{ $selectedGroup->uuid }}</code></dd><dt>Permissions</dt><dd>{{ count($groupPermissionIds) }}</dd><dt>Assigned To</dt><dd>{{ count($groupRoleIds) }} Roles</dd></dl>@endif</aside></div>
        <div class="us-bottom-grid four"><section class="us-panel"><h3>Groups by Type</h3>@include('admin.user-system.partials.bars',['items'=>collect($groupAnalytics['types'])->map(fn($x)=>(array)$x)->all()])</section><section class="us-panel"><h3>Groups by Status</h3>@include('admin.user-system.partials.bars',['items'=>collect($groupAnalytics['status'])->map(fn($x)=>(array)$x)->all()])</section><section class="us-panel"><h3>Top Groups by Permissions</h3>@include('admin.user-system.partials.bars',['items'=>collect($groupAnalytics['top'])->map(fn($x)=>(array)$x)->all()])</section><section class="us-panel"><h3>Recent Group Activity</h3><div class="us-activity-list">@foreach($recentGroupChanges as $log)<p>{{ \Illuminate\Support\Str::headline($log->action) }}<span>{{ optional($log->created_at)->format('d M H:i') }}</span></p>@endforeach</div></section></div>
        <dialog id="add-group" class="us-modal wide"><form method="post" action="{{ route('admin.user-system.permission-groups.store') }}">@csrf<h2>Create Permission Group</h2>@include('admin.user-system.partials.group-form',['editGroup'=>null,'groupPermissionIds'=>[],'groupRoleIds'=>[]])<div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Create Group</button></div></form></dialog>
        <dialog id="import-groups" class="us-modal"><form method="post" enctype="multipart/form-data" action="{{ route('admin.user-system.permission-groups.import') }}">@csrf<h2>Import Permission Groups</h2><label class="us-field">CSV File<input type="file" name="file" accept=".csv" required></label><div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Import</button></div></form></dialog>
        @foreach ($groups as $group)
            @php $pids=DB::table('permission_group_permission')->where('permission_group_id',$group->id)->pluck('permission_id')->map(fn($v)=>(int)$v)->all(); $rids=DB::table('permission_group_role')->where('permission_group_id',$group->id)->pluck('role_id')->map(fn($v)=>(int)$v)->all(); @endphp
            <dialog id="edit-group-{{ $group->id }}" class="us-modal wide"><form method="post" action="{{ route('admin.user-system.permission-groups.update',$group->id) }}">@csrf @method('PATCH')<h2>Edit {{ $group->name }}</h2>@include('admin.user-system.partials.group-form',['editGroup'=>$group,'groupPermissionIds'=>$pids,'groupRoleIds'=>$rids])<div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Save Group</button></div></form></dialog>
        @endforeach
    @endif

    @if ($page === 'matrix')
        <form class="us-matrix-filter" method="get"><label><span>Role</span><select name="role"><option value="">All Roles</option>@foreach($allRoles as $role)<option value="{{ $role->id }}" @selected((string)request('role')===(string)$role->id)>{{ $role->label }}</option>@endforeach</select></label><label><span>Module</span><select name="module"><option value="">All Modules</option>@foreach($modules as $module)<option value="{{ $module }}">{{ $module }}</option>@endforeach</select></label><label><span>Permission</span><select name="permission"><option value="">All Permissions</option>@foreach($actions as $action)<option value="{{ $action }}">{{ ucfirst($action) }}</option>@endforeach</select></label><button class="us-btn">Apply</button><div class="us-legend"><span><i class="perm full">✓</i><b>Full Access</b></span><span><i class="perm view">◉</i><b>View Only</b></span><span><i class="perm limited">✎</i><b>Limited Access</b></span><span><i class="perm none">−</i><b>No Access</b></span></div></form>
        <section class="us-panel us-matrix-panel"><div class="us-table-wrap"><table class="us-table us-matrix"><thead><tr><th class="sticky-col">Modules / Permissions</th>@foreach($roles as $role)<th>{{ $role->label }}<small>{{ ucfirst($role->type) }}</small></th>@endforeach</tr></thead><tbody>
        @foreach ($permissionModules as $module => $modulePermissions)
            <tr class="module-row"><td colspan="{{ 1 + $roles->count() }}">{{ $module }}</td></tr>
            @foreach ($modulePermissions as $permission)
                <tr><td class="sticky-col">{{ \Illuminate\Support\Str::headline($permission->action) }}</td>
                @foreach ($roles as $role)
                    @php $cell=$matrix->get($role->id.':'.$permission->id); $level=$cell->access_level??'none'; $next=$level==='full'?'view':($level==='view'?'limited':($level==='limited'?'none':'full')); @endphp
                    <td><form method="post" action="{{ route('admin.user-system.matrix.update') }}">@csrf<input type="hidden" name="role_id" value="{{ $role->id }}"><input type="hidden" name="permission_id" value="{{ $permission->id }}"><input type="hidden" name="access_level" value="{{ $next }}"><button class="perm {{ $level }}">{{ $level==='full'?'✓':($level==='view'?'◉':($level==='limited'?'✎':'−')) }}</button></form></td>
                @endforeach
                </tr>
            @endforeach
        @endforeach
        </tbody></table></div><p class="us-note"><b>Note:</b> Click any cell to change permission.</p></section>
        <div class="us-bottom-grid four"><section class="us-panel"><h3>Permission Distribution</h3><div class="us-donut"><b>{{ number_format($totalPermissionSlots) }}</b><span>Total Permissions</span></div></section><section class="us-panel"><h3>Permissions by Module</h3>@include('admin.user-system.partials.bars',['items'=>collect($moduleTotals)->map(fn($x)=>(array)$x)->all()])</section><section class="us-panel"><h3>Recent Changes</h3><div class="us-activity-list">@foreach($recentPermissionChanges as $log)<p>{{ \Illuminate\Support\Str::headline($log->action) }}<span>{{ optional($log->created_at)->format('d M H:i') }}</span></p>@endforeach</div></section><section class="us-panel"><h3>Quick Actions</h3><a class="us-btn" href="{{ route('admin.user-system.roles-permissions') }}">Compare Roles</a></section></div>
        <dialog id="import-matrix" class="us-modal"><form method="post" enctype="multipart/form-data" action="{{ route('admin.user-system.matrix.import') }}">@csrf<h2>Import Permission Matrix</h2><label class="us-field">CSV File<input type="file" name="file" accept=".csv" required></label><div class="us-detail-actions"><button type="button" class="us-btn" data-us-close>Cancel</button><button class="us-btn us-btn-primary">Import Matrix</button></div></form></dialog>
    @endif

    @if ($page === 'activity')
        <form class="us-filterbar" method="get"><label><span>Search Activities</span><input name="q" value="{{ request('q') }}" placeholder="Search by user, action, module, IP..."></label><label><span>Activity Type</span><select name="activity_type"><option value="">All Types</option><option value="users">Users</option><option value="roles">Roles</option><option value="permissions">Permissions</option><option value="authentication">Authentication</option></select></label><label><span>User</span><select name="user"><option value="">All Users</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></label><label><span>From</span><input type="date" name="from" value="{{ request('from') }}"></label><label><span>To</span><input type="date" name="to" value="{{ request('to') }}"></label><button class="us-btn">Filters</button><a class="us-btn" href="{{ route('admin.user-system.activity') }}">Reset</a></form>
        <div class="us-main-grid"><section class="us-panel us-table-panel"><div class="us-panel-title"><h2>Activity Log <span>({{ $logs->total() }} Results)</span></h2></div><div class="us-table-wrap"><table class="us-table"><thead><tr><th>Date &amp; Time</th><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>IP Address</th><th>Severity</th></tr></thead><tbody>
        @forelse ($logs as $log)
            @php $u=$logUsers->get($log->user_id); $severity=app(\App\Http\Controllers\Admin\UserSystemController::class)->severityForAction($log->action); @endphp
            <tr><td><a href="{{ route('admin.user-system.activity',['selected'=>$log->id]) }}">{{ optional($log->created_at)->format('d M Y, H:i') }}</a></td><td>{{ $u?->name ?? 'System' }}</td><td><b>{{ \Illuminate\Support\Str::headline($log->action) }}</b></td><td>{{ \Illuminate\Support\Str::headline(\Illuminate\Support\Str::before($log->action,'.')) }}</td><td>{{ \Illuminate\Support\Str::limit(json_encode($log->after),60) }}</td><td>{{ $log->ip_address ?: '—' }}</td><td><span class="us-pill {{ $severity==='high'?'danger':($severity==='medium'?'warning':'success') }}">{{ ucfirst($severity) }}</span></td></tr>
        @empty <tr><td colspan="7" class="us-empty">No security activity recorded yet.</td></tr> @endforelse
        </tbody></table></div><div class="us-pagination">{{ $logs->links() }}</div></section><aside class="us-panel us-detail"><h2>Activity Details</h2>@if($selectedLog)<div class="us-role-hero"><span class="us-role-crown">↻</span><div><h3>{{ \Illuminate\Support\Str::headline($selectedLog->action) }}</h3></div></div><dl class="us-kv"><dt>Date &amp; Time</dt><dd>{{ optional($selectedLog->created_at)->format('d M Y, H:i:s') }}</dd><dt>User</dt><dd>{{ $logUsers->get($selectedLog->user_id)?->name ?? 'System / Unknown' }}</dd><dt>Module</dt><dd>{{ \Illuminate\Support\Str::headline(\Illuminate\Support\Str::before($selectedLog->action,'.')) }}</dd><dt>IP Address</dt><dd>{{ $selectedLog->ip_address ?: '—' }}</dd><dt>Details</dt><dd><code>{{ json_encode($selectedLog->after) }}</code></dd></dl>@endif</aside></div>
        <div class="us-bottom-grid four"><section class="us-panel"><h3>Activities by Type</h3><div class="us-donut"><b>{{ number_format($logs->total()) }}</b><span>Total Activities</span></div></section><section class="us-panel"><h3>Activities Over Time</h3>@include('admin.user-system.partials.spark',['items'=>$activityAnalytics['days']])</section><section class="us-panel"><h3>Top Users by Activity</h3>@include('admin.user-system.partials.bars',['items'=>$activityAnalytics['users']])</section><section class="us-panel"><h3>Quick Actions</h3><a class="us-btn" href="{{ route('admin.user-system.activity.export') }}">Export Activity</a></section></div>
    @endif
</div>
@endsection
