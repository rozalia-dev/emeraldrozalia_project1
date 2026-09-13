<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\FranchiseApplication;
use App\Models\FranchiseMilestone;
use App\Models\FranchiseStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchiseManagementDashboardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_franchise_management_reference_pages_render_inside_existing_admin_shell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $pages = [
            'franchise-dashboard' => 'Franchise Dashboard',
            'franchise-applications' => 'Applications & Leads',
            'franchise-territories' => 'Territories',
            'franchise-agreements' => 'Agreements',
            'franchisees' => 'Franchisees',
            'franchise-retail-stores' => 'Franchise Retail Stores',
            'store-setup' => 'Store Setup',
            'training-documents' => 'Training & Documents',
            'marketing-assets' => 'Marketing Assets',
            'performance-targets' => 'Performance & Targets',
            'renewals' => 'Renewals',
            'franchise-reports' => 'Franchise Reports',
            'data-management' => 'Data Management',
        ];
        foreach ($pages as $slug => $heading) {
            $this->actingAs($admin)->get('/admin/resource/'.$slug)->assertOk()->assertSeeText($heading)->assertSeeText('PAGE PURPOSE')->assertSeeText('KEY FEATURES')->assertSee('/css/franchise-management.css?v=20260910-1', false);
        }
    }

    public function test_application_crud_is_database_backed_and_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.franchise.store', 'franchise-applications'), [
            'applicant_name' => 'Emerald Test Partner','email' => 'partner@example.com','phone' => '0890000000','territory' => 'Limerick, Ireland','preferred_location' => 'Limerick City','investment_range' => '€50k–€100k','status' => 'under-review','assigned_to' => $admin->id,'source' => 'Website',
        ])->assertRedirect();
        $application = FranchiseApplication::where('email', 'partner@example.com')->firstOrFail();
        $this->assertSame('under-review', $application->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'franchise.application.created']);
        $this->actingAs($admin)->patch(route('admin.franchise.update', ['franchise-applications', $application->id]), [
            'applicant_name' => 'Emerald Test Partner','email' => 'partner@example.com','phone' => '0890000000','territory' => 'Limerick, Ireland','preferred_location' => 'Limerick City','investment_range' => '€50k–€100k','status' => 'approved','assigned_to' => $admin->id,'source' => 'Website',
        ])->assertRedirect();
        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_agreements_use_shared_admin_records_and_filtered_csv_export(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.franchise.store', 'franchise-agreements'), [
            'title' => 'Emerald Caps Ltd.','reference' => 'AGR-2026-0001','secondary' => 'Primary Contact','territory' => 'Limerick City & County','record_type' => 'Master Franchise','source' => 'Direct','assigned_to_name' => 'Admin User','status' => 'active','amount' => 48750,'record_date' => '2026-09-10','end_date' => '2028-09-10','value' => 'Signed','growth' => '+12.4%','notes' => 'Reference agreement',
        ])->assertRedirect();
        $record = AdminRecord::where('module', 'franchise-agreements')->where('reference', 'AGR-2026-0001')->firstOrFail();
        $this->assertSame('Limerick City & County', data_get($record->data, 'territory'));
        $this->actingAs($admin)->get('/admin/resource/franchise-agreements?q=Emerald')->assertOk()->assertSeeText('AGR-2026-0001')->assertSeeText('Limerick City & County');
        $this->actingAs($admin)->get(route('admin.franchise.export', ['section' => 'franchise-agreements', 'q' => 'Emerald']))->assertOk()->assertDownload();
    }

    public function test_franchise_dashboard_uses_live_cards_and_store_setup_drilldowns(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $application = FranchiseApplication::create([
            'applicant_name' => 'Limerick Retail Partner',
            'email' => 'limerick.partner@example.com',
            'territory' => 'Limerick, Ireland',
            'status' => 'approved',
            'data' => ['source' => 'Website'],
        ]);
        FranchiseMilestone::create([
            'franchise_application_id' => $application->id,
            'type' => 'store_setup',
            'status' => 'in-progress',
            'due_on' => today()->addDays(7),
            'data' => [
                'store_name' => 'Limerick Flagship',
                'territory' => 'Limerick, Ireland',
                'owner_name' => 'Limerick Retail Partner',
                'checklist' => [
                    'territory_approved' => true,
                    'agreement_signed' => true,
                    'training_complete' => false,
                    'premises_ready' => false,
                    'opening_order_ready' => false,
                    'launch_approved' => false,
                ],
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.franchise.dashboard'));

        $response->assertOk()
            ->assertSeeText(['Franchise lifecycle', 'OPERATIONS AT A GLANCE', 'Readiness milestones', 'Limerick Flagship', 'Store Setup', 'KEY FEATURES'])
            ->assertSee(route('admin.franchise.store-setup'), false)
            ->assertSee(route('admin.franchise.page', ['section' => 'franchise-applications']), false)
            ->assertSee('href="'.route('admin.franchise.page', ['section' => 'franchise-applications', 'tab' => 'approved']).'"', false);
    }

    public function test_store_setup_is_a_database_backed_checklist_with_actions_and_recoverable_trash(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $application = FranchiseApplication::create([
            'applicant_name' => 'Emerald Setup Partner',
            'email' => 'setup.partner@example.com',
            'territory' => 'Munster, Ireland',
            'status' => 'approved',
        ]);
        $checklist = [
            'territory_approved' => '1',
            'agreement_signed' => '1',
            'training_complete' => '1',
            'premises_ready' => '1',
            'opening_order_ready' => '1',
            'launch_approved' => '1',
        ];

        $this->actingAs($admin)->post(route('admin.franchise.store-setup.store'), [
            'application_uuid' => $application->uuid,
            'store_name' => 'Munster Emerald Store',
            'store_code' => 'MUN-001',
            'territory' => 'Munster, Ireland',
            'owner_name' => 'Emerald Setup Partner',
            'manager_name' => 'Store Manager',
            'due_on' => today()->addDays(14)->format('Y-m-d'),
            'status' => 'pending',
            'evidence_reference' => 'AGREEMENT-2026-001',
            'notes' => 'Opening checklist',
            'checklist' => $checklist,
        ])->assertRedirect();

        $milestone = FranchiseMilestone::query()->where('type', 'store_setup')->firstOrFail();
        $this->assertSame('pending', $milestone->status);
        $this->assertSame('Munster Emerald Store', data_get($milestone->data, 'store_name'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'franchise.store_setup.created']);

        $this->actingAs($admin)->post(route('admin.franchise.store-setup.action', ['milestone' => $milestone->uuid, 'action' => 'complete']))
            ->assertStatus(422);
        $this->assertSame('pending', $milestone->fresh()->status);

        $this->actingAs($admin)->post(route('admin.franchise.store-setup.action', ['milestone' => $milestone->uuid, 'action' => 'start']))
            ->assertRedirect();
        $this->assertSame('in-progress', $milestone->fresh()->status);

        $this->actingAs($admin)->post(route('admin.franchise.store-setup.action', ['milestone' => $milestone->uuid, 'action' => 'complete']))
            ->assertRedirect();
        $this->assertSame('complete', $milestone->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'franchise.store_setup.complete']);

        $this->actingAs($admin)->post(route('admin.franchise.store-setup.action', ['milestone' => $milestone->uuid, 'action' => 'activate']))
            ->assertRedirect();
        $store = FranchiseStore::query()->where('code', 'MUN-001')->firstOrFail();
        $this->assertSame('active', $store->status);
        $this->assertSame($application->id, $store->franchise_application_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'franchise.store_setup.activate']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'franchise.store.activated']);

        $this->actingAs($admin)->delete(route('admin.franchise.store-setup.trash', ['milestone' => $milestone->uuid]))
            ->assertRedirect();
        $this->assertSoftDeleted('franchise_milestones', ['id' => $milestone->id]);

        $this->actingAs($admin)->post(route('admin.franchise.store-setup.restore', ['milestone' => $milestone->uuid]))
            ->assertRedirect();
        $this->assertDatabaseHas('franchise_milestones', ['id' => $milestone->id, 'deleted_at' => null]);
    }

    public function test_application_pipeline_actions_are_state_aware_and_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $application = FranchiseApplication::create([
            'applicant_name' => 'Pipeline Partner',
            'email' => 'pipeline.partner@example.com',
            'territory' => 'Limerick, Ireland',
            'status' => 'new',
        ]);

        $this->actingAs($admin)->get(route('admin.franchise.page', ['section' => 'franchise-applications']))
            ->assertOk()
            ->assertSeeText('Start review')
            ->assertSee(route('admin.franchise.application.action', ['application' => $application->uuid, 'action' => 'start-review']), false);

        $this->actingAs($admin)->post(route('admin.franchise.application.action', ['application' => $application->uuid, 'action' => 'start-review']))
            ->assertRedirect();
        $this->assertSame('under-review', $application->fresh()->status);

        $this->actingAs($admin)->post(route('admin.franchise.application.action', ['application' => $application->uuid, 'action' => 'approve']))
            ->assertRedirect();
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'franchise.application.approve']);
    }
}
