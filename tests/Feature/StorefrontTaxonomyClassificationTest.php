<?php

namespace Tests\Feature;

use App\Models\CatalogCountry;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTaxonomyClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_traditional_and_heritage_categories_are_repaired_for_eu_country_and_county_filters(): void
    {
        $traditional = Category::create([
            'name' => 'Irish Traditional Flat Caps',
            'slug' => 'irish-traditional-flat-caps',
            'taxonomy_type' => null,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $heritage = Category::create([
            'name' => 'Irish Heritage Hats',
            'slug' => 'irish-heritage-hats',
            'taxonomy_type' => null,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 2,
        ]);

        $migration = require database_path('migrations/2026_09_17_020000_classify_storefront_heritage_traditional_categories.php');
        $migration->up();

        $this->assertSame('traditional', $traditional->fresh()->taxonomy_type);
        $this->assertSame('heritage', $heritage->fresh()->taxonomy_type);

        $expectedEuCodes = CatalogCountry::query()
            ->active()
            ->where('is_eu', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('code')
            ->all();

        foreach ([$traditional, $heritage] as $category) {
            $this->get(route('category', $category->fresh()))
                ->assertOk()
                ->assertSee('data-shop-country', false)
                ->assertSee('data-shop-county', false)
                ->assertSee('Select Country First', false)
                ->assertViewHas('catalogCountyEnabled', true)
                ->assertViewHas('catalogFilterCountries', function ($countries) use ($expectedEuCodes): bool {
                    return $countries->pluck('code')->all() === $expectedEuCodes;
                });
        }
    }
}
