<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminResourceActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_resource_index_exposes_real_action_menu_and_trash_view(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $record = AdminRecord::create([
            'module' => 'online-sales',
            'title' => 'Action contract record',
            'reference' => 'ACTION-001',
            'status' => 'active',
            'data' => ['notes' => 'Action coverage'],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.resource', 'online-sales'));

        $response->assertOk()
            ->assertSee('data-admin-action-menu', false)
            ->assertSee(route('admin.resource.show', ['online-sales', $record->id]), false)
            ->assertSee(route('admin.resource.duplicate', ['online-sales', $record->id]), false)
            ->assertSee(route('admin.resource.archive', ['online-sales', $record->id]), false)
            ->assertSee(route('admin.resource.trash', ['online-sales', $record->id]), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('action="#"', false);

        $this->actingAs($admin)
            ->post(route('admin.resource.trash', ['online-sales', $record->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('admin_records', ['id' => $record->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'online-sales.trashed']);

        $this->actingAs($admin)
            ->get(route('admin.resource', ['online-sales', 'tab' => 'trash']))
            ->assertOk()
            ->assertSee('Action contract record', false)
            ->assertSee(route('admin.resource.restore', ['online-sales', $record->id]), false)
            ->assertSee(route('admin.resource.permanent-destroy', ['online-sales', $record->id]), false);
    }

    public function test_generic_resource_actions_duplicate_archive_restore_and_permanently_delete_with_audit_trail(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $record = AdminRecord::create([
            'module' => 'reports',
            'title' => 'Weekly report',
            'reference' => 'REPORT-001',
            'status' => 'active',
            'amount' => 125.50,
            'data' => ['notes' => 'Weekly notes'],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.resource.duplicate', ['reports', $record->id]))
            ->assertRedirect();

        $copy = AdminRecord::query()
            ->where('module', 'reports')
            ->where('reference', 'REPORT-001-COPY')
            ->firstOrFail();
        $this->assertSame('Copy of Weekly report', $copy->title);
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($record->public_uuid, $copy->public_uuid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.duplicated']);

        $this->actingAs($admin)
            ->post(route('admin.resource.archive', ['reports', $record->id]))
            ->assertRedirect();
        $this->assertSame('archived', $record->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.archived']);

        $this->actingAs($admin)
            ->post(route('admin.resource.trash', ['reports', $record->id]))
            ->assertRedirect();
        $this->assertSoftDeleted('admin_records', ['id' => $record->id]);

        $this->actingAs($admin)
            ->post(route('admin.resource.restore', ['reports', $record->id]))
            ->assertRedirect();
        $this->assertDatabaseHas('admin_records', ['id' => $record->id, 'deleted_at' => null, 'status' => 'archived']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.restored']);

        $this->actingAs($admin)
            ->post(route('admin.resource.trash', ['reports', $record->id]))
            ->assertRedirect();
        $this->actingAs($admin)
            ->delete(route('admin.resource.permanent-destroy', ['reports', $record->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('admin_records', ['id' => $record->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.permanently_deleted']);
    }

    public function test_generic_resource_details_are_module_scoped_and_edit_is_server_first(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $record = AdminRecord::create([
            'module' => 'online-sales',
            'title' => 'Editable record',
            'reference' => 'EDIT-001',
            'status' => 'active',
            'data' => ['notes' => 'Before'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.resource.show', ['online-sales', $record->id]))
            ->assertOk()
            ->assertSee('View &amp; edit', false)
            ->assertSee(route('admin.resource.update', ['online-sales', $record->id]), false)
            ->assertSee('SAVE CHANGES', false)
            ->assertDontSee('action="#"', false);

        $this->actingAs($admin)
            ->get(route('admin.resource.show', ['reports', $record->id]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.resource.update', ['online-sales', $record->id]), [
                'title' => 'Edited record',
                'reference' => 'EDIT-002',
                'status' => 'completed',
                'amount' => '10.00',
                'record_date' => '2026-09-13',
                'notes' => 'After',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admin_records', ['id' => $record->id, 'title' => 'Edited record', 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'online-sales.updated']);
    }
}
