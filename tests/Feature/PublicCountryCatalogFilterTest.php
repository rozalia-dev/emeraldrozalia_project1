<?php

namespace Tests\Feature;

use App\Models\CatalogCountry;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCountryCatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_renders_country_dropdown_and_filters_country_linked_products(): void
    {
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();
        $france = CatalogCountry::query()->where('code', 'FR')->firstOrFail();

        $irishCategory = Category::create([
            'name' => 'Ireland GAA Caps',
            'slug' => 'ireland-gaa-caps',
            'taxonomy_type' => 'gaa',
            'catalog_country_id' => $ireland->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $frenchCategory = Category::create([
            'name' => 'France GAA Caps',
            'slug' => 'france-gaa-caps',
            'taxonomy_type' => 'gaa',
            'catalog_country_id' => $france->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 2,
        ]);

        Product::create([
            'category_id' => $irishCategory->id,
            'name' => 'Ireland Country Filter Cap',
            'slug' => 'ireland-country-filter-cap',
            'sku' => 'COUNTRY-IE-001',
            'price' => '29.00',
            'stock' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $frenchCategory->id,
            'name' => 'France Country Filter Cap',
            'slug' => 'france-country-filter-cap',
            'sku' => 'COUNTRY-FR-001',
            'price' => '29.00',
            'stock' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('data-shop-country', false)
            ->assertSee('All Countries', false)
            ->assertSee('Ireland', false);

        $this->get('/shop?country=IE')
            ->assertOk()
            ->assertSee('Ireland Country Filter Cap', false)
            ->assertDontSee('France Country Filter Cap', false);
    }

    public function test_heritage_country_dropdown_is_limited_to_eu_countries(): void
    {
        $category = Category::create([
            'name' => 'European Heritage Hats',
            'slug' => 'european-heritage-hats',
            'taxonomy_type' => 'heritage',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('category', $category))
            ->assertOk()
            ->assertSee('Ireland', false)
            ->assertDontSee('United States', false)
            ->assertSee('EU countries only for Heritage.', false);
    }
}
