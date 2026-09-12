<?php

namespace Tests\Feature;

use App\Models\{Order, Product, ProductSpin, ReturnRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFrontOffice360ContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_uses_one_authoritative_managed_360_viewer(): void
    {
        $product = Product::create([
            'name' => 'Front Office Spin Cap',
            'slug' => 'front-office-spin-cap',
            'sku' => 'FRONT-360-001',
            'price' => 44.99,
            'stock' => 8,
            'is_active' => true,
            'spin_images' => ['/legacy-frame-001.jpg', '/legacy-frame-002.jpg'],
        ]);
        $spin = ProductSpin::create([
            'uuid' => 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff',
            'product_id' => $product->id,
            'title' => 'Front Office Spin Cap 360°',
            'category' => 'product',
            'status' => 'published',
            'visibility' => 'public',
            'frames' => [
                'spins/bbbbbbbb-cccc-4ddd-8eee-ffffffffffff/000.jpg',
                'spins/bbbbbbbb-cccc-4ddd-8eee-ffffffffffff/001.jpg',
            ],
            'settings' => ProductSpin::DEFAULTS,
            'seo' => [],
            'hotspots' => [],
            'bytes' => 1024,
            'resolution' => '1200 × 1200',
            'updated_by' => 'Test',
        ]);

        $content = $this->get(route('product', $product))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/\sdata-product-viewer(?:\s|>)/', $content));
        $this->assertSame(0, substr_count($content, 'data-spin-widget'));
        $this->assertStringContainsString('data-spin-source="managed"', $content);
        $this->assertStringContainsString('data-spin-uuid="'.$spin->uuid.'"', $content);
        $this->assertStringContainsString('/360/'.$spin->uuid.'/frames/0', $content);
        $this->assertStringContainsString('/360/'.$spin->uuid.'/frames/1', $content);
        $this->assertStringNotContainsString('/legacy-frame-001.jpg', $content);
        $this->assertStringNotContainsString('Explore in 360°', $content);
    }

    public function test_product_detail_marks_360_unavailable_without_two_approved_frames(): void
    {
        $product = Product::create([
            'name' => 'Photos Only Cap',
            'slug' => 'photos-only-cap',
            'sku' => 'PHOTOS-001',
            'price' => 29.99,
            'stock' => 4,
            'is_active' => true,
            'image' => '/photos-only-cap.jpg',
        ]);

        $content = $this->get(route('product', $product))->assertOk()->getContent();

        $this->assertStringContainsString('data-product-spin-source="none"', $content);
        $this->assertStringContainsString('data-spin-frames=\'[]\'', $content);
        $this->assertStringContainsString('aria-disabled="true"', $content);
        $this->assertStringContainsString('PHOTOS</button>', $content);
        $this->assertStringNotContainsString('data-spin-widget', $content);
    }

    public function test_customer_dashboard_uses_total_orders_and_real_returns_count(): void
    {
        $user = User::factory()->create();
        $orders = collect(range(1, 9))->map(function (int $number) use ($user): Order {
            return $user->orders()->create([
                'number' => 'ER-FRONT-'.$number,
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal' => 20,
                'shipping' => 0,
                'total' => 20,
                'currency' => 'EUR',
            ]);
        });
        ReturnRequest::create([
            'user_id' => $user->id,
            'order_id' => $orders->first()->id,
            'number' => 'RET-FRONT-001',
            'type' => 'return',
            'reason' => 'Size change',
            'status' => 'requested',
        ]);

        $content = $this->actingAs($user)->get(route('account.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<strong>9</strong><small>Orders</small>', $content);
        $this->assertStringContainsString('<strong>1</strong><small>Returns &amp; Exchanges</small>', $content);
        $this->assertStringContainsString('href="'.route('account.section', 'returns').'"', $content);
        $this->assertStringNotContainsString('Custom Designs', $content);
        $this->assertStringNotContainsString('My Designs', $content);
    }
}
