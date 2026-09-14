<?php

namespace Tests\Feature;

use App\Models\{Discount, Order, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountDashboardContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_discount_dashboard_never_renders_reference_coupons(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.discounts-coupons'));

        $response->assertOk()
            ->assertSee(['No live discount records have been created yet.', 'No discount types recorded.', 'No applied discount usage recorded.'], false)
            ->assertDontSee('Preview data is shown', false);

        foreach (['ER100OFF', 'FRANCHISE7', '156', '24,875', '€82,765.40', '€428,765.75', '12,540.30'] as $forbidden) {
            $response->assertDontSee($forbidden, false);
        }
    }

    public function test_dashboard_derives_coupon_usage_categories_and_impact_from_live_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Discount Customer']);
        $discount = Discount::create([
            'code' => 'LIVE10',
            'type' => 'percent',
            'value' => 10,
            'used' => 1,
            'is_active' => true,
            'metadata' => [
                'applies_to' => 'cart_subtotal',
                'order_categories' => ['online'],
                'customer_group' => 'new_customers',
            ],
        ]);
        $order = $customer->orders()->create([
            'number' => 'ORD-DISCOUNT-001',
            'order_type' => 'online',
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal' => 100,
            'shipping' => 0,
            'discount' => 10,
            'total' => 90,
            'currency' => 'EUR',
            'discount_code' => $discount->code,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.discounts-coupons', ['q' => 'LIVE10']));

        $response->assertOk()
            ->assertSee(['LIVE10', 'Cart Subtotal', 'Online Orders', 'New Customers', 'Unlimited / 1', '€10.00', '€90.00', 'Live database value'], false)
            ->assertDontSee('ER100OFF', false)
            ->assertDontSee('Preview data is shown', false);
        $this->assertSame('LIVE10', $order->fresh()->discount_code);

        $this->actingAs($admin)->post(route('admin.discounts-coupons.action', $discount), ['action' => 'pause'])
            ->assertRedirect();
        $this->assertFalse((bool) $discount->fresh()->is_active);

        $this->actingAs($admin)->post(route('admin.discounts-coupons.action', $discount), ['action' => 'delete'])
            ->assertRedirect();
        $this->assertSoftDeleted('discounts', ['id' => $discount->id]);
    }
}
