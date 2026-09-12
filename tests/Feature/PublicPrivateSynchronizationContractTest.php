<?php

namespace Tests\Feature;

use App\Models\{Banner, Product, Review, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPrivateSynchronizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_and_catalog_api_only_expose_current_published_home_banners(): void
    {
        $visible = Banner::create([
            'title' => 'Sync visible home banner',
            'subtitle' => 'Published from the Banner record',
            'position' => 'Home - Main Slider',
            'status' => 'published',
            'specific_pages' => [],
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMinute(),
        ]);
        $draft = Banner::create([
            'title' => 'Sync draft home banner',
            'position' => 'Home - Main Slider',
            'status' => 'draft',
            'specific_pages' => [],
        ]);
        $expired = Banner::create([
            'title' => 'Sync expired home banner',
            'position' => 'Home - Main Slider',
            'status' => 'published',
            'specific_pages' => [],
            'ends_at' => now()->subMinute(),
        ]);
        $shopOnly = Banner::create([
            'title' => 'Sync shop-only banner',
            'position' => 'Home - Main Slider',
            'status' => 'published',
            'specific_pages' => ['shop'],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Sync visible home banner')
            ->assertSee('data-public-source="published-banner-records"', false)
            ->assertSee('data-banner-public-uuid="'.$visible->public_uuid.'"', false)
            ->assertDontSee('Sync draft home banner')
            ->assertDontSee('Sync expired home banner')
            ->assertDontSee('Sync shop-only banner');

        $this->getJson('/api/v1/banners?position='.rawurlencode('Home - Main Slider'))
            ->assertOk()
            ->assertJsonFragment(['public_uuid' => $visible->public_uuid])
            ->assertJsonMissing(['public_uuid' => $draft->public_uuid])
            ->assertJsonMissing(['public_uuid' => $expired->public_uuid])
            ->assertJsonMissing(['public_uuid' => $shopOnly->public_uuid]);
    }

    public function test_reviews_remain_private_until_admin_approval_then_publish_from_the_same_record(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $approvedOwner = User::factory()->create(['name' => 'Approved Review Customer']);
        $pendingOwner = User::factory()->create(['name' => 'Pending Review Customer']);
        $submitter = User::factory()->create(['name' => 'Submitting Review Customer']);
        $product = Product::create([
            'name' => 'Synchronization Contract Cap',
            'slug' => 'synchronization-contract-cap',
            'sku' => 'SYNC-CONTRACT-CAP-001',
            'description' => 'A product used to verify the public and private review boundary.',
            'price' => 49.99,
            'stock' => 12,
            'status' => 'active',
            'is_active' => true,
        ]);
        $approved = Review::create([
            'user_id' => $approvedOwner->id,
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Approved synchronization review',
            'body' => 'This approved review is public.',
            'status' => 'approved',
        ]);
        $pending = Review::create([
            'user_id' => $pendingOwner->id,
            'product_id' => $product->id,
            'rating' => 3,
            'title' => 'Pending synchronization review',
            'body' => 'This pending review stays private.',
            'status' => 'pending',
        ]);

        $this->get(route('product', $product))
            ->assertOk()
            ->assertSee($approved->title)
            ->assertSee($approved->body)
            ->assertDontSee($pending->title)
            ->assertDontSee($pending->body)
            ->assertSee('data-public-source="approved-review-records"', false);

        $this->actingAs($admin)->get('/admin/resource/reviews-ratings')
            ->assertOk()
            ->assertSee($approved->title)
            ->assertSee($pending->title)
            ->assertSee($pending->body)
            ->assertSee('data-review-source="review-records"', false);

        $this->actingAs($submitter)->post(route('reviews.store', $product), [
            'rating' => 4,
            'title' => 'Submitted synchronization review',
            'body' => 'This submitted review awaits moderation.',
        ])->assertRedirect();

        $submitted = Review::query()
            ->where('user_id', $submitter->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
        $this->assertSame('pending', $submitted->status);
        $this->assertDatabaseHas('reviews', [
            'id' => $submitted->id,
            'status' => 'pending',
        ]);

        $this->get(route('product', $product))
            ->assertDontSee($submitted->title)
            ->assertDontSee($submitted->body);
        $this->actingAs($admin)->get('/admin/resource/reviews-ratings?status=pending')
            ->assertOk()
            ->assertSee($submitted->title)
            ->assertSee($submitted->body);
        $this->actingAs($submitter)
            ->patch(route('admin.reviews.status', $submitted), ['status' => 'approved'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('admin.reviews.status', $submitted), ['status' => 'approved'])
            ->assertRedirect();
        $this->assertDatabaseHas('reviews', [
            'id' => $submitted->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.status.updated']);

        $this->get(route('product', $product))
            ->assertSee($submitted->title)
            ->assertSee($submitted->body);
    }
}
