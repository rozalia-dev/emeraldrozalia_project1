<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\Conversation;
use App\Models\Order;
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

    public function test_order_communication_and_customer_dashboards_render_from_the_shared_reports_menu(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $pages = [
            'order' => ['Order Reports', 'Online Orders', 'Orders by Type'],
            'communication' => ['Communication Reports', 'Conversations by Channel', 'SLA Performance'],
            'customer' => ['Customer Reports', 'Customers by Segment', 'Top Customers by Revenue'],
        ];

        foreach ($pages as $page => $content) {
            $this->actingAs($admin)->get(route('admin.reports.'.$page))
                ->assertOk()
                ->assertSee($content, false)
                ->assertSee('Order Reports', false)
                ->assertSee('Communication Reports', false)
                ->assertSee('Customer Reports', false)
                ->assertSee('/css/reports-analytics-reference.css?v=20260912-1', false)
                ->assertSee('/js/reports-analytics-reference.js?v=20260912-1', false)
                ->assertSee('data-report-analytics-root', false);
        }
    }

    public function test_report_dashboards_use_postgresql_records_when_the_reference_window_is_filtered(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Live Report Customer', 'is_admin' => false]);
        $order = Order::create([
            'user_id' => $customer->id, 'number' => 'LIVE-REPORT-001', 'order_type' => 'online', 'status' => 'delivered',
            'payment_status' => 'paid', 'fulfillment_status' => 'delivered', 'subtotal' => 321.50, 'shipping' => 0,
            'discount' => 0, 'total' => 321.50, 'currency' => 'EUR', 'email' => $customer->email,
            'shipping_address' => ['country_name' => 'Ireland', 'channel' => 'website'],
        ]);
        Conversation::create([
            'channel' => 'whatsapp', 'contact' => $customer->email, 'subject' => 'Live report conversation',
            'status' => 'open', 'metadata' => ['name' => 'Live Report Customer', 'category' => 'Order Support'],
        ]);

        $window = ['from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString()];
        $this->actingAs($admin)->get(route('admin.reports.order', $window + ['q' => $order->number]))
            ->assertOk()->assertSee('€321.50', false)->assertSee('Live PostgreSQL data', false);
        $this->actingAs($admin)->get(route('admin.reports.communication', $window + ['q' => 'Live report']))
            ->assertOk()->assertSee('Live report conversation', false)->assertSee('Live PostgreSQL data', false);
        $this->actingAs($admin)->get(route('admin.reports.customer', $window + ['q' => 'Live Report Customer']))
            ->assertOk()->assertSee('Live Report Customer', false)->assertSee('Live PostgreSQL data', false);
        $this->actingAs($admin)->get(route('admin.reports.export', ['name' => 'order-reports', 'analytics' => 'order'] + $window))
            ->assertOk()->assertDownload();
    }

    public function test_report_dashboards_show_explicit_empty_states_without_reference_fixtures(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $window = ['from' => '2040-01-01', 'to' => '2040-01-31', 'q' => 'no-records-for-this-window'];

        $this->actingAs($admin)->get(route('admin.reports.order', $window))
            ->assertOk()->assertSeeText('No matching records')->assertSeeText('No orders found for the selected filters.')
            ->assertDontSee('3,856', false)->assertDontSee('€2,845,671.00', false);

        $this->actingAs($admin)->get(route('admin.reports.communication', $window))
            ->assertOk()->assertSeeText('No matching records')->assertSeeText('No conversations found for the selected filters.')
            ->assertDontSee('12,842', false)->assertDontSee('92.68%', false);

        $this->actingAs($admin)->get(route('admin.reports.customer', $window))
            ->assertOk()->assertSeeText('No matching records')->assertSeeText('No customers found for the selected filters.')
            ->assertDontSee('18,742', false)->assertDontSee('€2.84M', false);

        $this->actingAs($admin)->get(route('admin.sales-reports.dashboard', $window))
            ->assertOk()->assertSeeText('No matching orders')->assertSeeText('No orders found for the selected filters.')
            ->assertDontSee('€1,052,003.20', false)->assertDontSee('€82,765.40', false);
    }

    public function test_communication_metrics_use_recorded_metadata_when_available(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Conversation::create([
            'channel' => 'email', 'contact' => 'metrics@example.ie', 'subject' => 'Recorded metrics', 'status' => 'closed',
            'metadata' => ['first_response_seconds' => 120, 'resolution_seconds' => 3600, 'sla_met' => true, 'csat' => 4],
        ]);

        $this->actingAs($admin)->get(route('admin.reports.communication', [
            'from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString(), 'q' => 'Recorded metrics',
        ]))->assertOk()->assertSeeText('2m 0s')->assertSeeText('1h 0m')->assertSeeText('100.00%')->assertSeeText('4.00 / 5');
    }
}
