<?php

namespace Tests\Feature;

use App\Models\{ContentPage, Conversation, Inquiry, MediaAsset, SalesQuote};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CorporateOrderPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_corporate_order_page_renders_live_contract_without_unmanaged_reference_fixture(): void
    {
        $baseline = MediaAsset::query()
            ->where('asset_key', 'legacy:assets/products/irish-heritage-bucket-hat/front.jpg')
            ->firstOrFail();

        $response = $this->get('/corporate-orders');

        $response
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
                '/css/order-fullwidth.css?v=20260910-fullwidth',
                '/css/corporate-order-functional.css?v=20260915-functional',
                'data-reference-contract="CORPORATE ORDERS | HOW IT WORKS | WHAT WE OFFER | REQUEST A QUOTE | WHY CHOOSE EMERALD ROZALIA | TRUSTED BY ORGANISATIONS WORLDWIDE"',
                'data-public-data-state="awaiting-approved-client-records"',
                'name="idempotency_key"',
                route('media.public', ['uuid' => $baseline->uuid]),
                'data-public-media-state="approved"',
            ], false)
            ->assertDontSee('Approved corporate media is not configured.')
            ->assertDontSee('Approved media is not configured.');

        $this->assertMatchesRegularExpression('/name="idempotency_key" value="[0-9a-f-]{36}"/i', $response->getContent());
        $this->assertStringNotContainsString('corporate-order-reference.png', $response->getContent());
    }

    public function test_repository_baseline_media_is_public_and_managed(): void
    {
        $baseline = MediaAsset::query()
            ->where('asset_key', 'legacy:assets/products/irish-heritage-bucket-hat/front.jpg')
            ->firstOrFail();

        $this->assertSame('approved', $baseline->approval_status);
        $this->assertTrue((bool) $baseline->active);
        $this->assertSame('repository-corporate-order-baseline', data_get($baseline->metadata, 'source'));
        $this->assertTrue(Storage::disk('public')->exists($baseline->path));

        $this->get(route('media.public', ['uuid' => $baseline->uuid]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_published_page_manager_media_is_rendered_through_the_approved_public_media_route(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('site-media/corporate-hero.webp', 'corporate hero');

        $asset = MediaAsset::create([
            'name' => 'Corporate hero',
            'disk' => 'local',
            'path' => 'site-media/corporate-hero.webp',
            'mime_type' => 'image/webp',
            'width' => 1600,
            'height' => 900,
            'alt_text' => 'Emerald Rozalia corporate headwear',
            'approval_status' => 'approved',
            'active' => true,
            'approved_at' => now(),
        ]);

        $page = ContentPage::create([
            'title' => 'Corporate Order',
            'slug' => 'corporate-orders',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'navigation_visible' => true,
            'published_at' => now(),
            'meta' => ['settings' => ['visibility' => 'public']],
        ]);
        $page->sections()->create([
            'type' => 'hero',
            'label' => 'Corporate hero',
            'sort_order' => 0,
            'locale' => 'en',
            'media_uuid' => $asset->uuid,
            'settings' => ['title' => 'Corporate Orders'],
            'visible' => true,
        ]);

        $this->get('/corporate-orders')
            ->assertOk()
            ->assertSee(route('media.public', ['uuid' => $asset->uuid]), false)
            ->assertSee('Emerald Rozalia corporate headwear', false)
            ->assertSee('data-page-runtime-source="managed-page"', false)
            ->assertSee('data-public-media-state="approved"', false)
            ->assertDontSee('site-media/corporate-hero.webp', false);
    }

    public function test_corporate_quote_requires_requirements_message_and_displays_the_error_on_return(): void
    {
        $response = $this->from('/corporate-orders')->post('/enquiry', [
            'type' => 'corporate-orders',
            'idempotency_key' => (string) Str::uuid(),
            'name' => 'Corporate Buyer',
            'email' => 'corporate@example.com',
        ])->assertRedirect('/corporate-orders')->assertSessionHasErrors('message');

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Please check your quote request.')
            ->assertSee('The message field is required.');

        $this->assertDatabaseCount('inquiries', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('sales_quotes', 0);
    }

    public function test_corporate_quote_is_saved_to_inquiry_communication_centre_and_sales_quote(): void
    {
        $idempotencyKey = (string) Str::uuid();
        $payload = [
            'type' => 'corporate-orders',
            'idempotency_key' => $idempotencyKey,
            'name' => 'Corporate Buyer',
            'company' => 'Corporate Team Ltd',
            'email' => 'corporate@example.com',
            'phone' => '+353 89 978 8187',
            'country' => 'Ireland',
            'message' => 'We need 120 embroidered caps for our team and client gifting.',
        ];

        $response = $this->from('/corporate-orders')->post('/enquiry', $payload);
        $response->assertRedirect('/corporate-orders')->assertSessionHas('success');

        $inquiry = Inquiry::query()->firstOrFail();
        $conversation = Conversation::query()->with('messages')->firstOrFail();
        $quote = SalesQuote::query()->firstOrFail();

        $this->assertSame('corporate-orders', $inquiry->type);
        $this->assertSame('Corporate Team Ltd', $inquiry->company);
        $this->assertSame('public_corporate-orders_form', $inquiry->meta['source']);
        $this->assertSame('Ireland', $inquiry->meta['country']);
        $this->assertSame('corporate-orders', $conversation->metadata['type']);
        $this->assertSame('Ireland', $conversation->metadata['country']);
        $this->assertSame('Corporate Team Ltd', $conversation->metadata['company']);
        $this->assertCount(1, $conversation->messages);
        $this->assertStringContainsString('120 embroidered caps', $conversation->messages->first()->body);
        $this->assertSame('corporate', $quote->order_type);
        $this->assertSame('submitted', $quote->status);
        $this->assertSame($inquiry->id, $quote->inquiry_id);
        $this->assertSame($conversation->id, $quote->conversation_id);

        $this->from('/corporate-orders')->post('/enquiry', $payload)
            ->assertRedirect('/corporate-orders')
            ->assertSessionHas('success');

        $this->assertDatabaseCount('inquiries', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('sales_quotes', 1);
    }
}
