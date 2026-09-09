<?php

namespace Tests\Feature;

use App\Models\{Product, ProductSpin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagedSpinIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_view_prefers_latest_published_managed_spin_frames(): void
    {
        $product = Product::create([
            'name' => 'Managed Spin Cap',
            'slug' => 'managed-spin-cap',
            'sku' => 'MANAGED-SPIN-001',
            'price' => 34.99,
            'stock' => 5,
            'is_active' => true,
            'spin_images' => ['/legacy-spin-001.jpg', '/legacy-spin-002.jpg'],
        ]);

        $spin = ProductSpin::create([
            'uuid' => '11111111-2222-4333-8444-555555555555',
            'product_id' => $product->id,
            'title' => 'Managed Spin Cap 360°',
            'category' => 'product',
            'status' => 'published',
            'visibility' => 'public',
            'frames' => [
                'spins/11111111-2222-4333-8444-555555555555/000.jpg',
                'spins/11111111-2222-4333-8444-555555555555/001.jpg',
            ],
            'settings' => ProductSpin::DEFAULTS,
            'seo' => [],
            'hotspots' => [],
            'bytes' => 1024,
            'resolution' => '1200 × 1200',
            'updated_by' => 'Test',
        ]);

        $response = $this->get(route('product', $product));

        $response->assertOk();
        $response->assertSee('/360/'.$spin->uuid.'/frames/0', false);
        $response->assertSee('/360/'.$spin->uuid.'/frames/1', false);
        $response->assertDontSee('/legacy-spin-001.jpg', false);
    }

    public function test_product_detail_view_keeps_legacy_spin_frames_when_managed_spin_is_not_public(): void
    {
        $product = Product::create([
            'name' => 'Legacy Spin Cap',
            'slug' => 'legacy-spin-cap',
            'sku' => 'LEGACY-SPIN-001',
            'price' => 29.99,
            'stock' => 5,
            'is_active' => true,
            'spin_images' => ['/legacy-spin-001.jpg', '/legacy-spin-002.jpg'],
        ]);

        ProductSpin::create([
            'uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            'product_id' => $product->id,
            'title' => 'Private Spin',
            'category' => 'product',
            'status' => 'published',
            'visibility' => 'private',
            'frames' => [
                'spins/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee/000.jpg',
                'spins/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee/001.jpg',
            ],
            'settings' => ProductSpin::DEFAULTS,
            'seo' => [],
            'hotspots' => [],
            'bytes' => 1024,
            'resolution' => '1200 × 1200',
            'updated_by' => 'Test',
        ]);

        $response = $this->get(route('product', $product));

        $response->assertOk();
        $response->assertSee('/legacy-spin-001.jpg', false);
        $response->assertDontSee('/360/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee/frames/0', false);
    }
}
