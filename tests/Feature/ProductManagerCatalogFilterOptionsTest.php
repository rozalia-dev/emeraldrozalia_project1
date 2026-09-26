<?php

namespace Tests\Feature;

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Models\CatalogCounty;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagerCatalogFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unfiltered_product_manager_does_not_render_the_global_county_and_club_registry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();
        $limerick = CatalogCounty::query()
            ->where('catalog_country_id', $ireland->id)
            ->where('code', 'IE-LK')
            ->firstOrFail();

        $club = CatalogClub::create([
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => $limerick->code,
            'governing_body' => 'gaa',
            'name' => 'Scoped Product Manager Test Club',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.resource', 'product-manager'));

        $response->assertOk()
            ->assertSee('data-h-county disabled', false)
            ->assertSee('data-h-club disabled', false)
            ->assertDontSee('value="'.$limerick->code.'"', false)
            ->assertDontSee($club->name);
    }

    public function test_country_and_county_selection_load_only_the_next_location_level(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();
        $limerick = CatalogCounty::query()
            ->where('catalog_country_id', $ireland->id)
            ->where('code', 'IE-LK')
            ->firstOrFail();
        $otherCounty = CatalogCounty::query()
            ->where('catalog_country_id', $ireland->id)
            ->where('code', '!=', $limerick->code)
            ->firstOrFail();

        $limerickClub = CatalogClub::create([
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => $limerick->code,
            'governing_body' => 'gaa',
            'name' => 'Scoped Limerick Product Manager Club',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $otherCountyClub = CatalogClub::create([
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => $otherCounty->code,
            'governing_body' => 'gaa',
            'name' => 'Scoped Other County Product Manager Club',
            'is_active' => true,
            'sort_order' => 20,
        ]);
        $countryWideClub = CatalogClub::create([
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => null,
            'governing_body' => 'fifa',
            'name' => 'Scoped Ireland Product Manager Team',
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $countryResponse = $this->actingAs($admin)->get(route('admin.resource', [
            'module' => 'product-manager',
            'catalog_country_id' => $ireland->id,
        ]));

        $countryResponse->assertOk()
            ->assertSee('value="'.$limerick->code.'"', false)
            ->assertSee('value="'.$otherCounty->code.'"', false)
            ->assertDontSee($countryWideClub->name)
            ->assertDontSee($limerickClub->name)
            ->assertDontSee($otherCountyClub->name);

        $countyResponse = $this->actingAs($admin)->get(route('admin.resource', [
            'module' => 'product-manager',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => $limerick->code,
        ]));

        $countyResponse->assertOk()
            ->assertSee($limerickClub->name)
            ->assertDontSee($otherCountyClub->name)
            ->assertDontSee($countryWideClub->name);
    }

    public function test_fifa_country_selection_loads_only_that_countrys_national_team(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();
        $category = Category::create([
            'name' => 'FIFA Product Manager Test',
            'slug' => 'fifa-product-manager-test',
            'taxonomy_type' => 'fifa',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'FIFA Product Manager Seed Product',
            'slug' => 'fifa-product-manager-seed-product',
            'sku' => 'PM-FIFA-TEST-001',
            'price' => 29.99,
            'stock' => 4,
            'is_active' => true,
            'status' => 'active',
        ]);
        $team = CatalogClub::create([
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => null,
            'governing_body' => 'fifa',
            'name' => 'Scoped Ireland FIFA Product Manager Team',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)->get(route('admin.resource', [
            'module' => 'product-manager',
            'root_category_id' => $category->id,
            'catalog_country_id' => $ireland->id,
        ]))->assertOk()
            ->assertSee($team->name)
            ->assertSee('data-taxonomy="fifa"', false);
    }
}
