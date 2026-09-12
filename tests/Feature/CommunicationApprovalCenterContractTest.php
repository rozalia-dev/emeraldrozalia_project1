<?php

namespace Tests\Feature;

use App\Events\ApprovalRequestChanged;
use App\Models\AdminRecord;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CommunicationApprovalCenterContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_approval_center_uses_durable_tenant_scoped_requests(): void
    {
        $company = Company::create(['name' => 'Emerald Rozalia', 'code' => 'ER-APR-WEB', 'active' => true]);
        $otherCompany = Company::create(['name' => 'Other Company', 'code' => 'ER-APR-WEB-2', 'active' => true]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->post(route('admin.communication-center.approvals.store'), [
                'title' => 'Approve Limerick franchise application',
                'type' => 'Franchise Application',
                'description' => 'Review the franchise application and supporting documents.',
                'priority' => 'high',
                'requested_by' => 'Sarah Kelly',
                'entity' => 'Limerick, Ireland',
                'status' => 'pending',
                'record_date' => '2026-09-12',
            ])->assertRedirect();

        $approval = Approval::query()->firstOrFail();

        $this->assertSame($company->id, $approval->company_id);
        $this->assertSame('APR-'.strtoupper(substr(str_replace('-', '', $approval->uuid), 0, 10)), $approval->reference);
        $this->assertDatabaseMissing('admin_records', ['module' => 'approval-center']);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->get('/admin/resource/approval-center?q=Limerick')
            ->assertOk()
            ->assertSeeText('Approve Limerick franchise application')
            ->assertSeeText($approval->reference)
            ->assertSeeText('Pending');

        $this->withSession(['company_id' => $otherCompany->id])
            ->actingAs($admin)
            ->get('/admin/resource/approval-center?q=Limerick')
            ->assertOk()
            ->assertDontSeeText('Approve Limerick franchise application');
    }

    public function test_approval_api_is_uuid_keyed_idempotent_versioned_audited_and_tenant_scoped(): void
    {
        Event::fake([ApprovalRequestChanged::class]);
        $company = Company::create(['name' => 'Emerald Rozalia', 'code' => 'ER-APR-API', 'active' => true]);
        $otherCompany = Company::create(['name' => 'Other Company', 'code' => 'ER-APR-API-2', 'active' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = [
            'title' => 'Approve bulk order discount',
            'type' => 'Bulk Order',
            'description' => 'Internal decision details must not be copied into audit snapshots.',
            'priority' => 'urgent',
            'requested_by' => 'Wholesale Team',
            'entity' => 'ORD-API-001',
            'status' => 'pending',
        ];

        $create = $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->withHeader('Idempotency-Key', 'approval-contract-001')
            ->postJson(route('api.v1.communication.approvals.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.type', 'Bulk Order')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonMissingPath('data.id');

        $uuid = $create->json('data.uuid');
        $this->assertNotEmpty($uuid);
        $this->assertSame(1, Approval::query()->count());
        Event::assertDispatched(ApprovalRequestChanged::class, fn (ApprovalRequestChanged $event): bool => $event->action === 'created' && $event->approval->uuid === $uuid);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->withHeader('Idempotency-Key', 'approval-contract-001')
            ->postJson(route('api.v1.communication.approvals.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->withHeader('Idempotency-Key', 'approval-contract-001')
            ->postJson(route('api.v1.communication.approvals.store'), array_replace($payload, ['title' => 'Different request']))
            ->assertStatus(409);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.approvals.index', ['q' => 'ORD-API-001']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $uuid)
            ->assertJsonMissingPath('data.0.id');

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->postJson(route('api.v1.communication.approvals.action', ['approval' => $uuid, 'action' => 'approve']), [
                'decision_note' => 'Approved after review.',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.decision.note', 'Approved after review.')
            ->assertJsonMissingPath('data.id');
        Event::assertDispatched(ApprovalRequestChanged::class, fn (ApprovalRequestChanged $event): bool => $event->action === 'approve' && $event->approval->uuid === $uuid);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->patchJson(route('api.v1.communication.approvals.update', ['approval' => $uuid]), [
                'title' => 'Stale approval update',
                'expected_version' => 1,
            ])
            ->assertStatus(409);

        $audit = $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.approvals.audit', ['approval' => $uuid]))
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($audit);
        foreach ($audit as $entry) {
            $this->assertArrayNotHasKey('description', $entry['before'] ?? []);
            $this->assertArrayNotHasKey('description', $entry['after'] ?? []);
            $this->assertArrayNotHasKey('decision_note', $entry['before'] ?? []);
            $this->assertArrayNotHasKey('decision_note', $entry['after'] ?? []);
        }
        $this->assertDatabaseHas('audit_logs', ['subject_uuid' => $uuid, 'action' => 'communication.approval.approve']);

        $this->withSession(['company_id' => $otherCompany->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.approvals.show', ['approval' => $uuid]))
            ->assertNotFound();

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->deleteJson(route('api.v1.communication.approvals.destroy', ['approval' => $uuid]))
            ->assertNoContent();

        $this->assertSoftDeleted('approvals', ['uuid' => $uuid]);
        $this->assertDatabaseHas('audit_logs', ['subject_uuid' => $uuid, 'action' => 'communication.approval.deleted']);
    }

    public function test_legacy_approval_record_route_creates_a_compatibility_shadow(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.store', 'approval-center'), [
                'title' => 'Legacy approval bridge',
                'type' => 'Franchise Application',
                'priority' => 'high',
                'requested_by' => 'Sarah Kelly',
                'status' => 'pending',
                'record_date' => '2026-09-12',
            ])
            ->assertRedirect();

        $shadow = AdminRecord::query()->where('module', 'approval-center')->firstOrFail();
        $approvalUuid = data_get($shadow->data, 'approval_uuid');

        $this->assertNotEmpty($approvalUuid);
        $this->assertDatabaseHas('approvals', ['uuid' => $approvalUuid, 'status' => 'pending']);

        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.action', ['approval-center', $shadow, 'approve']))
            ->assertRedirect();

        $this->assertSame('approved', $shadow->fresh()->status);
        $this->assertSame('approved', Approval::where('uuid', $approvalUuid)->firstOrFail()->status);
    }
}
