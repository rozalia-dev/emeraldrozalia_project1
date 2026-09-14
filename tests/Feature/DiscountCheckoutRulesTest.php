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
}
