<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkOrderPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_order_page_renders_approved_reference_contract(): void
    {
        $this->get('/bulk-orders')
            ->assertOk()
            ->assertSee([
                'BULK ORDER',
                'SOLUTIONS',
                'Premium Headwear. Made in Limerick.',
                'OUR BULK ORDER PROCESS',
                'WHAT YOU CAN ORDER',
                'QUANTITY, LEAD TIME &amp; PRICING',
                'REQUEST A BULK QUOTE',
                'CHAT ON WHATSAPP',
                'TRUSTED BY ORGANISATIONS WORLDWIDE',
                'WHY CHOOSE EMERALD ROZALIA?',
                'WELCOME TO',
                'VISIT OUR FACTORY',
                '/css/bulk-order.css?v=20260908-approved',
                '/assets/brand/bulk-order-reference.png?v=20260908',
            ], false);

        $this->assertFileExists(public_path('assets/brand/bulk-order-reference.png'));
    }

    public function test_bulk_quote_requires_requirements_message(): void
    {
        $this->from('/bulk-orders')->post('/enquiry', [
            'type' => 'bulk-orders',
            'name' => 'Bulk Buyer',
            'email' => 'bulk@example.com',
        ])->assertRedirect('/bulk-orders')->assertSessionHasErrors('message');

        $this->assertDatabaseCount('inquiries', 0);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_bulk_quote_is_saved_to_inquiry_and_communication_centre(): void
    {
        $response = $this->from('/bulk-orders')->post('/enquiry', [
            'type' => 'bulk-orders',
            'name' => 'Bulk Buyer',
            'company' => 'Buyer Trading Ltd',
            'email' => 'bulk@example.com',
            'phone' => '+353 89 111 2222',
            'country' => 'Ireland',
            'message' => 'We need 250 embroidered caps with our logo delivered next month.',
        ]);

        $response->assertRedirect('/bulk-orders')->assertSessionHas('success');

        $inquiry = Inquiry::query()->firstOrFail();
        $conversation = Conversation::query()->with('messages')->firstOrFail();

        $this->assertSame('bulk-orders', $inquiry->type);
        $this->assertSame('Buyer Trading Ltd', $inquiry->company);
        $this->assertSame('public_bulk-orders_form', $inquiry->meta['source']);
        $this->assertSame('Ireland', $inquiry->meta['country']);
        $this->assertSame('bulk-orders', $conversation->metadata['type']);
        $this->assertSame('Ireland', $conversation->metadata['country']);
        $this->assertSame('Buyer Trading Ltd', $conversation->metadata['company']);
        $this->assertCount(1, $conversation->messages);
        $this->assertStringContainsString('250 embroidered caps', $conversation->messages->first()->body);
    }
}
