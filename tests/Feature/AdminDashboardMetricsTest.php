<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\CommunicationTemplate;
use App\Models\Conversation;
use App\Models\FranchiseApplication;
use App\Models\FranchiseStore;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_dashboard_uses_honest_zero_and_empty_states(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-dashboard-page', $html);
        $this->assertStringContainsString('data-dashboard-selected-period="this_month"', $html);
        $this->assertStringContainsString('data-dashboard-animated-number', $html);
        $this->assertStringContainsString('No paid orders in this period.', $html);
        $this->assertStringContainsString('No geocoded store locations yet.', $html);
        $this->assertStringContainsString('No recent activity has been recorded yet.', $html);
        $this->assertStringContainsString('Conversion Rate: 0.0%', $html);
        $this->assertStringContainsString(route('admin.franchise.page', ['section' => 'franchise-applications']), $html);
        $this->assertStringContainsString(route('admin.communication-center.page.communication-center'), $html);

        foreach (['FRO-2025-0456', '520,825.40', '98450.20', '18.3%', '21.4%', 'Berlin Store'] as $previewValue) {
            $this->assertStringNotContainsString($previewValue, $html, "Preview value leaked into the empty dashboard: {$previewValue}");
        }
    }

    public function test_dashboard_kpis_pipeline_map_performance_and_communication_use_live_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $newApplication = FranchiseApplication::create([
            'applicant_name' => 'Live New Partner',
            'email' => 'live-new@example.com',
            'territory' => 'Limerick, Ireland',
            'status' => 'new',
        ]);
        FranchiseApplication::create([
            'applicant_name' => 'Live Converted Partner',
            'email' => 'live-converted@example.com',
            'territory' => 'Cork, Ireland',
            'status' => 'converted',
        ]);
        $store = FranchiseStore::create([
            'franchise_application_id' => $newApplication->id,
            'code' => 'ER-B15-LIM',
            'name' => 'Limerick Flagship',
            'territory' => 'Limerick, Ireland',
            'address' => ['region' => 'Ireland', 'latitude' => 52.6638, 'longitude' => -8.6267],
            'status' => 'active',
            'opened_at' => today(),
        ]);
        Conversation::create(['channel' => 'chat', 'contact' => 'Live visitor', 'status' => 'open', 'subject' => 'Live question']);
        CommunicationTemplate::create(['channel' => 'email', 'name' => 'Live template', 'subject' => 'Live', 'body' => 'Live body', 'status' => 'draft']);
        Approval::create(['status' => 'pending', 'title' => 'Live approval']);
        Order::create([
            'user_id' => $admin->id,
            'number' => 'FR-B15-001',
            'order_type' => 'franchise',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 100,
            'shipping' => 0,
            'discount' => 0,
            'total' => 100,
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'exchange_rate' => 1,
            'email' => $admin->email,
            'shipping_address' => ['name' => 'Live Converted Partner', 'store' => $store->name],
        ]);

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-dashboard-value="2"', $html);
        $this->assertStringContainsString('data-dashboard-value="1"', $html);
        $this->assertStringContainsString('Conversion Rate: 50.0%', $html);
        $this->assertStringContainsString('FR-B15-001', $html);
        $this->assertStringContainsString('Limerick Flagship', $html);
        $this->assertStringContainsString(route('admin.franchise.page', ['section' => 'franchise-retail-stores', 'q' => $store->code]), $html);
        $this->assertStringContainsString('€100.00', $html);
        $this->assertStringContainsString('Email Templates', $html);
        $this->assertStringContainsString('Approval Pending', $html);
        $this->assertStringContainsString('data-dashboard-selected-period="this_month"', $html);
        $this->assertStringContainsString('name="period"', $html);

        foreach (['FRO-2025-0456', '520,825.40', '98450.20', 'Berlin Store'] as $previewValue) {
            $this->assertStringNotContainsString($previewValue, $html);
        }
    }

    public function test_dashboard_period_control_filters_performance_and_drilldown_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDay()->setTime(12, 0);
        Order::create([
            'user_id' => $admin->id,
            'number' => 'FR-B15-CURRENT',
            'order_type' => 'franchise',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 100,
            'shipping' => 0,
            'discount' => 0,
            'total' => 100,
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'exchange_rate' => 1,
            'email' => $admin->email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Order::create([
            'user_id' => $admin->id,
            'number' => 'FR-B15-LAST',
            'order_type' => 'franchise',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 200,
            'shipping' => 0,
            'discount' => 0,
            'total' => 200,
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'exchange_rate' => 1,
            'email' => $admin->email,
            'created_at' => $lastMonth,
            'updated_at' => $lastMonth,
        ]);

        $dateFrom = $lastMonth->copy()->startOfMonth()->toDateString();
        $dateTo = $lastMonth->copy()->endOfMonth()->toDateString();
        $html = $this->actingAs($admin)->get(route('admin.dashboard', ['period' => 'last_month']))->assertOk()->getContent();

        $this->assertStringContainsString('data-dashboard-selected-period="last_month"', $html);
        $this->assertStringContainsString('(Last Month)', $html);
        $this->assertStringContainsString('€200.00', $html);
        $this->assertStringContainsString(route('admin.order-master', ['type' => 'franchise', 'date_from' => $dateFrom, 'date_to' => $dateTo]), $html);
    }
}
