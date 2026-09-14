<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Order, PaymentTransaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentLifecycleContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_uses_the_shared_order_lifecycle_without_creating_a_duplicate_ledger_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::create([
            'number' => 'ER-PAY-LIFECYCLE-001',
            'order_type' => 'online',
            'status' => 'processing',
            'payment_status' => 'pending',
            'fulfillment_status' => 'picking',
            'payment_method' => 'bank_transfer',
            'subtotal' => 100,
            'shipping' => 0,
            'discount' => 0,
            'total' => 100,
            'currency' => 'EUR',
        ]);
        $payment = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'bank_transfer',
            'amount' => 100,
            'currency' => 'EUR',
            'status' => 'awaiting_payment',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.action', $payment), [
                'action' => 'capture',
                'expected_version' => 1,
                'transition_note' => 'Bank confirmation received.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment captured and reconciled.');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(2, $order->fresh()->version);
        $this->assertSame(1, PaymentTransaction::query()->where('order_id', $order->id)->count());
        $this->assertNotEmpty($payment->fresh()->payload['status_history'] ?? []);
        $this->assertNotNull($payment->fresh()->payload['captured_at'] ?? null);
        $this->assertTrue(AuditLog::query()->where('action', 'admin.payment_capture')->where('subject_id', $payment->id)->exists());
    }

    public function test_partial_then_full_refund_is_exact_and_only_full_refund_closes_the_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::create([
            'number' => 'ER-PAY-LIFECYCLE-002',
            'order_type' => 'corporate',
            'status' => 'shipped',
            'payment_status' => 'paid',
            'fulfillment_status' => 'shipped',
            'payment_method' => 'manual',
            'subtotal' => 100,
            'shipping' => 0,
            'discount' => 0,
            'total' => 100,
            'currency' => 'EUR',
        ]);
        $payment = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'manual',
            'amount' => 100,
            'currency' => 'EUR',
            'status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.action', $payment), [
                'action' => 'refund',
                'expected_version' => 1,
                'refund_amount' => '25.00',
            ])
            ->assertRedirect();

        $this->assertSame('partially_refunded', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(1, $order->fresh()->version);
        $this->assertSame('25.00', $payment->fresh()->payload['refunded_amount']);

        $this->actingAs($admin)
            ->post(route('admin.payments.action', $payment), [
                'action' => 'refund',
                'expected_version' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(2, $order->fresh()->version);
        $this->assertSame('100.00', $payment->fresh()->payload['refunded_amount']);
    }

    public function test_payment_action_rejects_stale_order_versions_before_mutation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::create([
            'number' => 'ER-PAY-LIFECYCLE-003',
            'order_type' => 'buyer',
            'status' => 'processing',
            'payment_status' => 'pending',
            'fulfillment_status' => 'picking',
            'subtotal' => 50,
            'shipping' => 0,
            'discount' => 0,
            'total' => 50,
            'currency' => 'EUR',
        ]);
        $payment = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'manual',
            'amount' => 50,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);
        $order->update(['version' => 2]);

        $this->actingAs($admin)
            ->post(route('admin.payments.action', $payment), [
                'action' => 'capture',
                'expected_version' => 1,
            ])
            ->assertStatus(409);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }
}
