<?php

namespace Tests\Feature;

use App\Models\{Banner,Category,Conversation,FranchiseApplication,Inquiry,Product,Review,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Project1Batch1FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_versioned_catalog_api_returns_public_contracts_and_published_banners(): void
    {
        $category = Category::create([
            'name' => 'Foundation Caps',
            'slug' => 'foundation-caps',
            'description' => 'Catalog test category',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Foundation Emerald Cap',
            'slug' => 'foundation-emerald-cap',
            'sku' => 'FOUNDATION-CAP-001',
            'description' => 'Catalog test product',
            'price' => 39.99,
            'stock' => 4,
            'status' => 'active',
            'is_active' => true,
            'is_new' => true,
        ]);
        $banner = Banner::create([
            'title' => 'Foundation Home Banner',
            'subtitle' => 'Published from the cPanel data model',
            'type' => 'slider',
            'position' => 'Home - Main Slider',
            'status' => 'published',
            'target_url' => '/shop',
            'target_type' => 'internal',
            'priority' => 1,
            'specific_pages' => [],
        ]);

        $products = $this->getJson('/api/v1/products')->assertOk();
        $productData = $products->json('data.0');
        $this->assertSame($product->public_uuid, $productData['public_uuid']);
        $this->assertSame($product->name, $productData['name']);
        $this->assertArrayNotHasKey('id', $productData);

        $banners = $this->getJson('/api/v1/banners?position='.rawurlencode('Home - Main Slider'))->assertOk();
        $bannerData = $banners->json('data.0');
        $this->assertSame($banner->public_uuid, $bannerData['public_uuid']);
        $this->assertSame($banner->title, $bannerData['title']);
        $this->assertArrayNotHasKey('id', $bannerData);

        $this->get('/')->assertOk()->assertSee('Foundation Home Banner')->assertSee('data-banner-public-uuid="'.$banner->public_uuid.'"', false);
    }

    public function test_admin_reviews_page_reads_live_reviews_and_dynamic_metrics(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Live Review Customer']);
        $product = Product::create([
            'name' => 'Live Review Cap',
            'slug' => 'live-review-cap',
            'sku' => 'LIVE-REVIEW-CAP-001',
            'price' => 44.99,
            'stock' => 8,
            'status' => 'active',
            'is_active' => true,
        ]);
        $review = Review::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Live review title',
            'body' => 'This review is read from PostgreSQL-backed records.',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get('/admin/resource/reviews-ratings');

        $response->assertOk()
            ->assertSee('Live review title')
            ->assertSee('This review is read from PostgreSQL-backed records.')
            ->assertSee('Live Review Cap')
            ->assertSee($review->public_uuid)
            ->assertDontSee('Excellent quality!')
            ->assertSee('5.0');
    }

    public function test_public_franchise_submission_is_idempotent_and_correlated_across_domains(): void
    {
        $payload = [
            'type' => 'franchise',
            'name' => 'Franchise Applicant',
            'email' => 'franchise@example.test',
            'phone' => '+353 61 000 000',
            'company' => 'Emerald Test Holdings',
            'message' => 'I would like to discuss a Limerick territory.',
            'consent' => '1',
        ];

        $first = $this->withHeader('Idempotency-Key', 'franchise-foundation-001')->post('/enquiry', $payload)->assertRedirect();
        $second = $this->withHeader('Idempotency-Key', 'franchise-foundation-001')->post('/enquiry', $payload)->assertRedirect();

        $this->assertSame(1, Inquiry::count());
        $this->assertSame(1, FranchiseApplication::count());
        $this->assertSame(1, Conversation::count());
        $inquiry = Inquiry::firstOrFail();
        $application = FranchiseApplication::firstOrFail();
        $conversation = Conversation::firstOrFail();
        $this->assertNotEmpty($inquiry->correlation_id);
        $this->assertSame($inquiry->correlation_id, $application->correlation_id);
        $this->assertSame($inquiry->correlation_id, $conversation->correlation_id);
        $this->assertSame($inquiry->id, $application->inquiry_id);
        $this->assertSame($inquiry->id, $conversation->inquiry_id);
        $this->assertSame($application->id, $conversation->franchise_application_id);
        $this->assertStringContainsString('already received', (string) $second->getSession()->get('success'));
        $this->assertNotSame($first->headers->get('X-Correlation-ID'), $second->headers->get('X-Correlation-ID'));
    }

    public function test_reusing_an_idempotency_key_with_different_payload_is_rejected(): void
    {
        $payload = [
            'type' => 'contact',
            'name' => 'Contact Applicant',
            'email' => 'contact@example.test',
            'subject' => 'First enquiry',
            'message' => 'First message',
            'consent' => '1',
        ];

        $this->withHeader('Idempotency-Key', 'contact-foundation-001')->post('/enquiry', $payload)->assertRedirect();
        $this->withHeader('Idempotency-Key', 'contact-foundation-001')->post('/enquiry', array_replace($payload, [
            'message' => 'Changed message',
        ]))->assertStatus(409);
        $this->assertSame(1, Inquiry::count());
    }

    public function test_web_requests_receive_a_correlation_id_header(): void
    {
        $response = $this->get('/api/v1/banners');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', (string) $response->headers->get('X-Correlation-ID'));
    }
}
