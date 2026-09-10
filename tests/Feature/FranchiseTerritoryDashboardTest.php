<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchiseTerritoryDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_territory_reference_dashboard_renders_in_shared_admin_shell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/resource/franchise-territories')
            ->assertOk()
            ->assertSeeText('Territories')
            ->assertSeeText('TERRITORY COVERAGE MAP')
            ->assertSeeText('ASSIGNED TERRITORIES')
            ->assertSeeText('UNASSIGNED TERRITORIES')
            ->assertSeeText('Add Territory')
            ->assertSeeText('PAGE PURPOSE')
            ->assertSeeText('KEY FEATURES')
            ->assertSee('/css/franchise-territories.css?v=20260910-1', false)
            ->assertSee('id="admin-sidebar"', false);
    }

    public function test_territory_crud_filters_and_export_are_database_backed_and_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.franchise.territories.store'), [
            'reference' => 'TER-IE-01',
            'title' => 'Limerick City & County',
            'country' => 'Ireland',
            'region' => 'Munster',
            'status' => 'assigned',
            'assigned_to_name' => 'Emerald Caps Ltd.',
            'assigned_code' => 'FRAN-IE-001',
            'stores_count' => 6,
            'franchisees_count' => 3,
            'coverage' => 98,
            'notes' => 'Primary Limerick territory',
        ])->assertRedirect();

        $territory = AdminRecord::where('module', 'franchise-territories')->where('reference', 'TER-IE-01')->firstOrFail();
        $this->assertSame('assigned', $territory->status);
        $this->assertSame('Munster', data_get($territory->data, 'region'));
        $this->assertSame(6, data_get($territory->data, 'stores_count'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'franchise.territory.created']);

        $this->actingAs($admin)
            ->get('/admin/resource/franchise-territories?q=munster&tab=assigned')
            ->assertOk()
            ->assertSeeText('TER-IE-01')
            ->assertSeeText('Emerald Caps Ltd.')
            ->assertSeeText('Munster');

        $this->actingAs($admin)->patch(route('admin.franchise.territories.update', $territory), [
            'reference' => 'TER-IE-01',
            'title' => 'Limerick City & County',
            'country' => 'Ireland',
            'region' => 'Munster',
            'status' => 'unassigned',
            'assigned_to_name' => '',
            'assigned_code' => '',
            'stores_count' => 0,
            'franchisees_count' => 0,
            'coverage' => 85,
            'notes' => 'Available for reassignment',
        ])->assertRedirect();

        $this->assertSame('unassigned', $territory->fresh()->status);
        $this->actingAs($admin)->get(route('admin.franchise.territories.export'))->assertOk()->assertDownload();

        $this->actingAs($admin)->delete(route('admin.franchise.territories.destroy', $territory))->assertRedirect();
        $this->assertDatabaseMissing('admin_records', ['id' => $territory->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'franchise.territory.deleted']);
    }
}
