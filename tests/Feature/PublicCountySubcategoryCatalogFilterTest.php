<?php

namespace Tests\Feature;

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogBrazil;
use App\Support\CatalogCounties;
use App\Support\CatalogEngland;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCountySubcategoryCatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_heritage_subcategory_is_independent_while_county_remains_country_dependent(): void
    {
        [$root, $limerickCaps, $limerickHats, $dublinCaps] = $this->heritageTree();

        $this->product($limerickCaps, 'Limerick Heritage Cap', 'HERITAGE-LK-CAP');
        $this->product($limerickHats, 'Limerick Heritage Hat', 'HERITAGE-LK-HAT');
        $this->product($dublinCaps, 'Dublin Heritage Cap', 'HERITAGE-D-CAP');

        $this->get(route('category', $root))
            ->assertOk()
            ->assertSee('data-shop-country', false)
            ->assertSee('data-shop-county', false)
            ->assertSee('data-shop-subcategory', false)
            ->assertSee('Select Country First', false)
            ->assertSee('All Subcategories', false)
            ->assertSee('value="type:caps"', false)
            ->assertSee('value="type:beanies"', false)
            ->assertSee('Ireland', false)
            ->assertDontSee('United States', false)
            ->assertDontSee('value="category:heritage-filter-ie-lk-caps"', false);

        $this->get(route('category', $root).'?country=IE')
            ->assertOk()
            ->assertSee('Limerick', false)
            ->assertSee('Dublin', false)
            ->assertSee('All Subcategories', false)
            ->assertSee('value="type:caps"', false);

        $this->get(route('category', $root).'?country=IE&subcategory=type:caps')
            ->assertOk()
            ->assertSee('Limerick Heritage Cap', false)
            ->assertSee('Dublin Heritage Cap', false)
            ->assertDontSee('Limerick Heritage Hat', false);

        $this->get(route('category', $root).'?country=IE&county=IE-LK&subcategory=type:caps')
            ->assertOk()
            ->assertSee('Limerick Heritage Cap', false)
            ->assertDontSee('Dublin Heritage Cap', false)
            ->assertDontSee('Limerick Heritage Hat', false);
    }

    public function test_traditional_subcategory_is_available_before_country_or_county_selection(): void
    {
        $root = Category::create([
            'name' => 'Traditional',
            'slug' => 'traditional-independent-subcategory',
            'taxonomy_type' => 'traditional',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('category', $root))
            ->assertOk()
            ->assertSee('data-shop-country', false)
            ->assertSee('data-shop-county', false)
            ->assertSee('data-shop-subcategory', false)
            ->assertSee('All Subcategories', false)
            ->assertSee('value="type:caps"', false)
            ->assertSee('value="type:hats"', false);
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

    public function test_product_metadata_classification_supports_country_and_county_filters(): void
    {
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();

        $root = Category::create([
            'name' => 'Heritage',
            'slug' => 'heritage-metadata-root',
            'taxonomy_type' => 'heritage',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $caps = Category::create([
            'parent_id' => $root->id,
            'name' => 'Caps',
            'slug' => 'heritage-metadata-caps',
            'taxonomy_type' => 'heritage',
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        Product::create([
            'category_id' => $caps->id,
            'name' => 'Metadata Limerick Cap',
            'slug' => 'metadata-limerick-cap',
            'sku' => 'META-LK-CAP',
            'price' => '39.00',
            'stock' => 4,
            'status' => 'active',
            'is_active' => true,
            'product_metadata' => [
                'catalog_classification' => [
                    'catalog_country_id' => $ireland->id,
                    'catalog_county_code' => 'IE-LK',
                ],
            ],
        ]);

        Product::create([
            'category_id' => $caps->id,
            'name' => 'Metadata Dublin Cap',
            'slug' => 'metadata-dublin-cap',
            'sku' => 'META-D-CAP',
            'price' => '39.00',
            'stock' => 4,
            'status' => 'active',
            'is_active' => true,
            'product_metadata' => [
                'catalog_classification' => [
                    'catalog_country_id' => $ireland->id,
                    'catalog_county_code' => 'IE-D',
                ],
            ],
        ]);

        $this->get(route('category', $root).'?country=IE&county=IE-LK&subcategory=type:caps')
            ->assertOk()
            ->assertSee('Metadata Limerick Cap', false)
            ->assertDontSee('Metadata Dublin Cap', false);
    }

    public function test_gaa_public_catalogue_uses_country_county_club_then_subcategory(): void
    {
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();

        $root = Category::create([
            'name' => 'GAA',
            'slug' => 'gaa-public-filter-root',
            'taxonomy_type' => 'gaa',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $country = Category::create([
            'parent_id' => $root->id,
            'name' => 'Ireland',
            'slug' => 'gaa-public-ie',
            'taxonomy_type' => 'gaa',
            'catalog_country_id' => $ireland->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $laois = Category::create([
            'parent_id' => $country->id,
            'name' => 'Laois',
            'slug' => 'gaa-public-ie-laois',
            'taxonomy_type' => 'gaa',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LS',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $laoisClub = CatalogClub::create([
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LS',
            'governing_body' => 'gaa',
            'name' => 'Laois GAA',
            'slug' => 'laois-gaa',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $portlaoiseClub = CatalogClub::create([
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LS',
            'governing_body' => 'gaa',
            'name' => 'Portlaoise GAA',
            'slug' => 'portlaoise-gaa',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $laoisClubCategory = Category::create([
            'parent_id' => $laois->id,
            'name' => 'Laois GAA',
            'slug' => 'gaa-public-ie-laois-laois-gaa',
            'taxonomy_type' => 'gaa',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LS',
            'catalog_club_id' => $laoisClub->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $portlaoiseClubCategory = Category::create([
            'parent_id' => $laois->id,
            'name' => 'Portlaoise GAA',
            'slug' => 'gaa-public-ie-laois-portlaoise-gaa',
            'taxonomy_type' => 'gaa',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LS',
            'catalog_club_id' => $portlaoiseClub->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 2,
        ]);

        $laoisCaps = Category::create([
            'parent_id' => $laoisClubCategory->id,
            'name' => 'Caps',
            'slug' => 'gaa-public-ie-laois-laois-gaa-caps',
            'taxonomy_type' => 'gaa',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LS',
            'catalog_club_id' => $laoisClub->id,
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $portlaoiseCaps = Category::create([
            'parent_id' => $portlaoiseClubCategory->id,
            'name' => 'Caps',
            'slug' => 'gaa-public-ie-laois-portlaoise-gaa-caps',
            'taxonomy_type' => 'gaa',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LS',
            'catalog_club_id' => $portlaoiseClub->id,
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->product($laoisCaps, 'Laois GAA Cap', 'GAA-LAOIS-CAP');
        $this->product($portlaoiseCaps, 'Portlaoise GAA Cap', 'GAA-PORTLAOISE-CAP');

        $this->get(route('category', $root))
            ->assertOk()
            ->assertSee('data-shop-country', false)
            ->assertSee('data-shop-county', false)
            ->assertSee('data-shop-club', false)
            ->assertSee('Select Country First', false)
            ->assertSee('Select Club First', false);

        $this->get(route('category', $root).'?country=IE')
            ->assertOk()
            ->assertSee('Laois', false)
            ->assertSee('Select County First', false);

        $this->get(route('category', $root).'?country=IE&county=IE-LS')
            ->assertOk()
            ->assertSee('Laois GAA', false)
            ->assertSee('Portlaoise GAA', false)
            ->assertSee('Select Club First', false);

        $this->get(route('category', $root).'?country=IE&county=IE-LS&club=laois-gaa&subcategory=type:caps')
            ->assertOk()
            ->assertSee('Laois GAA Cap', false)
            ->assertDontSee('Portlaoise GAA Cap', false);
    }

    public function test_english_public_catalogue_has_counties_and_clubs(): void
    {
        $england = CatalogCountry::query()->where('code', 'ENG')->firstOrFail();

        $this->assertTrue(collect(CatalogCounties::forCountry('ENG'))->contains(
            fn (array $county): bool => $county['code'] === 'ENG-GTM' && $county['name'] === 'Greater Manchester'
        ));
        $this->assertTrue(collect(CatalogEngland::CLUBS)->contains(
            fn (array $club): bool => $club['county_code'] === 'ENG-GTM' && $club['name'] === 'Manchester United'
        ));

        $root = Category::create([
            'name' => 'English',
            'slug' => 'english-public-filter-root',
            'taxonomy_type' => 'english',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $country = Category::create([
            'parent_id' => $root->id,
            'name' => 'England',
            'slug' => 'english-public-eng',
            'taxonomy_type' => 'english',
            'catalog_country_id' => $england->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $county = Category::create([
            'parent_id' => $country->id,
            'name' => 'Greater Manchester',
            'slug' => 'english-public-eng-gtm',
            'taxonomy_type' => 'english',
            'catalog_country_id' => $england->id,
            'catalog_county_code' => 'ENG-GTM',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $united = CatalogClub::create([
            'catalog_country_id' => $england->id,
            'catalog_county_code' => 'ENG-GTM',
            'governing_body' => 'english',
            'name' => 'Manchester United',
            'slug' => 'manchester-united',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $clubCategory = Category::create([
            'parent_id' => $county->id,
            'name' => 'Manchester United',
            'slug' => 'english-public-eng-gtm-manchester-united',
            'taxonomy_type' => 'english',
            'catalog_country_id' => $england->id,
            'catalog_county_code' => 'ENG-GTM',
            'catalog_club_id' => $united->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $caps = Category::create([
            'parent_id' => $clubCategory->id,
            'name' => 'Caps',
            'slug' => 'english-public-eng-gtm-manchester-united-caps',
            'taxonomy_type' => 'english',
            'catalog_country_id' => $england->id,
            'catalog_county_code' => 'ENG-GTM',
            'catalog_club_id' => $united->id,
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->product($caps, 'Manchester United Cap', 'ENG-MU-CAP');

        $this->get(route('category', $root).'?country=ENG')
            ->assertOk()
            ->assertSee('Greater Manchester', false)
            ->assertSee('Select County First', false);

        $this->get(route('category', $root).'?country=ENG&county=ENG-GTM')
            ->assertOk()
            ->assertSee('Manchester United', false)
            ->assertSee('Select Club', false);

        $this->get(route('category', $root).'?country=ENG&county=ENG-GTM&club=manchester-united&subcategory=type:caps')
            ->assertOk()
            ->assertSee('Manchester United Cap', false);
    }

    public function test_fifa_brazil_public_catalogue_has_states_and_clubs(): void
    {
        $brazil = CatalogCountry::query()->where('code', 'BR')->firstOrFail();

        $this->assertTrue(collect(CatalogCounties::forCountry('BR'))->contains(
            fn (array $state): bool => $state['code'] === 'BR-SP' && $state['name'] === 'São Paulo'
        ));
        $this->assertTrue(collect(CatalogBrazil::CLUBS)->contains(
            fn (array $club): bool => $club['county_code'] === 'BR-SP' && $club['name'] === 'Corinthians'
        ));

        $root = Category::create([
            'name' => 'FIFA',
            'slug' => 'fifa-public-filter-root',
            'taxonomy_type' => 'fifa',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $country = Category::create([
            'parent_id' => $root->id,
            'name' => 'Brazil',
            'slug' => 'fifa-public-br',
            'taxonomy_type' => 'fifa',
            'catalog_country_id' => $brazil->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $state = Category::create([
            'parent_id' => $country->id,
            'name' => 'São Paulo',
            'slug' => 'fifa-public-br-sp',
            'taxonomy_type' => 'fifa',
            'catalog_country_id' => $brazil->id,
            'catalog_county_code' => 'BR-SP',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $corinthians = CatalogClub::create([
            'catalog_country_id' => $brazil->id,
            'catalog_county_code' => 'BR-SP',
            'governing_body' => 'fifa',
            'name' => 'Corinthians',
            'slug' => 'corinthians',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $clubCategory = Category::create([
            'parent_id' => $state->id,
            'name' => 'Corinthians',
            'slug' => 'fifa-public-br-sp-corinthians',
            'taxonomy_type' => 'fifa',
            'catalog_country_id' => $brazil->id,
            'catalog_county_code' => 'BR-SP',
            'catalog_club_id' => $corinthians->id,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $caps = Category::create([
            'parent_id' => $clubCategory->id,
            'name' => 'Caps',
            'slug' => 'fifa-public-br-sp-corinthians-caps',
            'taxonomy_type' => 'fifa',
            'catalog_country_id' => $brazil->id,
            'catalog_county_code' => 'BR-SP',
            'catalog_club_id' => $corinthians->id,
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->product($caps, 'Corinthians Cap', 'FIFA-BR-SP-COR');

        $this->get(route('category', $root).'?country=BR')
            ->assertOk()
            ->assertSee('São Paulo', false)
            ->assertSee('Rio de Janeiro', false)
            ->assertSee('Select County First', false);

        $this->get(route('category', $root).'?country=BR&county=BR-SP')
            ->assertOk()
            ->assertSee('Corinthians', false)
            ->assertSee('Select Club', false);

        $this->get(route('category', $root).'?country=BR&county=BR-SP&club=corinthians&subcategory=type:caps')
            ->assertOk()
            ->assertSee('Corinthians Cap', false);
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
