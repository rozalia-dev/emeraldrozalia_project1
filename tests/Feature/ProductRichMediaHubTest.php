<?php

namespace Tests\Feature;

use App\Models\{Product, ProductMedia, ProductSpin, ProductVariant, ProductVideo, Review, TryOnAsset, User, VariantMedia};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductRichMediaHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_integrates_six_colour_images_360_video_tryon_and_reviews(): void
    {
        $product = Product::create([
            'name' => 'Rich Media Emerald Cap',
            'slug' => 'rich-media-emerald-cap',
            'sku' => 'RICH-MEDIA-001',
            'price' => 49.99,
            'stock' => 12,
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach (['Emerald', 'Navy', 'Black', 'Brown', 'Grey', 'Cream'] as $index => $colour) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => 'RICH-MEDIA-'.($index + 1),
                'colour' => $colour,
                'size' => 'M',
                'price' => 49.99,
                'stock' => 2,
                'is_active' => true,
                'sort_order' => $index,
            ]);

            VariantMedia::create([
                'uuid' => (string) Str::uuid(),
                'product_variant_id' => $variant->id,
                'type' => 'image',
                'disk' => 'public',
                'path' => 'products/rich-media/'.strtolower($colour).'.jpg',
                'alt_text' => $colour.' Rich Media Emerald Cap',
                'sort_order' => 0,
                'approval_status' => 'approved',
                'active' => true,
            ]);
        }

        $spin = ProductSpin::create([
            'uuid' => '22222222-3333-4444-8555-666666666666',
            'product_id' => $product->id,
            'title' => 'Rich Media Emerald Cap 360°',
            'category' => 'product',
            'status' => 'published',
            'visibility' => 'public',
            'frames' => [
                'spins/22222222-3333-4444-8555-666666666666/000.jpg',
                'spins/22222222-3333-4444-8555-666666666666/001.jpg',
            ],
            'settings' => ProductSpin::DEFAULTS,
            'seo' => [],
            'hotspots' => [],
            'bytes' => 1024,
            'resolution' => '1200 × 1200',
            'updated_by' => 'Test',
        ]);

        $video = ProductVideo::create([
            'product_id' => $product->id,
            'disk' => 'external',
            'path' => 'https://youtu.be/dQw4w9WgXcQ',
            'alt_text' => 'Rich Media Cap product video',
            'sort_order' => 0,
            'approval_status' => 'approved',
            'active' => true,
            'metadata' => [
                'title' => 'Product Video',
                'platform' => 'YouTube',
                'visibility' => 'public',
                'gallery' => true,
                'description' => 'Approved public product video.',
            ],
        ]);

        $tryOnUuid = '33333333-4444-4555-8666-777777777777';
        TryOnAsset::create([
            'uuid' => $tryOnUuid,
            'product_id' => $product->id,
            'title' => 'Rich Media Cap Try-On',
            'type' => 'ar_ai',
            'target' => 'unisex',
            'status' => 'published',
            'visibility' => 'public',
            'files' => [
                'preview' => 'tryons/'.$tryOnUuid.'/abcdef123456/overlay.png',
            ],
            'settings' => TryOnAsset::DEFAULTS,
            'seo' => [],
            'bytes' => 200,
            'updated_by' => 'Test',
        ]);

        $customer = User::factory()->create(['name' => 'Verified Customer']);
        Review::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Excellent cap',
            'body' => 'The colour and fit are excellent.',
            'status' => 'approved',
        ]);

        $content = $this->get(route('product', $product))
            ->assertOk()
            ->getContent();

        $this->assertSame(6, substr_count($content, 'data-product-thumb data-index='));
        foreach (['Emerald', 'Navy', 'Black', 'Brown', 'Grey', 'Cream'] as $colour) {
            $this->assertStringContainsString($colour, $content);
        }

        $this->assertStringContainsString('data-product-mode="gallery"', $content);
        $this->assertStringContainsString('data-product-mode="spin"', $content);
        $this->assertStringContainsString('data-product-mode="video"', $content);
        $this->assertStringContainsString('data-product-mode="tryon"', $content);
        $this->assertStringContainsString('data-product-mode="reviews"', $content);

        $this->assertStringContainsString('data-spin-uuid="'.$spin->uuid.'"', $content);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $content);
        $this->assertStringContainsString(route('tryons.asset', [$tryOnUuid, 'preview']), $content);
        $this->assertStringContainsString(route('virtual-tryon', ['product_id' => $product->id]), $content);
        $this->assertStringContainsString('Excellent cap', $content);
        $this->assertStringContainsString('The colour and fit are excellent.', $content);
        $this->assertStringContainsString('6 of 6 colour views available', $content);
        $this->assertStringContainsString('REVIEWS (1)', $content);
        $this->assertNotEmpty($video->uuid);
    }


    public function test_product_media_manager_assets_power_live_media_status_and_generic_variant_prompt(): void
    {
        $product = Product::create([
            'name' => 'Media Manager Cap',
            'slug' => 'media-manager-cap',
            'sku' => 'MEDIA-MANAGER-001',
            'price' => 70,
            'stock' => 10,
            'colours' => ['White', 'Black', 'Navy', 'Green', 'Red', 'Burgundy'],
            'status' => 'active',
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MEDIA-MANAGER-V1',
            'colour' => null,
            'size' => null,
            'price' => 70,
            'stock' => 4,
            'is_active' => true,
        ]);

        foreach (['white', 'black', 'navy', 'green', 'red', 'burgundy'] as $index => $colour) {
            ProductMedia::create([
                'uuid' => (string) Str::uuid(),
                'product_id' => $product->id,
                'type' => 'image',
                'disk' => 'public',
                'path' => 'product-media/media-manager/'.$colour.'.jpg',
                'alt_text' => ucfirst($colour).' Media Manager Cap',
                'sort_order' => $index,
                'approval_status' => 'approved',
                'active' => true,
                'mime_type' => 'image/jpeg',
            ]);
        }

        $videoUuid = (string) Str::uuid();
        ProductMedia::create([
            'uuid' => $videoUuid,
            'product_id' => $product->id,
            'type' => 'video',
            'disk' => 'public',
            'path' => 'product-media/media-manager/demo.mp4',
            'alt_text' => 'Media Manager product video',
            'sort_order' => 20,
            'approval_status' => 'approved',
            'active' => true,
            'mime_type' => 'video/mp4',
        ]);

        $tryOnUuid = (string) Str::uuid();
        ProductMedia::create([
            'uuid' => $tryOnUuid,
            'product_id' => $product->id,
            'type' => 'try_on',
            'disk' => 'public',
            'path' => 'product-media/media-manager/try-on.png',
            'alt_text' => 'Media Manager virtual try-on',
            'sort_order' => 30,
            'approval_status' => 'approved',
            'active' => true,
            'mime_type' => 'image/png',
        ]);

        $content = $this->get(route('product', $product))
            ->assertOk()
            ->getContent();

        $this->assertSame(6, substr_count($content, 'data-product-thumb data-index='));
        $this->assertStringContainsString('6 of 6 colour views available', $content);
        $this->assertStringContainsString('data-has-video="true"', $content);
        $this->assertStringContainsString('data-has-tryon="true"', $content);
        $this->assertStringContainsString(route('media.public', ['uuid' => $videoUuid]), $content);
        $this->assertStringContainsString(route('media.public', ['uuid' => $tryOnUuid]), $content);
        $this->assertStringContainsString('SELECT VARIANT', $content);
        $this->assertStringContainsString('Choose a variant to see availability', $content);
        $this->assertStringNotContainsString('0 of 6 colour views available', $content);
    }

    public function test_unapproved_rich_media_is_not_exposed_as_available(): void
    {
        $product = Product::create([
            'name' => 'Private Media Cap',
            'slug' => 'private-media-cap',
            'sku' => 'PRIVATE-MEDIA-001',
            'price' => 29.99,
            'stock' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        ProductVideo::create([
            'product_id' => $product->id,
            'disk' => 'external',
            'path' => 'https://youtu.be/dQw4w9WgXcQ',
            'alt_text' => 'Pending video',
            'sort_order' => 0,
            'approval_status' => 'pending',
            'active' => true,
            'metadata' => [
                'title' => 'Pending Video',
                'platform' => 'YouTube',
                'visibility' => 'public',
                'gallery' => true,
            ],
        ]);

        TryOnAsset::create([
            'uuid' => '44444444-5555-4666-8777-888888888888',
            'product_id' => $product->id,
            'title' => 'Private Try-On',
            'type' => 'ar_ai',
            'target' => 'unisex',
            'status' => 'published',
            'visibility' => 'private',
            'files' => ['preview' => 'tryons/44444444-5555-4666-8777-888888888888/abcdef123456/overlay.png'],
            'settings' => TryOnAsset::DEFAULTS,
            'seo' => [],
            'bytes' => 200,
            'updated_by' => 'Test',
        ]);

        $content = $this->get(route('product', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-product-mode="video"', $content);
        $this->assertStringContainsString('data-product-mode="tryon"', $content);
        $this->assertStringNotContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $content);
        $this->assertStringNotContainsString('/try-on/44444444-5555-4666-8777-888888888888/assets/preview', $content);
        $this->assertStringContainsString('VIDEO</strong><small>Available when approved', $content);
        $this->assertStringContainsString('TRY ON</strong><small>Available when approved', $content);
    }
}
