<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationCenterReferenceSuiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_communication_center_reference_page_renders_in_the_existing_admin_shell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $pages = [
            'communication-center' => 'Communication Center',
            'inbox' => 'Inbox',
            'chat-24-7' => 'Chat 24/7',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'email-templates' => 'Email Templates',
            'approval-center' => 'Approval Center',
            'action-follow-ups' => 'Action / Follow-ups',
            'alerts-notifications' => 'Alerts & Notifications',
            'communication-reports' => 'Communication Reports',
            'communication-history' => 'Communication History / Audit Log',
        ];

        foreach ($pages as $slug => $heading) {
            $this->actingAs($admin)
                ->get('/admin/resource/'.$slug)
                ->assertOk()
                ->assertSeeText($heading)
                ->assertSee('/css/communication-center-reference.css?v=20260910-1', false)
                ->assertSee('id="admin-sidebar"', false);
        }
    }

    public function test_inbox_whatsapp_and_email_are_backed_by_shared_conversations_and_replies(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $conversation = Conversation::create([
            'channel' => 'whatsapp',
            'contact' => '+353 87 654 3210',
            'subject' => 'Where is my order?',
            'priority' => 'high',
            'status' => 'open',
            'assigned_to' => $admin->id,
            'metadata' => [
                'name' => 'Emma Walsh',
                'location' => 'Limerick, Ireland',
                'order_id' => 'ORD-TEST-001',
                'tracking_number' => 'DPD1234567890',
            ],
        ]);
        $conversation->messages()->create([
            'direction' => 'inbound',
            'body' => 'I need help tracking my order.',
            'delivery_status' => 'stored',
            'sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/resource/whatsapp?conversation='.$conversation->id)
            ->assertOk()
            ->assertSeeText('Emma Walsh')
            ->assertSeeText('Where is my order?')
            ->assertSeeText('DPD1234567890')
            ->assertSeeText('I need help tracking my order.');

        $this->actingAs($admin)
            ->get('/admin/resource/inbox?conversation='.$conversation->id)
            ->assertOk()
            ->assertSeeText('Emma Walsh');

        $this->actingAs($admin)
            ->post(route('admin.communication.message.store', $conversation), [
                'body' => 'Your order has shipped today.',
            ])->assertRedirect();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => 'Your order has shipped today.',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.communication.update', $conversation), [
                'status' => 'pending',
                'priority' => 'urgent',
                'assigned_to' => $admin->id,
                'follow_up_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])->assertRedirect();

        $this->assertSame('pending', $conversation->fresh()->status);
        $this->assertSame('urgent', $conversation->fresh()->priority);
    }

    public function test_email_templates_are_database_backed_editable_duplicable_and_exportable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.store', 'email-templates'), [
                'title' => 'Order Confirmation',
                'subject' => 'Your order is confirmed',
                'category' => 'Order',
                'channel' => 'Email',
                'language' => 'English',
                'body' => 'Thank you for your order.',
                'status' => 'active',
                'record_date' => '2026-09-10',
            ])->assertRedirect();

        $record = AdminRecord::query()
            ->where('module', 'email-templates')
            ->where('title', 'Order Confirmation')
            ->firstOrFail();

        $this->assertStringStartsWith('TPL-', (string) $record->reference);
        $this->assertSame('Your order is confirmed', data_get($record->data, 'subject'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'communication.email-templates.created']);

        $this->actingAs($admin)
            ->patch(route('admin.communication-center.record.update', ['email-templates', $record]), [
                'title' => 'Order Confirmation Updated',
                'reference' => $record->reference,
                'subject' => 'Your order has been confirmed',
                'category' => 'Order',
                'channel' => 'Email',
                'language' => 'English',
                'body' => 'Thank you for shopping with Emerald Rozalia.',
                'status' => 'active',
                'record_date' => '2026-09-10',
            ])->assertRedirect();

        $this->assertSame('Order Confirmation Updated', $record->fresh()->title);

        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.action', ['email-templates', $record, 'duplicate']))
            ->assertRedirect();

        $this->assertSame(2, AdminRecord::query()->where('module', 'email-templates')->count());

        $this->actingAs($admin)
            ->get(route('admin.communication-center.export', ['section' => 'email-templates']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_approval_actions_followups_and_alerts_support_operational_workflows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.store', 'approval-center'), [
                'title' => 'New Franchise Application — Limerick',
                'type' => 'Franchise Application',
                'priority' => 'high',
                'requested_by' => 'Sarah Kelly',
                'entity' => 'Limerick, Ireland',
                'status' => 'pending',
                'record_date' => '2026-09-10',
            ])->assertRedirect();

        $approval = AdminRecord::query()->where('module', 'approval-center')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.action', ['approval-center', $approval, 'approve']))
            ->assertRedirect();
        $this->assertSame('approved', $approval->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.store', 'action-follow-ups'), [
                'title' => 'Follow up with franchise documents',
                'category' => 'Franchise',
                'priority' => 'high',
                'assigned_to_name' => 'Sarah Kelly',
                'entity' => 'FRAN-TEST-001',
                'source' => 'Approval Center',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'status' => 'pending',
                'record_date' => '2026-09-10',
            ])->assertRedirect();

        $action = AdminRecord::query()->where('module', 'action-follow-ups')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.action', ['action-follow-ups', $action, 'complete']))
            ->assertRedirect();
        $this->assertSame('completed', $action->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.store', 'alerts-notifications'), [
                'title' => 'Payment failed for order',
                'type' => 'Payment',
                'category' => 'Payment Failed',
                'severity' => 'critical',
                'assigned_to_name' => 'Finance Team',
                'source' => 'Payment Gateway',
                'entity' => 'ORD-TEST-001',
                'status' => 'unread',
                'record_date' => '2026-09-10',
            ])->assertRedirect();

        $alert = AdminRecord::query()->where('module', 'alerts-notifications')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.communication-center.record.action', ['alerts-notifications', $alert, 'acknowledge']))
            ->assertRedirect();
        $this->assertSame('acknowledged', $alert->fresh()->status);
    }

    public function test_reports_and_history_use_live_communication_and_audit_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $conversation = Conversation::create([
            'channel' => 'email',
            'contact' => 'customer@example.com',
            'subject' => 'Bulk order quotation request',
            'priority' => 'normal',
            'status' => 'closed',
            'assigned_to' => $admin->id,
            'metadata' => [
                'name' => 'Global Wholesale Inc.',
                'topic' => 'Bulk Orders / Quotation',
                'order_category' => 'Bulk Orders',
                'csat' => 4.8,
                'first_response_seconds' => 720,
                'resolution_seconds' => 5400,
            ],
        ]);
        $conversation->messages()->create([
            'user_id' => $admin->id,
            'direction' => 'outbound',
            'body' => 'Quotation sent.',
            'delivery_status' => 'stored',
            'sent_at' => now(),
        ]);

        AuditTrail::record('communication.report.tested', $conversation, null, $conversation->toArray());

        $this->actingAs($admin)
            ->get('/admin/resource/communication-reports')
            ->assertOk()
            ->assertSeeText('Bulk Orders / Quotation')
            ->assertSeeText('Bulk Orders')
            ->assertSeeText('Customer Satisfaction');

        $this->actingAs($admin)
            ->get('/admin/resource/communication-history')
            ->assertOk()
            ->assertSeeText('Communication Report Tested');

        $this->assertTrue(AuditLog::query()->where('action', 'communication.report.tested')->exists());
    }
}
