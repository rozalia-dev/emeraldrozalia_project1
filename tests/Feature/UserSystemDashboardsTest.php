<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSystemDashboardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_user_system_mockup_pages_render_inside_the_admin_shell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $routes = [
            'admin.user-system.users' => 'Users Management',
            'admin.user-system.roles' => 'Roles Management',
            'admin.user-system.roles-permissions' => 'User Roles & Permissions',
            'admin.user-system.assignments' => 'Role Assignments',
            'admin.user-system.permission-groups' => 'Permission Groups',
            'admin.user-system.matrix' => 'Permission Matrix',
            'admin.user-system.activity' => 'Role & Security Activity Log',
        ];

        foreach ($routes as $route => $heading) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertSee([$heading, 'Project 1 Control Panel (cPanel)', 'Users & Roles'], false);
        }
    }

    public function test_user_create_role_assignment_and_access_actions_are_persisted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $role = Role::where('name', 'Operations Manager')->firstOrFail();
        $response = $this->actingAs($admin)->post(route('admin.user-system.users.store'), [
            'name' => 'System Operator', 'email' => 'operator@example.com', 'phone' => '0890000000',
            'department' => 'Operations', 'employee_code' => 'USR-OPS-001', 'status' => 'active',
            'two_factor_enabled' => 1, 'password' => 'SecurePass123!', 'role_ids' => [$role->id],
        ]);
        $user = User::where('email', 'operator@example.com')->firstOrFail();
        $response->assertRedirect(route('admin.user-system.users', ['selected'=>$user->id]));
        $this->assertDatabaseHas('role_user', ['user_id'=>$user->id,'role_id'=>$role->id,'assignment_type'=>'primary','status'=>'active']);
        $this->assertTrue($user->fresh()->two_factor_enabled);
        $this->actingAs($admin)->post(route('admin.user-system.users.action',$user), ['action'=>'lock'])->assertRedirect();
        $this->assertNotNull($user->fresh()->locked_at);
        $this->assertDatabaseHas('audit_logs', ['action'=>'users.created','subject_id'=>(string)$user->id]);
    }

    public function test_role_permission_matrix_cycles_access_and_enforces_permission_lookup(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $role = Role::where('name','Operations Manager')->firstOrFail();
        $permission = Permission::where('name','users.roles.view')->firstOrFail();
        $user->roles()->attach($role->id, ['assignment_type'=>'primary','status'=>'active','assigned_by'=>$admin->id,'assigned_at'=>now()]);
        $this->actingAs($admin)->post(route('admin.user-system.matrix.update'), ['role_id'=>$role->id,'permission_id'=>$permission->id,'access_level'=>'view'])->assertRedirect();
        $this->assertDatabaseHas('permission_role', ['role_id'=>$role->id,'permission_id'=>$permission->id,'access_level'=>'view']);
        $this->assertTrue($user->fresh()->hasPermission('users.roles.view'));
        $this->actingAs($admin)->post(route('admin.user-system.matrix.update'), ['role_id'=>$role->id,'permission_id'=>$permission->id,'access_level'=>'none'])->assertRedirect();
        $this->assertDatabaseMissing('permission_role', ['role_id'=>$role->id,'permission_id'=>$permission->id]);
    }

    public function test_permission_groups_can_be_created_and_applied_to_roles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $role = Role::where('name','Store Manager')->firstOrFail();
        $permission = Permission::where('name','customers.view')->firstOrFail();
        $response = $this->actingAs($admin)->post(route('admin.user-system.permission-groups.store'), [
            'name'=>'Store Customer Access','type'=>'custom','description'=>'Store customer access bundle',
            'permission_ids'=>[$permission->id],'role_ids'=>[$role->id],
        ]);
        $groupId = \Illuminate\Support\Facades\DB::table('permission_groups')->where('name','Store Customer Access')->value('id');
        $response->assertRedirect(route('admin.user-system.permission-groups',['selected'=>$groupId]));
        $this->assertDatabaseHas('permission_group_permission',['permission_group_id'=>$groupId,'permission_id'=>$permission->id]);
        $this->assertDatabaseHas('permission_group_role',['permission_group_id'=>$groupId,'role_id'=>$role->id]);
        $this->assertDatabaseHas('permission_role',['permission_id'=>$permission->id,'role_id'=>$role->id,'access_level'=>'full']);
    }

    public function test_role_assignments_and_activity_log_are_database_backed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['department'=>'Sales']);
        $role = Role::where('name','Sales Representative')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.user-system.assignments.store'), ['user_id'=>$user->id,'role_id'=>$role->id,'assignment_type'=>'secondary'])->assertRedirect(route('admin.user-system.assignments',['selected'=>$user->id]));
        $this->actingAs($admin)->get(route('admin.user-system.assignments',['q'=>$user->email]))->assertOk()->assertSee([$user->email,'Sales Representative','Secondary'],false);
        $this->assertDatabaseHas('audit_logs',['action'=>'roles.assigned','subject_id'=>(string)$user->id]);
        $this->actingAs($admin)->get(route('admin.user-system.activity',['q'=>'roles.assigned']))->assertOk()->assertSee('Roles Assigned',false);
    }
}
