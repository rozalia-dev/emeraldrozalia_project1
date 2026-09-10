<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsReferenceSuiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_reference_pages_render_inside_the_existing_admin_shell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $pages = [
            'overview' => 'General Reporting / Report Center', 'approvals' => 'Approval Reports', 'custom' => 'Custom Reports',
            'history' => 'Report History', 'returns' => 'Returns & Refund Reports', 'roles' => 'User Roles & Permissions', 'scheduler' => 'Schedule Report',
        ];
        foreach ($pages as $page => $heading) {
            $this->actingAs($admin)->get(route('admin.reports.'.$page))->assertOk()->assertSeeText($heading)->assertSee('/css/reports-reference.css?v=20260910-1', false);
        }
    }

    public function test_custom_reports_runs_and_schedules_are_persisted_and_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.reports.custom.store'), [
            'name' => 'Franchise Sales Test', 'description' => 'Report test', 'report_type' => 'Tabular Report',
            'module' => 'Franchise Orders', 'data_source' => 'Operational Database', 'group_by' => 'Territory',
        ])->assertRedirect(route('admin.reports.custom'));
        $custom = AdminRecord::where('module', 'custom-reports')->where('title', 'Franchise Sales Test')->firstOrFail();
        $this->assertNotEmpty($custom->public_uuid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.custom.created', 'subject_id' => $custom->id]);

        $this->actingAs($admin)->post(route('admin.reports.run'), ['report' => 'Franchise Sales Test', 'module' => 'Franchise Orders'])->assertRedirect();
        $run = AdminRecord::where('module', 'report-runs')->where('title', 'Franchise Sales Test')->firstOrFail();
        $this->assertSame('success', $run->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.run', 'subject_id' => $run->id]);

        $this->actingAs($admin)->post(route('admin.reports.schedules.store'), [
            'name' => 'Weekly Franchise Test', 'report' => 'Franchise Sales Test', 'frequency' => 'Weekly', 'day' => 'Monday',
            'time' => '09:00', 'recipients' => 'admin@emeraldrozalia.ie', 'format' => 'PDF', 'active' => '1',
        ])->assertRedirect(route('admin.reports.scheduler'));
        $schedule = AdminRecord::where('module', 'report-schedules')->where('title', 'Weekly Franchise Test')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.reports.schedules.toggle', $schedule))->assertRedirect();
        $this->assertSame('paused', $schedule->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.schedule.toggled', 'subject_id' => $schedule->id]);
    }

    public function test_report_export_is_downloadable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.reports.export', ['name' => 'history']))->assertOk()->assertDownload();
    }
}
