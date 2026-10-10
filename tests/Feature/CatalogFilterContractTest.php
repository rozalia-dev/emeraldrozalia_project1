<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFilterContractTest extends TestCase
{
    use RefreshDatabase;



    public function test_shop_pagination_has_clean_page_one_redirect_and_self_canonical_later_pages(): void
    {
        $this->get('/shop?page=1')
            ->assertStatus(301)
            ->assertRedirect('/shop');

        $this->get('/shop?page=2')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/shop?page=2">', false);
    }

    public function test_legacy_numeric_shop_filters_redirect_to_clean_shop_url(): void
    {
        $this->get('/shop?category=3&organization=1&country=82&club=490&product_type=8')
            ->assertStatus(301)
            ->assertRedirect('/shop');

        $this->get('/shop?sort=newest')
            ->assertOk();
    }

    public function test_public_catalogue_filters_use_decimal_contracts_and_reject_exponents(): void
    {
        $inside = Product::create([
            'name' => 'Exact Price Cap',
            'slug' => 'exact-price-cap',
            'sku' => 'EXACT-PRICE-001',
            'price' => '30.10',
            'stock' => 2,
            'is_active' => true,
        ]);
        Product::create([
            'name' => 'Outside Price Cap',
            'slug' => 'outside-price-cap',
            'sku' => 'OUTSIDE-PRICE-001',
            'price' => '40.21',
            'stock' => 2,
            'is_active' => true,
        ]);

        $this->get('/shop?min_price=30.10&max_price=40.20')
            ->assertOk()
            ->assertSee('Exact Price Cap', false)
            ->assertDontSee('Outside Price Cap', false);

        $this->get('/shop?min_price=1e2')
            ->assertRedirect()
            ->assertSessionHasErrors('min_price');

        $this->assertNotNull($inside->id);
    }
}
