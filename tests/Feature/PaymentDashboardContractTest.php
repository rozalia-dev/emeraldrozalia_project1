<?php

namespace Tests\Feature;

use App\Models\{Order, PaymentTransaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDashboardContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_ledger_is_never_rendered_as_operational_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.payments'));

        $response->assertOk()
            ->assertSee(['No live payment activity recorded', 'No payment method data recorded.', 'No payment status data recorded.'], false);
        foreach (['Preview data is shown', 'PAY-250501-00098', '3,842', '€482,765.75'] as $forbidden) {
            $response->assertDontSee($forbidden, false);
        }
    }

    public function test_dashboard_combines_live_transactions_with_orders_without_a_ledger_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Ledger Customer']);
        $paid = $customer->orders()->create([
            'number' => 'ER-PAYMENT-PAID',
            'order_type' => 'online',
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal' => 42,
            'shipping' => 0,
            'discount' => 0,
            'total' => 42,
            'currency' => 'EUR',
        ]);
        PaymentTransaction::create([
            'order_id' => $paid->id,
            'provider' => 'stripe',
            'transaction_id' => 'TX-LIVE-001',
            'amount' => 42,
            'currency' => 'EUR',
            'status' => 'paid',
        ]);
        $unpaid = $customer->orders()->create([
            'number' => 'ER-PAYMENT-UNLEDGERED',
            'order_type' => 'corporate',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'bank_transfer',
            'subtotal' => 18,
            'shipping' => 0,
            'discount' => 0,
            'total' => 18,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments', ['tab' => 'all']));

        $response->assertOk()
            ->assertSee(['TX-LIVE-001', 'ER-PAYMENT-PAID', 'ER-PAYMENT-UNLEDGERED', '€42.00', '€18.00', 'Live database value'], false);
        foreach (['Preview data is shown', 'PAY-250501-00098', '3,842'] as $forbidden) {
            $response->assertDontSee($forbidden, false);
        }
        $this->assertSame('paid', $paid->fresh()->payment_status);
        $this->assertSame('pending', $unpaid->fresh()->payment_status);
    }
}
