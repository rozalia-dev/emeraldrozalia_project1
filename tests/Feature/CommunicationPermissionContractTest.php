<?php

namespace Tests\Feature;

use App\Models\{Company, Conversation, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationPermissionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_view_permission_can_read_communication_center_but_cannot_write(): void
    {
        $company = Company::create([
            'name' => 'Communication Permission Tenant',
            'code' => 'COM-PERM-'.strtoupper(Str::random(6)),
            'country_code' => 'IE',
            'base_currency' => 'EUR',
            'default_locale' => 'en',
            'active' => true,
        ]);

        $permission = Permission::firstOrCreate(
            ['name' => 'communication.center.view'],
            ['group' => 'Communication Center'],
        );
        $role = Role::create([
            'name' => 'Communication Viewer '.Str::random(6),
            'label' => 'Communication Viewer',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission->id, ['access_level' => 'view']);

        $viewer = User::factory()->create([
            'is_admin' => false,
            'status' => 'active',
        ]);
        $company->users()->attach($viewer->id, ['role' => 'viewer', 'is_default' => true]);
        $viewer->roles()->attach($role->id, [
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $conversation = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'email',
            'contact' => 'tenant.viewer@example.test',
            'subject' => 'Tenant viewer conversation',
            'status' => 'open',
            'priority' => 'normal',
            'metadata' => ['source' => 'permission-contract'],
        ]);

        $this->actingAs($viewer)
            ->withSession(['company_id' => $company->id])
            ->get(route('admin.communication-center.page.inbox'))
            ->assertOk()
            ->assertSeeText('Tenant viewer conversation');

        $this->actingAs($viewer)
            ->withSession(['company_id' => $company->id])
            ->post(route('admin.communication-center.actions.store'), [
                'title' => 'Must be denied',
                'status' => 'pending',
                'idempotency_key' => 'communication-view-only-write',
            ])
            ->assertForbidden();

        $this->assertTrue($viewer->fresh()->can('view', $conversation));
        $this->assertFalse($viewer->fresh()->can('update', $conversation));
    }
}
