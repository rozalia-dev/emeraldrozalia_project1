<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorporateOrderPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_corporate_order_page_renders_approved_reference_contract(): void
    {
        $this->get('/corporate-orders')
            ->assertOk()
            ->assertSee([
                'CORPORATE',
                'ORDERS',
                'Premium Headwear. Professional Impact.',
                'HOW IT WORKS',
                'WHAT WE OFFER',
                'REQUEST A QUOTE',
                'WHY CHOOSE EMERALD ROZALIA?',
                'TRUSTED BY ORGANISATIONS WORLDWIDE',
                "LET'S WORK TOGETHER",
                '/css/corporate-order.css?v=20260908-approved',
                '/assets/brand/corporate-order-reference.png?v=20260908',
            ], false);

        $this->assertFileExists(public_path('assets/brand/corporate-order-reference.png'));
    }

    public function test_corporate_quote_requires_requirements_message(): void
    {
        $this->from('/corporate-orders')->post('/enquiry', [
            'type' => 'corporate-orders',
            'name' => 'Corporate Buyer',
            'email' => 'corporate@example.com',
        ])->assertRedirect('/corporate-orders')->assertSessionHasErrors('message');

        $this->assertDatabaseCount('inquiries', 0);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_corporate_quote_is_saved_to_inquiry_and_communication_centre(): void
    {
        $response = $this->from('/corporate-orders')->post('/enquiry', [
            'type' => 'corporate-orders',
            'name' => 'Corporate Buyer',
            'company' => 'Corporate Team Ltd',
            'email' => 'corporate@example.com',
            'phone' => '+353 89 978 8187',
            'country' => 'Ireland',
            'message' => 'We need 120 embroidered caps for our team and client gifting.',
        ]);

        $response->assertRedirect('/corporate-orders')->assertSessionHas('success');

        $inquiry = Inquiry::query()->firstOrFail();
        $conversation = Conversation::query()->with('messages')->firstOrFail();

        $this->assertSame('corporate-orders', $inquiry->type);
        $this->assertSame('Corporate Team Ltd', $inquiry->company);
        $this->assertSame('public_corporate-orders_form', $inquiry->meta['source']);
        $this->assertSame('Ireland', $inquiry->meta['country']);
        $this->assertSame('corporate-orders', $conversation->metadata['type']);
        $this->assertSame('Ireland', $conversation->metadata['country']);
        $this->assertSame('Corporate Team Ltd', $conversation->metadata['company']);
        $this->assertCount(1, $conversation->messages);
        $this->assertStringContainsString('120 embroidered caps', $conversation->messages->first()->body);
    }
}
