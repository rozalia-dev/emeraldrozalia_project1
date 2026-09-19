<?php

namespace Tests\Feature;

use App\Models\{FranchiseStore, Product, ProductSpin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFranchiseAndArrivalContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_franchise_page_omits_unconfigured_public_metrics(): void
    {
        $this->get('/franchise')
            ->assertOk()
            ->assertDontSee('data-public-data-state="live"', false)
            ->assertDontSee('fr-metrics', false)
            ->assertDontSee('Active retail partner', false)
            ->assertDontSee('Active product', false)
            ->assertDontSee('Heritage year<br>not configured', false)
            ->assertDontSee('35+', false)
            ->assertDontSee('100+', false)
            ->assertDontSee('Years of Heritage', false);
    }

    public function test_new_arrivals_only_advertises_a_public_managed_spin(): void
    {
        $product = Product::create([
            'name' => 'Arrival Cap',
            'slug' => 'arrival-cap',
            'sku' => 'ARRIVAL-001',
            'price' => 49.00,
            'stock' => 5,
            'is_new' => true,
            'is_active' => true,
        ]);

        $this->get('/new-arrivals')
            ->assertOk()
            ->assertSee('Arrival Cap', false)
            ->assertDontSee('arrival-spin-badge', false);

        ProductSpin::create([
            'product_id' => $product->id,
            'title' => 'Arrival Cap Spin',
            'status' => 'published',
            'visibility' => 'public',
            'frames' => ['frame-one.webp', 'frame-two.webp'],
            'settings' => [],
            'seo' => ['alt' => 'Arrival Cap 360 view'],
            'hotspots' => [],
        ]);

        $this->get('/new-arrivals')
            ->assertOk()
            ->assertSee('arrival-spin-badge', false)
            ->assertSee('360°', false);
    }
}
