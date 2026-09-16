<?php

namespace Tests\Feature;

use App\Models\{Conversation, FranchiseApplication, Inquiry, SalesQuote, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBusinessAccountSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_customer_same_email_links_corporate_and_franchise_flows_to_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Verified Customer',
            'email' => 'verified.customer@example.com',
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->from('/corporate-orders')
            ->post('/enquiry', [
                'type' => 'corporate-orders',
                'name' => 'Verified Customer',
                'email' => 'verified.customer@example.com',
                'company' => 'Verified Corporate Ltd',
                'message' => 'Please prepare our corporate quote.',
                'product_interest' => 'Branded caps',
                'estimated_quantity' => 120,
            ])
            ->assertRedirect('/corporate-orders');

        $corporateInquiry = Inquiry::query()->where('type', 'corporate-orders')->firstOrFail();
        $corporateQuote = SalesQuote::query()->where('inquiry_id', $corporateInquiry->id)->firstOrFail();
        $corporateConversation = Conversation::query()->where('inquiry_id', $corporateInquiry->id)->firstOrFail();

        $this->assertSame($user->id, $corporateInquiry->customer_id);
        $this->assertSame($user->id, $corporateQuote->customer_id);
        $this->assertSame($user->id, $corporateConversation->customer_id);

        $this->actingAs($user)
            ->from('/franchise')
            ->post('/enquiry', [
                'type' => 'franchise',
                'name' => 'Verified Customer',
                'email' => 'verified.customer@example.com',
                'company' => 'Limerick City Centre',
                'country' => 'Ireland',
                'message' => 'I would like to operate an Emerald Rozalia franchise.',
                'preferred_location' => 'Limerick City Centre',
                'investment_range' => '€50,000–€100,000',
                'consent' => '1',
            ])
            ->assertRedirect('/franchise');

        $application = FranchiseApplication::query()->latest('id')->firstOrFail();
        $this->assertSame($user->id, $application->customer_id);
        $this->assertSame($user->id, $application->inquiry?->customer_id);
        $this->assertSame($user->id, $application->conversation?->customer_id);

        $this->actingAs($user)
            ->get('/account/corporate-orders')
            ->assertOk()
            ->assertSee('Corporate Quotes &amp; Orders', false)
            ->assertSee(substr((string) $corporateQuote->uuid, 0, 10));

        $this->actingAs($user)
            ->get('/account/franchise')
            ->assertOk()
            ->assertSee('Franchise Applications &amp; Orders', false)
            ->assertSee('Limerick City Centre');
    }

    public function test_logged_in_customer_using_different_email_is_not_silently_linked(): void
    {
        $user = User::factory()->create([
            'email' => 'account.owner@example.com',
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->from('/corporate-orders')
            ->post('/enquiry', [
                'type' => 'corporate-orders',
                'name' => 'Different Email Requester',
                'email' => 'different.business@example.com',
                'company' => 'Independent Business Lead',
                'message' => 'This request intentionally uses another email.',
            ])
            ->assertRedirect('/corporate-orders');

        $inquiry = Inquiry::query()->where('email', 'different.business@example.com')->firstOrFail();
        $quote = SalesQuote::query()->where('inquiry_id', $inquiry->id)->firstOrFail();
        $conversation = Conversation::query()->where('inquiry_id', $inquiry->id)->firstOrFail();

        $this->assertNull($inquiry->customer_id);
        $this->assertNull($quote->customer_id);
        $this->assertNull($conversation->customer_id);

        $this->actingAs($user)
            ->get('/account/corporate-orders')
            ->assertOk()
            ->assertSee('No corporate quote requests are linked to this account yet.');
    }

    public function test_guest_can_submit_bulk_request_without_creating_an_account(): void
    {
        $this->from('/bulk-orders')
            ->post('/enquiry', [
                'type' => 'bulk-orders',
                'name' => 'Guest Bulk Buyer',
                'email' => 'guest.bulk@example.com',
                'company' => 'Guest Bulk Ltd',
                'message' => 'Please prepare a bulk quote.',
                'estimated_quantity' => 500,
            ])
            ->assertRedirect('/bulk-orders');

        $inquiry = Inquiry::query()->where('email', 'guest.bulk@example.com')->firstOrFail();
        $quote = SalesQuote::query()->where('inquiry_id', $inquiry->id)->firstOrFail();
        $conversation = Conversation::query()->where('inquiry_id', $inquiry->id)->firstOrFail();

        $this->assertNull($inquiry->customer_id);
        $this->assertNull($quote->customer_id);
        $this->assertNull($conversation->customer_id);
        $this->assertSame('bulk', $quote->order_type);
        $this->assertSame('submitted', $quote->status);
    }
}
