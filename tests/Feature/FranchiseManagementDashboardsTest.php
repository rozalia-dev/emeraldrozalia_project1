<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\FranchiseApplication;
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
}
