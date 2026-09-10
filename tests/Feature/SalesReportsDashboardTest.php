<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_reports_dashboard_renders_inside_online_sales_shell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.sales-reports.dashboard'))
            ->assertOk()
            ->assertSeeText('Sales Reports')
            ->assertSeeText('Online Sales')
            ->assertSeeText('Total Sales (Net)')
            ->assertSee('/css/sales-reports-reference.css?v=20260910-1', false);
    }

    public function test_sales_report_uses_order_data_for_a_filtered_window(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Order::create([
            'user_id' => $admin->id, 'number' => 'ONL-REPORT-001', 'order_type' => 'online', 'status' => 'completed',
            'payment_status' => 'paid', 'fulfillment_status' => 'delivered', 'subtotal' => 125, 'shipping' => 0,
            'discount' => 5, 'total' => 120, 'currency' => 'EUR', 'email' => $admin->email,
            'payment_method' => 'Credit / Debit Card', 'shipping_address' => ['country_name' => 'Ireland'],
        ]);

        $this->actingAs($admin)->get(route('admin.sales-reports.dashboard', [
            'from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString(), 'category' => 'online',
        ]))->assertOk()->assertSeeText('€120.00');
    }

    public function test_sales_report_exports_and_saves_audited_views(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.sales-reports.export', ['format' => 'csv']))
            ->assertOk()->assertDownload();

        $this->actingAs($admin)->post(route('admin.sales-reports.views.store'), [
            'name' => 'Weekly Online Sales', 'category' => 'online', 'from' => '2025-04-01', 'to' => '2025-05-01',
            'channel' => 'all', 'customer_group' => 'all', 'payment' => 'all', 'fulfillment' => 'all', 'currency' => 'all',
        ])->assertRedirect();

        $view = AdminRecord::where('module', 'sales-report-views')->where('title', 'Weekly Online Sales')->firstOrFail();
        $this->assertNotEmpty($view->public_uuid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales-reports.view.created', 'subject_id' => $view->id]);
    }
}
