<?php

namespace Tests\Feature;

use App\Models\{Company, MaintenanceRun, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemMaintenanceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_records_real_check_results_and_audits_the_outcome(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.settings.action', 'run-maintenance'))
            ->assertRedirect();

        $run = MaintenanceRun::query()->latest('id')->firstOrFail();

        $this->assertSame('attention', $run->status);
        $this->assertNotNull($run->completed_at);
        $this->assertSame('passed', data_get($run->checks, 'database.status'));
        $this->assertSame('passed', data_get($run->checks, 'cache.status'));
        $this->assertSame('passed', data_get($run->checks, 'storage.status'));
        $this->assertSame('passed', data_get($run->checks, 'queue.status'));
        $this->assertSame('attention', data_get($run->checks, 'scheduler.status'));
        $this->assertSame('attention', data_get($run->checks, 'verified_backup.status'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.maintenance.completed']);

        $this->actingAs($admin)
            ->get(route('admin.settings.maintenance.status'))
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $run->uuid)
            ->assertJsonPath('data.0.status', 'attention')
            ->assertJsonPath('semantics', 'Application-level checks only. Database/file restore, worker heartbeats, and external provider handshakes remain separate release gates.');
    }

    public function test_maintenance_runs_are_tenant_scoped(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $companyA = Company::create([
            'name' => 'Maintenance A',
            'code' => 'maintenance-a',
            'country_code' => 'IE',
            'base_currency' => 'EUR',
            'default_locale' => 'en',
            'active' => true,
        ]);
        $companyB = Company::create([
            'name' => 'Maintenance B',
            'code' => 'maintenance-b',
            'country_code' => 'IE',
            'base_currency' => 'EUR',
            'default_locale' => 'en',
            'active' => true,
        ]);

        MaintenanceRun::create(['company_id' => $companyA->id, 'status' => 'passed', 'started_at' => now()]);
        MaintenanceRun::create(['company_id' => $companyB->id, 'status' => 'failed', 'started_at' => now()]);

        $this->actingAs($admin)->withSession(['company_id' => $companyA->id])
            ->get(route('admin.settings.maintenance.status'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'passed');
    }
}
