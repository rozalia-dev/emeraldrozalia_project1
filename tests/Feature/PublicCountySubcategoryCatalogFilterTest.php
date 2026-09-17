<?php

namespace Tests\Feature;

use App\Models\CatalogCountry;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCountySubcategoryCatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_heritage_shop_loads_country_county_and_common_subcategory_filters_from_taxonomy(): void
    {
        [$root, $limerickCaps, $limerickHats, $dublinCaps] = $this->heritageTree();

        $this->product($limerickCaps, 'Limerick Heritage Cap', 'HERITAGE-LK-CAP');
        $this->product($limerickHats, 'Limerick Heritage Hat', 'HERITAGE-LK-HAT');
        $this->product($dublinCaps, 'Dublin Heritage Cap', 'HERITAGE-D-CAP');

        $this->get(route('category', $root))
            ->assertOk()
            ->assertSee('Country', false)
            ->assertSee('County', false)
            ->assertSee('Select Country First', false)
            ->assertSee('Subcategory', false)
            ->assertSee('Beanies', false)
            ->assertSee('Caps', false)
            ->assertSee('Hats', false)
            ->assertSee('Ireland', false)
            ->assertDontSee('United States', false);

        $this->get(route('category', $root).'?country=IE')
            ->assertOk()
            ->assertSee('Limerick', false)
            ->assertSee('Dublin', false);
    }

    public function test_county_filter_is_country_scoped_and_filters_products(): void
    {
        [$root, $limerickCaps, $limerickHats, $dublinCaps] = $this->heritageTree();

        $this->product($limerickCaps, 'Limerick Heritage Cap', 'HERITAGE-LK-CAP');
        $this->product($limerickHats, 'Limerick Heritage Hat', 'HERITAGE-LK-HAT');
        $this->product($dublinCaps, 'Dublin Heritage Cap', 'HERITAGE-D-CAP');

        $this->get(route('category', $root).'?country=IE&county=IE-LK')
            ->assertOk()
            ->assertSee('Limerick Heritage Cap', false)
            ->assertSee('Limerick Heritage Hat', false)
            ->assertDontSee('Dublin Heritage Cap', false);

        $this->get(route('category', $root).'?country=FR&county=IE-LK')
            ->assertSessionHasErrors('county');
    }

    public function test_common_product_type_and_parent_specific_subcategory_filters_compose(): void
    {
        [$root, $limerickCaps, $limerickHats] = $this->heritageTree();

        $this->product($limerickCaps, 'Limerick Heritage Cap', 'HERITAGE-LK-CAP');
        $this->product($limerickHats, 'Limerick Heritage Hat', 'HERITAGE-LK-HAT');

        $specials = Category::create([
            'parent_id' => $root->id,
            'name' => 'Tweed Specials',
            'slug' => 'heritage-tweed-specials',
            'taxonomy_type' => 'heritage',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 90,
        ]);
        $this->product($specials, 'Special Tweed Hat', 'HERITAGE-TWEED-001');

        $this->get(route('category', $root).'?country=IE&subcategory=type:caps')
            ->assertOk()
            ->assertSee('Limerick Heritage Cap', false)
            ->assertDontSee('Limerick Heritage Hat', false)
            ->assertDontSee('Special Tweed Hat', false);

        $this->get(route('category', $root).'?subcategory=category:heritage-tweed-specials')
            ->assertOk()
            ->assertSee('Tweed Specials', false)
            ->assertSee('Special Tweed Hat', false)
            ->assertDontSee('Limerick Heritage Cap', false);
    }

    public function test_county_control_is_not_rendered_for_non_traditional_or_heritage_category(): void
    {
        $category = Category::create([
            'name' => 'Everyday Caps',
            'slug' => 'everyday-caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('category', $category))
            ->assertOk()
            ->assertDontSee('data-shop-county', false)
            ->assertSee('Subcategory', false);
    }

    private function heritageTree(): array
    {
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();

        $root = Category::create([
            'name' => 'Heritage',
            'slug' => 'heritage-filter-root',
            'taxonomy_type' => 'heritage',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $country = Category::create([
            'parent_id' => $root->id,
            'name' => 'Ireland',
            'slug' => 'heritage-filter-ie',
            'taxonomy_type' => 'heritage',
            'catalog_country_id' => $ireland->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $limerick = Category::create([
            'parent_id' => $country->id,
            'name' => 'Limerick',
            'slug' => 'heritage-filter-ie-lk',
            'taxonomy_type' => 'heritage',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LK',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $dublin = Category::create([
            'parent_id' => $country->id,
            'name' => 'Dublin',
            'slug' => 'heritage-filter-ie-d',
            'taxonomy_type' => 'heritage',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-D',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 2,
        ]);
        $limerickCaps = Category::create([
            'parent_id' => $limerick->id,
            'name' => 'Caps',
            'slug' => 'heritage-filter-ie-lk-caps',
            'taxonomy_type' => 'heritage',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LK',
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $limerickHats = Category::create([
            'parent_id' => $limerick->id,
            'name' => 'Hats',
            'slug' => 'heritage-filter-ie-lk-hats',
            'taxonomy_type' => 'heritage',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LK',
            'product_type' => 'hats',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 2,
        ]);
        $dublinCaps = Category::create([
            'parent_id' => $dublin->id,
            'name' => 'Caps',
            'slug' => 'heritage-filter-ie-d-caps',
            'taxonomy_type' => 'heritage',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-D',
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        return [$root, $limerickCaps, $limerickHats, $dublinCaps];
    }

    private function product(Category $category, string $name, string $sku): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'sku' => $sku,
            'price' => '29.00',
            'stock' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }
}
