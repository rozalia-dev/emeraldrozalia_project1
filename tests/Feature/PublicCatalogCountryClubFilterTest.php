<?php

namespace Tests\Feature;

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogCountryClubFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_renders_country_selector_and_filters_products_by_country_and_club(): void
    {
        $ireland = CatalogCountry::query()->updateOrCreate(
            ['code' => 'IE'],
            ['name' => 'Ireland', 'is_eu' => true, 'is_uefa' => true, 'is_active' => true, 'sort_order' => 1]
        );
        $france = CatalogCountry::query()->updateOrCreate(
            ['code' => 'FR'],
            ['name' => 'France', 'is_eu' => true, 'is_uefa' => true, 'is_active' => true, 'sort_order' => 2]
        );
        $limerick = CatalogClub::query()->create([
            'catalog_country_id' => $ireland->id,
            'governing_body' => 'gaa',
            'name' => 'Limerick',
            'slug' => 'limerick',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $irishCategory = Category::query()->create([
            'name' => 'Limerick GAA Caps',
            'slug' => 'gaa-ie-limerick-caps',
            'taxonomy_type' => 'gaa',
            'catalog_country_id' => $ireland->id,
            'catalog_club_id' => $limerick->id,
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $frenchCategory = Category::query()->create([
            'name' => 'France Caps',
            'slug' => 'fifa-fr-caps',
            'taxonomy_type' => 'fifa',
            'catalog_country_id' => $france->id,
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 2,
        ]);

        Product::query()->create([
            'category_id' => $irishCategory->id,
            'name' => 'Limerick Country Filter Cap',
            'slug' => 'limerick-country-filter-cap',
            'sku' => 'COUNTRY-IE-001',
            'price' => '29.90',
            'stock' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $frenchCategory->id,
            'name' => 'France Country Filter Cap',
            'slug' => 'france-country-filter-cap',
            'sku' => 'COUNTRY-FR-001',
            'price' => '31.90',
            'stock' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('data-shop-country-filter', false)
            ->assertSee('All Countries', false)
            ->assertSee('Ireland', false)
            ->assertSee('France', false);

        $this->get('/shop?country=IE')
            ->assertOk()
            ->assertSee('Limerick Country Filter Cap', false)
            ->assertDontSee('France Country Filter Cap', false);

        $this->get('/shop?country=IE&club=limerick')
            ->assertOk()
            ->assertSee('Limerick Country Filter Cap', false)
            ->assertDontSee('France Country Filter Cap', false);
    }

    public function test_gaa_category_page_exposes_country_and_country_scoped_club_controls(): void
    {
        $ireland = CatalogCountry::query()->updateOrCreate(
            ['code' => 'IE'],
            ['name' => 'Ireland', 'is_eu' => true, 'is_uefa' => true, 'is_active' => true, 'sort_order' => 1]
        );
        CatalogClub::query()->create([
            'catalog_country_id' => $ireland->id,
            'governing_body' => 'gaa',
            'name' => 'Limerick',
            'slug' => 'limerick',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $category = Category::query()->create([
            'name' => 'GAA Bucket Hats',
            'slug' => 'gaa-bucket-hats',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('category', $category))
            ->assertOk()
            ->assertSee('data-shop-country-filter', false)
            ->assertSee('data-shop-club-filter', false)
            ->assertSee('Limerick', false);
    }
}
