<?php

namespace Tests\Feature;

use App\Events\SalesQuoteConverted;
use App\Models\{AuditLog, Conversation, FranchiseApplication, Inquiry, Order, Product, ProductVariant, SalesQuote, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SalesQuoteConversionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_corporate_and_bulk_enquiries_create_traceable_quotes_while_franchise_apply_creates_an_application(): void
    {
        foreach (['corporate-orders' => 'corporate', 'bulk-orders' => 'bulk'] as $type => $orderType) {
            $this->from('/'.$type)->post('/enquiry', [
                'type' => $type,
                'name' => ucfirst($orderType).' Requester',
                'email' => $orderType.'-'.uniqid().'@example.com',
                'company' => ucfirst($orderType).' Trading Ltd',
                'message' => 'Please prepare a quote for our requirements.',
                'product_interest' => 'Branded hats and caps',
                'estimated_quantity' => 250,
            ])->assertRedirect('/'.$type);

            $quote = SalesQuote::query()->where('order_type', $orderType)->latest('id')->firstOrFail();
            $this->assertSame('submitted', $quote->status);
            $this->assertSame($type, $quote->inquiry?->type);
            $this->assertSame($quote->inquiry_id, $quote->conversation?->inquiry_id);
            $this->assertNull($quote->order_id);
        }

        $this->from('/franchise')->post('/enquiry', [
            'type' => 'franchise',
            'name' => 'Franchise Applicant',
            'email' => 'franchise-'.uniqid().'@example.com',
            'company' => 'Limerick City Centre',
            'country' => 'Ireland',
            'message' => 'I would like to operate an Emerald Rozalia franchise.',
            'preferred_location' => 'Limerick City Centre',
            'investment_range' => '€50,000–€100,000',
            'consent' => '1',
        ])->assertRedirect('/franchise');

        $application = FranchiseApplication::query()->latest('id')->firstOrFail();
        $this->assertSame('new', $application->status);
        $this->assertSame('Limerick City Centre', $application->preferred_location);
        $this->assertNotNull($application->inquiry_id);
        $this->assertTrue(Conversation::query()->where('franchise_application_id', $application->id)->exists());
        $this->assertFalse(SalesQuote::query()->where('inquiry_id', $application->inquiry_id)->exists());

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('sales_quotes', 2);
        $this->assertDatabaseCount('franchise_applications', 1);
    }

    public function test_admin_can_price_approve_and_convert_a_quote_into_one_shared_order(): void
    {
        Event::fake([SalesQuoteConverted::class]);
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Corporate Heritage Cap',
            'slug' => 'corporate-heritage-cap',
            'sku' => 'QUOTE-CAP-001',
            'price' => '17.50',
            'stock' => 10,
            'is_active' => true,
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'QUOTE-CAP-001-GREEN',
            'price' => '18.00',
            'stock' => 5,
            'is_active' => true,
            'status' => 'active',
        ]);
        $inquiry = Inquiry::create([
            'type' => 'corporate-orders',
            'name' => 'Corporate Buyer',
            'email' => 'buyer@example.com',
            'company' => 'Corporate Buyer Ltd',
            'message' => 'Please quote two caps.',
        ]);
        $conversation = Conversation::create([
            'inquiry_id' => $inquiry->id,
            'channel' => 'web',
            'contact' => $inquiry->email,
            'subject' => 'Corporate quote',
            'status' => 'new',
            'priority' => 'normal',
        ]);
        $quote = app(\App\Services\SalesQuoteService::class)->createFromInquiry($inquiry, $conversation);

        $this->actingAs($admin)->patch(route('admin.quotes.update', $quote), [
            'expected_version' => 1,
            'line_items' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 2,
                'unit_price' => '18.00',
                'options' => ['colour' => 'Emerald'],
            ]],
            'shipping' => '6.95',
            'discount' => '1.50',
            'currency_code' => 'EUR',
            'exchange_rate' => '1',
        ])->assertRedirect();

        $this->assertSame('41.45', $quote->fresh()->total);
        $this->assertSame('36.00', $quote->fresh()->line_items[0]['total']);

        $this->actingAs($admin)->post(route('admin.quotes.approve', $quote->fresh()), [
            'expected_version' => 2,
        ])->assertRedirect();
        $this->assertSame('approved', $quote->fresh()->status);

        $response = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'corporate-conversion-1')
            ->post(route('admin.quotes.convert', $quote->fresh()), [
                'expected_version' => 3,
            ]);
        $response->assertRedirect();

        $order = Order::query()->where('quote_id', $quote->id)->firstOrFail();
        $this->assertSame('corporate', $order->order_type);
        $this->assertSame('approved', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('ready_to_ship', $order->fulfillment_status);
        $this->assertSame('41.45', $order->total);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => '18.00',
            'total' => '36.00',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'status' => 'awaiting_payment',
            'amount' => '41.45',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => -2,
            'type' => 'sale',
        ]);
        $this->assertSame(3, (int) $variant->fresh()->stock);
        $this->assertSame('converted', $quote->fresh()->status);
        $this->assertSame($order->id, $quote->fresh()->order_id);
        $this->assertSame('converted', $inquiry->fresh()->status);
        $this->assertSame($order->id, $conversation->fresh()->order_id);
        $this->assertTrue(AuditLog::query()->where('action', 'sales_quote.converted')->where('subject_id', $quote->id)->exists());
        Event::assertDispatched(SalesQuoteConverted::class);
    }

    public function test_quote_conversion_is_idempotent_and_rejects_a_second_key(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$quote, $product] = $this->approvedQuote('bulk');
        $first = $this->actingAs($admin)->withHeader('Idempotency-Key', 'bulk-conversion-1')
            ->post(route('admin.quotes.convert', $quote), ['expected_version' => 3]);
        $first->assertRedirect();
        $order = Order::query()->where('quote_id', $quote->id)->firstOrFail();

        $this->actingAs($admin)->withHeader('Idempotency-Key', 'bulk-conversion-1')
            ->post(route('admin.quotes.convert', $quote->fresh()))
            ->assertRedirect();
        $this->assertSame($order->id, Order::query()->where('quote_id', $quote->id)->value('id'));
        $this->assertSame(1, Order::query()->where('quote_id', $quote->id)->count());

        $this->actingAs($admin)->withHeader('Idempotency-Key', 'bulk-conversion-2')
            ->post(route('admin.quotes.convert', $quote->fresh()))
            ->assertStatus(409);
        $this->assertSame(1, Order::query()->where('quote_id', $quote->id)->count());
        $this->assertSame(7, (int) $product->fresh()->stock);
    }

    public function test_insufficient_inventory_rolls_back_the_entire_quote_conversion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$quote, $product] = $this->approvedQuote('bulk', 4, 2);

        $this->actingAs($admin)->withHeader('Idempotency-Key', 'bulk-conversion-insufficient')
            ->post(route('admin.quotes.convert', $quote), ['expected_version' => 3])
            ->assertSessionHasErrors('inventory');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertSame(2, (int) $product->fresh()->stock);
        $this->assertSame('approved', $quote->fresh()->status);
    }

    public function test_public_franchise_inquiry_cannot_be_promoted_to_a_sales_quote_by_the_service(): void
    {
        $inquiry = Inquiry::create([
            'type' => 'franchise',
            'name' => 'Franchise Applicant',
            'email' => 'franchise-service-guard@example.com',
            'company' => 'Limerick',
            'message' => 'Franchise application, not a supply order.',
        ]);
        $conversation = Conversation::create([
            'inquiry_id' => $inquiry->id,
            'channel' => 'web',
            'contact' => $inquiry->email,
            'subject' => 'Franchise application',
            'status' => 'new',
            'priority' => 'high',
        ]);

        try {
            app(\App\Services\SalesQuoteService::class)->createFromInquiry($inquiry, $conversation);
            $this->fail('Public franchise applications must not become sales quotes.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }

        $this->assertDatabaseCount('sales_quotes', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_non_admin_cannot_access_the_quote_queue(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('admin.quotes.index'))->assertForbidden();
    }

    /** @return array{0: SalesQuote, 1: Product} */
    private function approvedQuote(string $orderType, int $quantity = 3, int $stock = 10): array
    {
        $product = Product::create([
            'name' => ucfirst($orderType).' Quote Cap',
            'slug' => $orderType.'-quote-cap',
            'sku' => strtoupper($orderType).'-QUOTE-001',
            'price' => '20.00',
            'stock' => $stock,
            'is_active' => true,
            'status' => 'active',
        ]);
        $inquiry = Inquiry::create([
            'type' => $orderType.'-orders',
            'name' => ucfirst($orderType).' Buyer',
            'email' => $orderType.'-buyer@example.com',
            'company' => ucfirst($orderType).' Buyer Ltd',
            'message' => 'A quote please.',
        ]);
        $conversation = Conversation::create([
            'inquiry_id' => $inquiry->id,
            'channel' => 'web',
            'contact' => $inquiry->email,
            'subject' => 'Quote',
            'status' => 'new',
            'priority' => 'normal',
        ]);
        $quote = app(\App\Services\SalesQuoteService::class)->createFromInquiry($inquiry, $conversation);
        app(\App\Services\SalesQuoteService::class)->updatePricing($quote, [
            'expected_version' => 1,
            'line_items' => [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => '20.00',
            ]],
            'shipping' => '0.00',
            'discount' => '0.00',
            'currency_code' => 'EUR',
            'exchange_rate' => '1',
        ]);
        $quote = app(\App\Services\SalesQuoteService::class)->transition($quote->fresh(), 'approved', ['expected_version' => 2]);

        return [$quote->fresh(), $product];
    }
}
