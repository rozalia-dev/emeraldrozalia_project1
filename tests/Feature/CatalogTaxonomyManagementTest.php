<?php

namespace Tests\Feature;

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTaxonomyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_master_is_seeded_and_admin_pages_are_available(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertGreaterThanOrEqual(240, CatalogCountry::query()->count());
        $this->assertSame(27, CatalogCountry::query()->where('is_eu', true)->count());
        $this->assertTrue((bool) CatalogCountry::query()->where('code', 'IE')->value('is_uefa'));

        $this->actingAs($admin)->get(route('admin.categories.taxonomy'))
            ->assertOk()
            ->assertSee('Country Taxonomy Builder')
            ->assertSee('All Countries');
        $this->actingAs($admin)->get(route('admin.categories.countries'))
            ->assertOk()
            ->assertSee('Country Master');
        $this->actingAs($admin)->get(route('admin.categories.clubs'))
            ->assertOk()
            ->assertSee('Club Master');
    }

    public function test_uefa_country_club_product_type_hierarchy_can_be_built(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();
        $club = CatalogClub::create([
            'catalog_country_id' => $ireland->id,
            'governing_body' => 'uefa',
            'name' => 'Test Dublin FC',
            'slug' => 'test-dublin-fc',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)->post(route('admin.categories.taxonomy.build'), [
            'taxonomy_type' => 'uefa',
            'catalog_country_id' => $ireland->id,
            'catalog_club_id' => $club->id,
            'product_types' => ['beanies', 'caps', 'hats'],
        ])->assertRedirect(route('admin.categories.taxonomy', [
            'taxonomy_type' => 'uefa',
            'catalog_country_id' => $ireland->id,
            'catalog_club_id' => $club->id,
        ]));

        $uefa = Category::query()->where('slug', 'uefa')->firstOrFail();
        $country = Category::query()->where('slug', 'uefa-ie')->firstOrFail();
        $clubCategory = Category::query()->where('slug', 'uefa-ie-test-dublin-fc')->firstOrFail();

        $this->assertNull($uefa->parent_id);
        $this->assertSame($uefa->id, $country->parent_id);
        $this->assertSame($ireland->id, $country->catalog_country_id);
        $this->assertSame($country->id, $clubCategory->parent_id);
        $this->assertSame($club->id, $clubCategory->catalog_club_id);

        foreach (['beanies', 'caps', 'hats'] as $type) {
            $leaf = Category::query()->where('slug', 'uefa-ie-test-dublin-fc-'.$type)->firstOrFail();
            $this->assertSame($clubCategory->id, $leaf->parent_id);
            $this->assertSame('uefa', $leaf->taxonomy_type);
            $this->assertSame($ireland->id, $leaf->catalog_country_id);
            $this->assertSame($club->id, $leaf->catalog_club_id);
            $this->assertSame($type, $leaf->product_type);
            $this->assertTrue($leaf->is_visible);
        }
    }

    public function test_requested_non_geographic_category_can_build_product_type_and_style_without_country(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.categories.taxonomy.build'), [
            'taxonomy_type' => 'gift',
            'product_types' => ['caps', 'hats', 'beanies'],
            'style' => 'gift-for-her',
        ])->assertSessionHasNoErrors();

        $gift = Category::query()->where('slug', 'gift')->firstOrFail();
        $caps = Category::query()->where('slug', 'gift-caps')->firstOrFail();
        $style = Category::query()->where('slug', 'gift-caps-gift-for-her')->firstOrFail();

        $this->assertNull($gift->parent_id);
        $this->assertSame($gift->id, $caps->parent_id);
        $this->assertSame($caps->id, $style->parent_id);
        $this->assertSame('caps', $style->product_type);
        $this->assertNull($style->catalog_country_id);
    }

    public function test_traditional_and_heritage_are_restricted_to_eu_countries_and_skip_clubs(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();
        $usa = CatalogCountry::query()->where('code', 'US')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.categories.taxonomy.build'), [
            'taxonomy_type' => 'traditional',
            'catalog_country_id' => $usa->id,
            'catalog_county_code' => 'US-CA',
            'product_types' => ['caps'],
        ])->assertSessionHasErrors('catalog_country_id');

        $this->actingAs($admin)->post(route('admin.categories.taxonomy.build'), [
            'taxonomy_type' => 'heritage',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LK',
            'product_types' => ['beanies', 'caps', 'hats'],
        ])->assertSessionHasNoErrors();

        $country = Category::query()->where('slug', 'heritage-ie')->firstOrFail();
        $county = Category::query()
            ->where('taxonomy_type', 'heritage')
            ->where('catalog_country_id', $ireland->id)
            ->where('catalog_county_code', 'IE-LK')
            ->whereNull('product_type')
            ->firstOrFail();

        $this->assertNull($country->catalog_club_id);
        $this->assertSame($country->id, $county->parent_id);
        $this->assertNull($county->catalog_club_id);
        $this->assertDatabaseHas('categories', [
            'slug' => $county->slug.'-caps',
            'parent_id' => $county->id,
            'taxonomy_type' => 'heritage',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LK',
            'catalog_club_id' => null,
            'product_type' => 'caps',
        ]);
    }
}