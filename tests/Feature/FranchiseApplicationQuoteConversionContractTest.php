<?php

namespace Tests\Feature;

use App\Models\{Conversation, FranchiseApplication, Inquiry, InventoryMovement, Order, Product, SalesQuote, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchiseApplicationQuoteConversionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_franchise_application_convert_delegates_to_the_approved_quote_and_shared_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Franchise Opening Cap',
            'slug' => 'franchise-opening-cap',
            'sku' => 'FRANCHISE-OPENING-001',
            'price' => '18.00',
            'stock' => 8,
            'is_active' => true,
            'status' => 'active',
        ]);
        $inquiry = Inquiry::create([
            'type' => 'franchise',
            'name' => 'Franchise Applicant',
            'email' => 'franchise-applicant@example.com',
            'company' => 'Franchise Applicant Ltd',
            'message' => 'Please prepare an opening order quote.',
        ]);
        $application = FranchiseApplication::create([
            'applicant_name' => $inquiry->name,
            'email' => $inquiry->email,
            'phone' => null,
            'territory' => 'Munster',
            'preferred_location' => 'Limerick',
            'status' => 'onboarding',
            'correlation_id' => '11111111-1111-4111-8111-111111111111',
            'inquiry_id' => $inquiry->id,
            'data' => ['source' => 'test'],
        ]);
        $conversation = Conversation::create([
            'inquiry_id' => $inquiry->id,
            'franchise_application_id' => $application->id,
            'channel' => 'web',
            'contact' => $inquiry->email,
            'subject' => 'Franchise opening quote',
            'status' => 'new',
            'priority' => 'high',
        ]);
        $quote = app(\App\Services\SalesQuoteService::class)->createFromInquiry($inquiry, $conversation, $application);
        app(\App\Services\SalesQuoteService::class)->updatePricing($quote, [
            'expected_version' => 1,
            'line_items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => '18.00',
            ]],
            'shipping' => '5.00',
            'discount' => '0.00',
            'currency_code' => 'EUR',
            'exchange_rate' => '1',
        ]);
        $quote = app(\App\Services\SalesQuoteService::class)->transition($quote->fresh(), 'approved', [
            'expected_version' => 2,
        ]);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'franchise-application-convert-1')
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertRedirect();

        $order = Order::query()->where('quote_id', $quote->id)->firstOrFail();
        $this->assertSame('converted', $application->fresh()->status);
        $this->assertSame('converted', $quote->fresh()->status);
        $this->assertSame($order->number, data_get($application->fresh()->data, 'converted_order_number'));
        $this->assertSame($quote->uuid, data_get($application->fresh()->data, 'converted_quote_uuid'));
        $this->assertSame($order->id, $quote->fresh()->order_id);
        $this->assertSame(6, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => -2,
        ]);
    }

    public function test_franchise_application_conversion_requires_an_approved_quote(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $application = FranchiseApplication::create([
            'applicant_name' => 'Missing Quote Partner',
            'email' => 'missing-quote@example.com',
            'territory' => 'Connacht',
            'status' => 'onboarding',
        ]);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'franchise-application-convert-missing')
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertSessionHasErrors('conversion');

        $this->assertSame('onboarding', $application->fresh()->status);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_franchise_application_conversion_replays_only_with_the_same_application_key(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$application, $quote] = $this->approvedApplication();
        $firstKey = 'franchise-application-convert-replay';

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', $firstKey)
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertRedirect();

        $orderId = Order::query()->where('quote_id', $quote->id)->value('id');

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', $firstKey)
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertRedirect();

        $this->assertSame($orderId, Order::query()->where('quote_id', $quote->id)->value('id'));
        $this->assertSame(1, Order::query()->where('quote_id', $quote->id)->count());

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'franchise-application-convert-other')
            ->post(route('admin.franchise.application.action', [
                'application' => $application->uuid,
                'action' => 'convert',
            ]))
            ->assertStatus(409);

        $this->assertSame(1, Order::query()->where('quote_id', $quote->id)->count());
    }

    /** @return array{0: FranchiseApplication, 1: SalesQuote} */
    private function approvedApplication(): array
    {
        $inquiry = Inquiry::create([
            'type' => 'franchise',
            'name' => 'Replay Applicant',
            'email' => 'replay-applicant@example.com',
            'company' => 'Replay Applicant Ltd',
            'message' => 'Opening order quote.',
        ]);
        $application = FranchiseApplication::create([
            'applicant_name' => $inquiry->name,
            'email' => $inquiry->email,
            'territory' => 'Leinster',
            'status' => 'onboarding',
            'inquiry_id' => $inquiry->id,
        ]);
        $conversation = Conversation::create([
            'inquiry_id' => $inquiry->id,
            'franchise_application_id' => $application->id,
            'channel' => 'web',
            'contact' => $inquiry->email,
            'subject' => 'Opening quote',
            'status' => 'new',
            'priority' => 'high',
        ]);
        $product = Product::create([
            'name' => 'Replay Franchise Cap',
            'slug' => 'replay-franchise-cap',
            'sku' => 'FRANCHISE-REPLAY-001',
            'price' => '10.00',
            'stock' => 10,
            'is_active' => true,
            'status' => 'active',
        ]);
        $quote = app(\App\Services\SalesQuoteService::class)->createFromInquiry($inquiry, $conversation, $application);
        app(\App\Services\SalesQuoteService::class)->updatePricing($quote, [
            'expected_version' => 1,
            'line_items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => '10.00',
            ]],
            'shipping' => '0.00',
            'discount' => '0.00',
            'currency_code' => 'EUR',
            'exchange_rate' => '1',
        ]);
        $quote = app(\App\Services\SalesQuoteService::class)->transition($quote->fresh(), 'approved', [
            'expected_version' => 2,
        ]);

        return [$application, $quote];
    }
}
