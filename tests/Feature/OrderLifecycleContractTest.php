<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Order, PaymentTransaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_transition_updates_shared_states_once_and_records_versioned_ledger_audit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $order = $customer->orders()->create([
            'number' => 'ER-LIFECYCLE-001',
            'order_type' => 'corporate',
            'status' => 'processing',
            'payment_status' => 'pending',
            'fulfillment_status' => 'picking',
            'payment_method' => 'bank_transfer',
            'subtotal' => 100,
            'shipping' => 0,
            'discount' => 0,
            'total' => 100,
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'exchange_rate' => 1,
        ]);
        PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'bank_transfer',
            'amount' => 100,
            'currency' => 'EUR',
            'status' => 'awaiting_payment',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.order-master.update', ['corporate', $order]), [
                'status' => 'shipped',
                'payment_status' => 'paid',
                'expected_version' => 1,
                'transition_note' => 'Carrier hand-off verified.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Order status updated and recorded.');

        $updated = $order->fresh();
        $this->assertSame('shipped', $updated->status);
        $this->assertSame('paid', $updated->payment_status);
        $this->assertSame('shipped', $updated->fulfillment_status);
        $this->assertSame(2, $updated->version);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'status' => 'paid',
        ]);
        $this->assertTrue(AuditLog::query()
            ->where('action', 'admin.order_updated')
            ->where('subject_id', $order->id)
            ->exists());
    }

    public function test_stale_order_transition_is_rejected_without_overwriting_newer_state(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::create([
            'number' => 'ER-LIFECYCLE-002',
            'order_type' => 'online',
            'status' => 'processing',
            'payment_status' => 'pending',
            'fulfillment_status' => 'picking',
            'subtotal' => 50,
            'shipping' => 0,
            'discount' => 0,
            'total' => 50,
            'currency' => 'EUR',
        ]);

        $this->actingAs($admin)->patch(route('admin.order-master.update', ['online', $order]), [
            'status' => 'shipped',
            'payment_status' => 'pending',
            'expected_version' => 1,
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.order-master.update', ['online', $order]), [
            'status' => 'completed',
            'payment_status' => 'pending',
            'expected_version' => 1,
        ])->assertStatus(409);

        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(2, $order->fresh()->version);
    }

    public function test_invalid_order_transition_is_rejected_before_persistence(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::create([
            'number' => 'ER-LIFECYCLE-003',
            'order_type' => 'buyer',
            'status' => 'shipped',
            'payment_status' => 'paid',
            'fulfillment_status' => 'shipped',
            'subtotal' => 25,
            'shipping' => 0,
            'discount' => 0,
            'total' => 25,
            'currency' => 'EUR',
        ]);

        $this->actingAs($admin)->patch(route('admin.order-master.update', ['buyer', $order]), [
            'status' => 'processing',
            'payment_status' => 'paid',
            'expected_version' => 1,
        ])->assertSessionHasErrors('status');

        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(1, $order->fresh()->version);
    }

    public function test_fulfillment_transition_cannot_move_backwards(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::create([
            'number' => 'ER-LIFECYCLE-004',
            'order_type' => 'online',
            'status' => 'processing',
            'payment_status' => 'paid',
            'fulfillment_status' => 'ready_to_ship',
            'subtotal' => 25,
            'shipping' => 0,
            'discount' => 0,
            'total' => 25,
            'currency' => 'EUR',
        ]);

        $this->actingAs($admin)->patch(route('admin.order-master.update', ['online', $order]), [
            'status' => 'processing',
            'payment_status' => 'paid',
            'fulfillment_status' => 'picking',
            'expected_version' => 1,
        ])->assertSessionHasErrors('fulfillment_status');

        $this->assertSame('ready_to_ship', $order->fresh()->fulfillment_status);
        $this->assertSame(1, $order->fresh()->version);
    }
}
