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

    public function test_traditional_category_renders_country_dropdown_and_filters_country_linked_products(): void
    {
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();
        $france = CatalogCountry::query()->where('code', 'FR')->firstOrFail();

        $traditional = Category::create([
            'name' => 'Traditional',
            'slug' => 'traditional-country-filter-root',
            'taxonomy_type' => 'traditional',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $irishCategory = Category::create([
            'parent_id' => $traditional->id,
            'name' => 'Ireland Traditional Caps',
            'slug' => 'ireland-traditional-caps',
            'taxonomy_type' => 'traditional',
            'catalog_country_id' => $ireland->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $frenchCategory = Category::create([
            'parent_id' => $traditional->id,
            'name' => 'France Traditional Caps',
            'slug' => 'france-traditional-caps',
            'taxonomy_type' => 'traditional',
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

        $this->get(route('category', $traditional))
            ->assertOk()
            ->assertSee('data-shop-country', false)
            ->assertSee('Select Country', false)
            ->assertSee('Ireland', false)
            ->assertSee('France', false);

        $this->get(route('category', $traditional).'?country=IE')
            ->assertOk()
            ->assertSee('Ireland Country Filter Cap', false)
            ->assertDontSee('France Country Filter Cap', false);
    }

    public function test_generic_shop_does_not_render_country_control_before_a_supported_main_category_is_selected(): void
    {
        $this->get('/shop')
            ->assertOk()
            ->assertDontSee('data-shop-country', false);
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
