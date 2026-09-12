<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMasterDashboardsReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_dashboards_render_for_each_supplied_order_master(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach ([
            'online' => 'Online Orders', 'bulk' => 'Bulk Orders', 'buyer' => 'Buyer Orders',
            'franchise' => 'Franchise Orders', 'franchise_retail' => 'Franchise Retail Orders',
        ] as $type => $label) {
            $this->actingAs($admin)->get(route('admin.order-master', $type))
                ->assertOk()
                ->assertSee([$label, 'Orders in Progress', 'ORDER NOTIFICATIONS', 'QUICK ACTIONS', 'Order Status Overview'], false)
                ->assertSee('/css/orders-reference.css?v=20260912-1', false)
                ->assertSee('/js/orders-reference.js?v=20260912-1', false);
        }
    }

    public function test_category_dashboard_uses_postgresql_order_data_and_filters_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::create([
            'user_id' => $admin->id, 'number' => 'ONL-PG-ORD-001', 'order_type' => 'online', 'status' => 'completed',
            'payment_status' => 'paid', 'fulfillment_status' => 'delivered', 'subtotal' => 120, 'shipping' => 0,
            'discount' => 0, 'total' => 120, 'currency' => 'EUR', 'currency_code' => 'EUR', 'exchange_rate' => 1,
            'email' => $admin->email, 'phone' => '+353 89 000 0011', 'payment_method' => 'Visa',
            'shipping_address' => ['name' => 'PostgreSQL Customer', 'channel' => 'Website'],
        ]);

        $this->actingAs($admin)->get(route('admin.order-master', ['online', 'q' => $order->number]))
            ->assertOk()
            ->assertSee([$order->number, 'PostgreSQL Customer', '€120.00', 'Delivered', 'Website'], false)
            ->assertDontSee('ONL-250501-0001', false);
    }
}
