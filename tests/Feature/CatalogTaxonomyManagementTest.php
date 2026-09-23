<?php

namespace Tests\Feature;

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Models\Category;
use App\Models\User;
use App\Support\CatalogGaaCountyClubs;
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
            ->assertSee('All Countries')
            ->assertSee('Category → Country → County → Club Name → Subcategory')
            ->assertSee('data-club-label', false);
        $this->actingAs($admin)->get(route('admin.categories.countries'))
            ->assertOk()
            ->assertSee('Country Master');
        $this->actingAs($admin)->get(route('admin.categories.clubs'))
            ->assertOk()
            ->assertSee('Club Master')
            ->assertSee('Category → Country → County → Club Name')
            ->assertSee('data-club-country', false)
            ->assertSee('data-club-county', false);
    }

    public function test_default_irish_gaa_county_clubs_include_laois(): void
    {
        $rows = collect(CatalogGaaCountyClubs::ireland());

        $this->assertGreaterThanOrEqual(26, $rows->count());
        $this->assertTrue($rows->contains(
            fn (array $row): bool => $row['name'] === 'Laois GAA' && $row['county_code'] !== ''
        ));
    }

    public function test_club_master_requires_country_and_county_for_club_name(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.categories.clubs.store'), [
                'governing_body' => 'gaa',
                'catalog_country_id' => $ireland->id,
                'catalog_county_code' => 'IE-LK',
                'name' => 'Limerick Test GAA',
                'slug' => 'limerick-test-gaa',
                'is_active' => 1,
                'sort_order' => 10,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalog_clubs', [
            'governing_body' => 'gaa',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-LK',
            'slug' => 'limerick-test-gaa',
        ]);
    }

    public function test_one_club_can_belong_to_english_and_uefa(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $england = CatalogCountry::query()->where('code', 'ENG')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.categories.clubs.store'), [
                'organizations' => ['english', 'uefa'],
                'catalog_country_id' => $england->id,
                'catalog_county_code' => 'ENG-GTM',
                'name' => 'Manchester United',
                'slug' => 'manchester-united-multi-org-test',
                'is_active' => 1,
                'sort_order' => 10,
            ])
            ->assertSessionHasNoErrors();

        $club = CatalogClub::query()
            ->where('slug', 'manchester-united-multi-org-test')
            ->firstOrFail();

        $this->assertTrue($club->belongsToOrganization('english'));
        $this->assertTrue($club->belongsToOrganization('uefa'));
        $this->assertDatabaseHas('catalog_club_organizations', [
            'catalog_club_id' => $club->id,
            'taxonomy_type' => 'english',
        ]);
        $this->assertDatabaseHas('catalog_club_organizations', [
            'catalog_club_id' => $club->id,
            'taxonomy_type' => 'uefa',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.categories.clubs.options', [
                'governing_body' => 'english',
                'catalog_country_id' => $england->id,
                'catalog_county_code' => 'ENG-GTM',
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $club->id, 'name' => 'Manchester United']);

        $this->actingAs($admin)
            ->getJson(route('admin.categories.clubs.options', [
                'governing_body' => 'uefa',
                'catalog_country_id' => $england->id,
                'catalog_county_code' => 'ENG-GTM',
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $club->id, 'name' => 'Manchester United']);

        $this->actingAs($admin)
            ->get(route('admin.categories.clubs'))
            ->assertOk()
            ->assertSee('name="organizations[]"', false)
            ->assertSee('Manchester United');
    }

    public function test_club_options_are_lazy_and_fifa_includes_country_fallback(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $japan = CatalogCountry::query()->where('code', 'JP')->firstOrFail();

        $exact = CatalogClub::create([
            'catalog_country_id' => $japan->id,
            'catalog_county_code' => 'JP-13',
            'governing_body' => 'fifa',
            'name' => 'Tokyo Exact Club',
            'slug' => 'tokyo-exact-club',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $fallback = CatalogClub::create([
            'catalog_country_id' => $japan->id,
            'catalog_county_code' => null,
            'governing_body' => 'fifa',
            'name' => 'Japan Country Club',
            'slug' => 'japan-country-club',
            'is_active' => true,
            'sort_order' => 20,
        ]);
        CatalogClub::create([
            'catalog_country_id' => $japan->id,
            'catalog_county_code' => 'JP-27',
            'governing_body' => 'fifa',
            'name' => 'Osaka Other Club',
            'slug' => 'osaka-other-club',
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.categories.clubs.options', [
                'governing_body' => 'fifa',
                'catalog_country_id' => $japan->id,
                'catalog_county_code' => 'JP-13',
            ]))
            ->assertOk()
            ->assertJsonPath('clubs.0.id', $exact->id)
            ->assertJsonMissing(['id' => $fallback->id])
            ->assertJsonMissing(['name' => 'Osaka Other Club']);

        $this->actingAs($admin)
            ->getJson(route('admin.categories.clubs.options', [
                'governing_body' => 'fifa',
                'catalog_country_id' => $japan->id,
                'catalog_county_code' => 'JP-01',
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $fallback->id, 'scope' => 'country'])
            ->assertJsonMissing(['name' => 'Tokyo Exact Club'])
            ->assertJsonMissing(['name' => 'Osaka Other Club']);

        $this->actingAs($admin)
            ->get(route('admin.add-product'))
            ->assertOk()
            ->assertSee('data-club-options-url', false)
            ->assertDontSee('Tokyo Exact Club', false)
            ->assertDontSee('Japan Country Club', false);
    }

    public function test_uefa_club_options_prefer_exact_region_and_fall_back_to_country(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $germany = CatalogCountry::query()->where('code', 'DE')->firstOrFail();

        $bayern = CatalogClub::create([
            'catalog_country_id' => $germany->id,
            'catalog_county_code' => 'DE-BY',
            'governing_body' => 'uefa',
            'name' => 'Bayern München',
            'slug' => 'bayern-munchen',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $countryFallback = CatalogClub::create([
            'catalog_country_id' => $germany->id,
            'catalog_county_code' => null,
            'governing_body' => 'uefa',
            'name' => 'Germany Country Club',
            'slug' => 'germany-country-club',
            'is_active' => true,
            'sort_order' => 20,
        ]);
        CatalogClub::create([
            'catalog_country_id' => $germany->id,
            'catalog_county_code' => 'DE-NW',
            'governing_body' => 'uefa',
            'name' => 'Dortmund Region Club',
            'slug' => 'dortmund-region-club',
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.categories.clubs.options', [
                'governing_body' => 'uefa',
                'catalog_country_id' => $germany->id,
                'catalog_county_code' => 'DE-BY',
            ]))
            ->assertOk()
            ->assertJsonPath('clubs.0.id', $bayern->id)
            ->assertJsonMissing(['id' => $countryFallback->id])
            ->assertJsonMissing(['name' => 'Dortmund Region Club']);

        $this->actingAs($admin)
            ->getJson(route('admin.categories.clubs.options', [
                'governing_body' => 'uefa',
                'catalog_country_id' => $germany->id,
                'catalog_county_code' => 'DE-HE',
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $countryFallback->id, 'scope' => 'country'])
            ->assertJsonMissing(['name' => 'Bayern München'])
            ->assertJsonMissing(['name' => 'Dortmund Region Club']);
    }

    public function test_uefa_country_club_product_type_hierarchy_can_be_built(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();
        $club = CatalogClub::create([
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-D',
            'governing_body' => 'uefa',
            'name' => 'Test Dublin FC',
            'slug' => 'test-dublin-fc',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)->post(route('admin.categories.taxonomy.build'), [
            'taxonomy_type' => 'uefa',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-D',
            'catalog_club_id' => $club->id,
            'product_types' => ['beanies', 'caps', 'hats'],
        ])->assertRedirect(route('admin.categories.taxonomy', [
            'taxonomy_type' => 'uefa',
            'catalog_country_id' => $ireland->id,
            'catalog_county_code' => 'IE-D',
            'catalog_club_id' => $club->id,
        ]));

        $uefa = Category::query()->where('slug', 'uefa')->firstOrFail();
        $country = Category::query()->where('slug', 'uefa-ie')->firstOrFail();
        $county = Category::query()->where('slug', 'uefa-ie-ie-d')->firstOrFail();
        $clubCategory = Category::query()->where('slug', 'uefa-ie-ie-d-test-dublin-fc')->firstOrFail();

        $this->assertNull($uefa->parent_id);
        $this->assertSame($uefa->id, $country->parent_id);
        $this->assertSame($ireland->id, $country->catalog_country_id);
        $this->assertSame($country->id, $county->parent_id);
        $this->assertSame('IE-D', $county->catalog_county_code);
        $this->assertSame($county->id, $clubCategory->parent_id);
        $this->assertSame($club->id, $clubCategory->catalog_club_id);

        foreach (['beanies', 'caps', 'hats'] as $type) {
            $leaf = Category::query()->where('slug', 'uefa-ie-ie-d-test-dublin-fc-'.$type)->firstOrFail();
            $this->assertSame($clubCategory->id, $leaf->parent_id);
            $this->assertSame('uefa', $leaf->taxonomy_type);
            $this->assertSame($ireland->id, $leaf->catalog_country_id);
            $this->assertSame('IE-D', $leaf->catalog_county_code);
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