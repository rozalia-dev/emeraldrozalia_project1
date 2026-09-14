<?php

namespace Tests\Feature;

use App\Models\{FranchiseStore, Product, ProductSpin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFranchiseAndArrivalContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_franchise_metrics_are_live_and_unconfigured_values_are_not_fabricated(): void
    {
        Product::create([
            'name' => 'Live Catalogue Cap',
            'slug' => 'live-catalogue-cap',
            'sku' => 'LIVE-CATALOGUE-001',
            'price' => 39.00,
            'stock' => 5,
            'is_active' => true,
        ]);
        FranchiseStore::create([
            'code' => 'LIM-001',
            'name' => 'Limerick Partner Store',
            'territory' => 'Limerick',
            'status' => 'active',
            'address' => ['country' => 'IE'],
        ]);

        $response = $this->get('/franchise');

        $response->assertOk()
            ->assertSee('data-public-data-state="live"', false)
            ->assertSee('Active retail partner', false)
            ->assertSee('Country', false)
            ->assertSee('Active product', false)
            ->assertSee('Heritage year<br>not configured', false)
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
