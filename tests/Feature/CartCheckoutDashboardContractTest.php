<?php

namespace Tests\Feature;

use App\Jobs\DeliverCommunicationMessage;
use App\Models\{AdminRecord, ConversationMessage, Order, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CartCheckoutDashboardContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_checkout_dashboard_never_renders_operational_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.cart-checkout', ['tab' => 'abandoned']));

        $response->assertOk()
            ->assertSee(['No live checkout activity recorded yet', 'No abandonment reasons recorded.', 'No device data recorded.', 'No payment preference data recorded.'], false);

        foreach (['Preview data is shown', 'CART-250501-00123', '1,856', '35.28%', '2,846', '2,512', '1,862', '1,346'] as $forbidden) {
            $response->assertDontSee($forbidden, false);
        }
    }

    public function test_dashboard_uses_live_carts_and_online_orders_for_metrics_and_filters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Live Checkout Customer']);
        $cart = AdminRecord::create([
            'module' => 'cart-checkout',
            'reference' => 'CART-LIVE-001',
            'title' => 'Live checkout cart',
            'status' => 'abandoned',
            'amount' => 125.50,
            'data' => [
                'customer' => 'Live Checkout Customer',
                'email' => 'live-cart@example.test',
                'source' => 'instagram',
                'device_type' => 'mobile',
                'abandonment_reason' => 'Shipping cost',
                'abandonment_step' => 'shipping_method',
                'shipping_method' => 'standard',
                'payment_method' => 'card',
                'currency' => 'EUR',
            ],
        ]);
        $order = $customer->orders()->create([
            'number' => 'ORD-LIVE-001',
            'order_type' => 'online',
            'status' => 'completed',
            'payment_status' => 'paid',
            'shipping_method' => 'standard',
            'payment_method' => 'card',
            'subtotal' => 90,
            'shipping' => 0,
            'discount' => 0,
            'total' => 90,
            'currency' => 'EUR',
        ]);

        $this->actingAs($admin)->get(route('admin.cart-checkout', ['q' => 'CART-LIVE-001']))
            ->assertOk()
            ->assertSee(['CART-LIVE-001', 'Live Checkout Customer', 'Instagram', 'Shipping Cost', 'No recovery outcomes recorded'], false)
            ->assertDontSee('CART-250501-00123', false);
        $this->actingAs($admin)->get(route('admin.cart-checkout', ['q' => $order->number]))
            ->assertOk()
            ->assertSee(['ORD-LIVE-001', 'EUR 90.00', 'Online checkout'], false);

        $this->actingAs($admin)->post(route('admin.cart-checkout.action', $cart), ['action' => 'restore'])
            ->assertRedirect();
        $this->assertSame('active', $cart->fresh()->status);

        $this->actingAs($admin)->post(route('admin.cart-checkout.action', $cart), ['action' => 'complete'])
            ->assertRedirect();
        $this->assertSame('completed', $cart->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.cart_checkout.restore', 'subject_id' => (string) $cart->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.cart_checkout.complete', 'subject_id' => (string) $cart->id]);
    }

    public function test_recovery_request_is_queued_once_and_recorded_with_delivery_state(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $cart = AdminRecord::create([
            'module' => 'cart-checkout',
            'reference' => 'CART-RECOVERY-001',
            'title' => 'Recovery candidate',
            'status' => 'abandoned',
            'amount' => 80,
            'data' => ['email' => 'recover@example.test'],
        ]);

        $route = route('admin.cart-checkout.action', $cart);
        $this->actingAs($admin)->post($route, ['action' => 'send_recovery'])->assertRedirect();
        $this->actingAs($admin)->post($route, ['action' => 'send_recovery'])->assertRedirect();

        $payload = $cart->fresh()->data;
        $this->assertNotEmpty($payload['recovery_requested_at']);
        $this->assertNotEmpty($payload['recovery_message_uuid']);
        $this->assertSame(1, ConversationMessage::query()->count());
        Queue::assertPushed(DeliverCommunicationMessage::class, 1);
    }
}
