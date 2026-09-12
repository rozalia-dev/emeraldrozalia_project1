<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Project1Batch2ContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_role_permission_and_company_membership_grant_user_system_read_access(): void
    {
        $company = Company::create(['name' => 'Emerald Rozalia', 'code' => 'ERL', 'active' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $role = $this->roleWithPermission('users.roles.view');
        $company->users()->attach($user->id, ['role' => 'staff', 'is_default' => true]);
        $user->roles()->attach($role->id, ['assignment_type' => 'primary', 'status' => 'active', 'assigned_at' => now()]);

        $response = $this->withSession(['company_id' => $company->id])
            ->actingAs($user)
            ->get(route('admin.user-system.users'));

        $response->assertOk()->assertSeeText('Users Management');
    }

    public function test_non_member_cannot_use_a_role_permission_in_another_company_context(): void
    {
        $company = Company::create(['name' => 'Emerald Rozalia', 'code' => 'ERL', 'active' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $role = $this->roleWithPermission('users.roles.view');
        $user->roles()->attach($role->id, ['assignment_type' => 'primary', 'status' => 'active', 'assigned_at' => now()]);

        $response = $this->withSession(['company_id' => $company->id])
            ->actingAs($user)
            ->get(route('admin.user-system.users'));

        $response->assertForbidden();
    }

    public function test_expired_or_locked_assignments_do_not_grant_permissions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = $this->roleWithPermission('users.roles.view');
        $user->roles()->attach($role->id, [
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now()->subDay(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse($user->fresh()->hasPermission('users.roles.view'));

        $user->update(['locked_at' => now()]);
        $user->roles()->updateExistingPivot($role->id, ['expires_at' => now()->addDay()]);
        $this->assertFalse($user->fresh()->hasPermission('users.roles.view'));
    }

    private function roleWithPermission(string $permissionName): Role
    {
        $permission = Permission::create([
            'name' => $permissionName,
            'group' => 'Users & Roles',
            'module' => 'Users & Roles',
            'action' => str_ends_with($permissionName, '.view') ? 'view' : 'edit',
        ]);
        $role = Role::create([
            'name' => 'Batch 2 Role '.uniqid(),
            'label' => 'Batch 2 Role',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission->id, ['access_level' => 'view']);

        return $role;
    }
}
