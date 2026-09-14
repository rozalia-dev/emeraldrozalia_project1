<?php

namespace Tests\Feature;

use App\Events\FranchiseStoreLifecycleChanged;
use App\Models\{AuditLog, Company, FranchiseStore, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class FranchiseStoreLifecycleContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_lifecycle_is_state_aware_idempotent_audited_and_automation_ready(): void
    {
        Event::fake([FranchiseStoreLifecycleChanged::class]);

        $admin = User::factory()->create(['is_admin' => true]);
        $store = FranchiseStore::create([
            'code' => 'LIFECYCLE-001',
            'name' => 'Lifecycle Store',
            'territory' => 'Limerick, Ireland',
            'status' => 'active',
            'address' => ['franchisee_name' => 'Lifecycle Partner'],
            'version' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.franchise.store.action', ['store' => $store->uuid, 'action' => 'suspend']), [
                'idempotency_key' => 'store-lifecycle-suspend-001',
                'expected_version' => 1,
                'reason' => 'Temporary compliance review.',
            ])
            ->assertRedirect();

        $this->assertSame('suspended', $store->fresh()->status);
        $this->assertSame(2, $store->fresh()->version);
        $this->assertDatabaseHas('franchise_store_actions', [
            'franchise_store_id' => $store->id,
            'action' => 'suspend',
            'from_status' => 'active',
            'to_status' => 'suspended',
            'idempotency_key' => 'store-lifecycle-suspend-001',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'franchise.store.status_changed']);
        Event::assertDispatched(FranchiseStoreLifecycleChanged::class);

        $this->actingAs($admin)
            ->post(route('admin.franchise.store.action', ['store' => $store->uuid, 'action' => 'suspend']), [
                'idempotency_key' => 'store-lifecycle-suspend-001',
                'expected_version' => 1,
                'reason' => 'Temporary compliance review.',
            ])
            ->assertRedirect();

        $this->assertSame(1, \App\Models\FranchiseStoreAction::query()->where('franchise_store_id', $store->id)->count());
        $this->assertSame(2, $store->fresh()->version);

        $this->actingAs($admin)
            ->post(route('admin.franchise.store.action', ['store' => $store->uuid, 'action' => 'resume']), [
                'idempotency_key' => 'store-lifecycle-resume-001',
                'expected_version' => 1,
            ])
            ->assertStatus(409);
        $this->assertSame('suspended', $store->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.franchise.store.action', ['store' => $store->uuid, 'action' => 'resume']), [
                'idempotency_key' => 'store-lifecycle-resume-001',
                'expected_version' => 2,
            ])
            ->assertRedirect();
        $this->assertSame('active', $store->fresh()->status);
        $this->assertSame(3, $store->fresh()->version);

        $this->actingAs($admin)
            ->post(route('admin.franchise.store.action', ['store' => $store->uuid, 'action' => 'terminate']), [
                'idempotency_key' => 'store-lifecycle-terminate-001',
                'expected_version' => 3,
                'reason' => 'Contract ended after review.',
            ])
            ->assertRedirect();

        $this->assertSame('terminated', $store->fresh()->status);
        $this->assertSame(4, $store->fresh()->version);
        $this->assertSame(3, \App\Models\FranchiseStoreAction::query()->where('franchise_store_id', $store->id)->count());
    }

    public function test_view_only_franchise_store_user_can_read_but_cannot_change_lifecycle(): void
    {
        $company = Company::create([
            'name' => 'Lifecycle Tenant',
            'code' => 'LIFE-'.strtoupper(Str::random(6)),
            'country_code' => 'IE',
            'base_currency' => 'EUR',
            'default_locale' => 'en',
            'active' => true,
        ]);
        $permission = Permission::firstOrCreate(
            ['name' => 'franchise.retail.stores.view'],
            ['group' => 'Franchise Retail Stores'],
        );
        $role = Role::create([
            'name' => 'Lifecycle Viewer '.Str::random(6),
            'label' => 'Lifecycle Viewer',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission->id, ['access_level' => 'view']);
        $viewer = User::factory()->create(['is_admin' => false, 'status' => 'active']);
        $company->users()->attach($viewer->id, ['role' => 'viewer', 'is_default' => true]);
        $viewer->roles()->attach($role->id, [
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);
        $store = FranchiseStore::create([
            'company_id' => $company->id,
            'code' => 'LIFECYCLE-002',
            'name' => 'Tenant Store',
            'territory' => 'Cork, Ireland',
            'status' => 'active',
            'address' => [],
        ]);

        $this->actingAs($viewer)
            ->withSession(['company_id' => $company->id])
            ->get(route('admin.franchise.page', ['section' => 'franchise-retail-stores']))
            ->assertOk()
            ->assertSeeText('Tenant Store');

        $this->actingAs($viewer)
            ->withSession(['company_id' => $company->id])
            ->post(route('admin.franchise.store.action', ['store' => $store->uuid, 'action' => 'suspend']), [
                'idempotency_key' => 'store-lifecycle-view-only',
                'expected_version' => 1,
                'reason' => 'Should be denied.',
            ])
            ->assertForbidden();

        $this->assertTrue($viewer->fresh()->can('view', $store));
        $this->assertFalse($viewer->fresh()->can('transition', $store));
    }
}
