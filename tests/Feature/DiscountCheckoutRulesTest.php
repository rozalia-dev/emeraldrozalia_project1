<?php

namespace Tests\Feature;

use App\Models\{Discount, Product, ShippingMethod, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCheckoutRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_shipping_discount_removes_shipping_and_recalculates_total(): void
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Shipping Cap', 'slug' => 'shipping-cap', 'sku' => 'SHIP-001', 'price' => 40, 'stock' => 5, 'is_active' => true]);
        $shipping = ShippingMethod::create(['name' => 'Tracked', 'code' => 'TRACKED', 'price' => 8, 'is_active' => true]);
        Discount::create(['code' => 'FREESHIP', 'type' => 'free_shipping', 'value' => 0, 'is_active' => true]);
        $this->actingAs($user)->post(route('cart.add', $product), ['quantity' => 1])->assertRedirect();

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'name' => 'Customer', 'email' => $user->email, 'line1' => '1 Test Street', 'city' => 'Limerick', 'country' => 'IE',
            'shipping_method' => $shipping->code, 'payment_method' => 'bank_transfer', 'discount_code' => 'FREESHIP',
        ]);

        $response->assertRedirect();
        $order = $user->orders()->latest('id')->firstOrFail();
        $this->assertSame(0.0, (float) $order->shipping);
        $this->assertSame(40.0, (float) $order->total);
        $this->assertSame(1, (int) Discount::withTrashed()->where('code', 'FREESHIP')->value('used'));
    }

    public function test_buy_x_get_y_discount_applies_only_to_configured_eligible_units(): void
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'BOGO Cap', 'slug' => 'bogo-cap', 'sku' => 'BOGO-001', 'price' => 30, 'stock' => 10, 'is_active' => true]);
        Discount::create([
            'code' => 'BUY2GET1', 'type' => 'buy_x_get_y', 'value' => 0, 'is_active' => true,
            'metadata' => ['buy_quantity' => 2, 'get_quantity' => 1, 'product_skus' => ['BOGO-001']],
        ]);
        $this->actingAs($user)->post(route('cart.add', $product), ['quantity' => 3])->assertRedirect();

        $this->actingAs($user)->post(route('checkout.store'), [
            'name' => 'Customer', 'email' => $user->email, 'line1' => '1 Test Street', 'city' => 'Limerick', 'country' => 'IE',
            'payment_method' => 'bank_transfer', 'discount_code' => 'BUY2GET1',
        ])->assertRedirect();

        $order = $user->orders()->latest('id')->firstOrFail();
        $this->assertSame(90.0, (float) $order->subtotal);
        $this->assertSame(30.0, (float) $order->discount);
        $this->assertSame(60.0, (float) $order->total);
    }

    public function test_buy_x_get_y_rejects_a_cart_without_an_eligible_group(): void
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Other Cap', 'slug' => 'other-cap', 'sku' => 'OTHER-001', 'price' => 30, 'stock' => 10, 'is_active' => true]);
        Discount::create([
            'code' => 'TARGETED', 'type' => 'buy_x_get_y', 'value' => 0, 'is_active' => true,
            'metadata' => ['buy_quantity' => 1, 'get_quantity' => 1, 'product_skus' => ['TARGET-001']],
        ]);
        $this->actingAs($user)->post(route('cart.add', $product), ['quantity' => 2])->assertRedirect();

        $this->actingAs($user)->post(route('checkout.store'), [
            'name' => 'Customer', 'email' => $user->email, 'line1' => '1 Test Street', 'city' => 'Limerick', 'country' => 'IE',
            'payment_method' => 'bank_transfer', 'discount_code' => 'TARGETED',
        ])->assertRedirect()->assertSessionHasErrors('discount_code');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_archived_discount_code_can_be_reused_without_resurrecting_the_archived_record(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $old = Discount::create(['code' => 'REUSABLE', 'type' => 'percent', 'value' => 5, 'is_active' => false]);
        $this->actingAs($admin)->post(route('admin.discounts-coupons.action', $old), ['action' => 'delete'])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.discounts-coupons.store'), [
            'code' => 'REUSABLE', 'type' => 'percent', 'value' => 10, 'is_active' => 1,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSoftDeleted('discounts', ['id' => $old->id]);
        $this->assertDatabaseHas('discounts', ['code' => 'REUSABLE', 'value' => 10, 'deleted_at' => null]);
    }

    public function test_checkout_replay_is_idempotent_and_conflicting_reuse_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Replay Cap',
            'slug' => 'replay-cap',
            'sku' => 'REPLAY-001',
            'price' => 10,
            'stock' => 5,
            'is_active' => true,
        ]);
        $payload = [
            'name' => 'Customer',
            'email' => $user->email,
            'line1' => '1 Test Street',
            'city' => 'Limerick',
            'country' => 'IE',
            'payment_method' => 'bank_transfer',
            'notes' => 'Replay-safe checkout',
        ];

        $this->actingAs($user)->post(route('cart.add', $product), ['quantity' => 2])->assertRedirect();
        $first = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'checkout-replay-001')
            ->post(route('checkout.store'), $payload)
            ->assertRedirect();
        $order = $user->orders()->firstOrFail();

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'checkout-replay-001')
            ->post(route('checkout.store'), $payload)
            ->assertRedirect(route('order.success', $order));

        $this->assertSame(1, $user->orders()->count());
        $this->assertSame(1, $order->items()->count());
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('reward_transactions', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertNotEmpty($order->idempotency_key);
        $this->assertNotEmpty($order->request_hash);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'checkout-replay-001')
            ->post(route('checkout.store'), array_replace($payload, ['notes' => 'A different checkout']))
            ->assertStatus(409);
    }

    public function test_checkout_reprices_from_locked_live_product_data_before_persisting(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Repriced Cap',
            'slug' => 'repriced-cap',
            'sku' => 'REPRICE-001',
            'price' => 10,
            'stock' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('cart.add', $product), ['quantity' => 1])->assertRedirect();
        $product->update(['price' => 12]);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'checkout-reprice-001')
            ->post(route('checkout.store'), [
                'name' => 'Customer',
                'email' => $user->email,
                'line1' => '1 Test Street',
                'city' => 'Limerick',
                'country' => 'IE',
                'payment_method' => 'cod',
            ])
            ->assertRedirect();

        $order = $user->orders()->firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertSame('12.00', (string) $order->subtotal);
        $this->assertSame('12.00', (string) $item->unit_price);
        $this->assertSame('12.00', (string) $order->total);
    }
}
