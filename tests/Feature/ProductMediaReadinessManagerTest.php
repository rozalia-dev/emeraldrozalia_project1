<?php

namespace Tests\Feature;

use App\Models\{Product, ProductMedia, ProductSpin, ProductVideo, ProductVariant, Review, TryOnAsset, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductMediaReadinessManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_manager_shows_public_readiness_for_selected_product(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Review Customer']);

        $product = Product::create([
            'name' => 'Readiness Cap',
            'slug' => 'readiness-cap',
            'sku' => 'READY-001',
            'price' => 70,
            'stock' => 12,
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach (range(1, 6) as $index) {
            ProductMedia::create([
                'uuid' => (string) Str::uuid(),
                'product_id' => $product->id,
                'type' => 'image',
                'disk' => 'public',
                'path' => 'product-media/readiness/image-'.$index.'.jpg',
                'alt_text' => 'Readiness colour '.$index,
                'sort_order' => $index,
                'approval_status' => 'approved',
                'active' => true,
                'mime_type' => 'image/jpeg',
            ]);
        }

        ProductSpin::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'title' => 'Readiness 360',
            'category' => 'product',
            'status' => 'published',
            'visibility' => 'public',
            'frames' => ['spins/readiness/001.jpg', 'spins/readiness/002.jpg'],
            'settings' => ProductSpin::DEFAULTS,
            'seo' => [],
            'hotspots' => [],
            'bytes' => 200,
            'resolution' => '1200 × 1200',
            'updated_by' => 'Test',
        ]);

        ProductVideo::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => 'product-media/readiness/video.mp4',
            'alt_text' => 'Readiness product video',
            'sort_order' => 20,
            'approval_status' => 'approved',
            'active' => true,
            'mime_type' => 'video/mp4',
            'metadata' => [
                'title' => 'Readiness product video',
                'platform' => 'Website',
                'visibility' => 'public',
                'gallery' => true,
            ],
        ]);

        TryOnAsset::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'title' => 'Readiness Try-On',
            'type' => 'ar_ai',
            'target' => 'unisex',
            'status' => 'published',
            'visibility' => 'public',
            'files' => ['preview' => 'tryons/readiness/preview.png'],
            'settings' => TryOnAsset::DEFAULTS,
            'seo' => [],
            'bytes' => 100,
            'updated_by' => 'Test',
        ]);

        Review::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Great cap',
            'body' => 'Excellent product.',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.media.index', ['product_id' => $product->id]))
            ->assertOk()
            ->assertSee('PUBLIC PRODUCT PAGE READINESS')
            ->assertSee('6 of 6 public colour/product images')
            ->assertSee('2 published 360° frames')
            ->assertSee('1 public gallery video')
            ->assertSee('Public Try-On asset ready')
            ->assertSee('1 approved customer review')
            ->assertSee(route('admin.spins.index', ['product_id' => $product->id]), false)
            ->assertSee(route('admin.videos.index', ['product_id' => $product->id]), false)
            ->assertSee(route('admin.tryons.index', ['product_id' => $product->id]), false)
            ->assertSee(route('product', $product), false);

        $this->assertSame(5, substr_count($response->getContent(), 'data-readiness-type='));
    }

    public function test_media_manager_explains_missing_specialised_public_media(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $product = Product::create([
            'name' => 'Needs Media Cap',
            'slug' => 'needs-media-cap',
            'sku' => 'NEEDS-MEDIA-001',
            'price' => 45,
            'stock' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.media.index', ['product_id' => $product->id]))
            ->assertOk()
            ->assertSee('0 of 6 public colour/product images')
            ->assertSee('Publish a 360° ZIP/frame set')
            ->assertSee('Publish a public gallery video')
            ->assertSee('Publish a public Try-On preview')
            ->assertSee('0 approved customer reviews')
            ->assertSee('NOT LIVE')
            ->assertSee('NEEDS MEDIA');
    }
}
