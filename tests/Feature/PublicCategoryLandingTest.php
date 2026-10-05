<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCategoryLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_traditional_and_heritage_landing_pages_use_live_root_categories_and_descendants(): void
    {
        $traditional = Category::create([
            'name' => 'Traditional',
            'slug' => 'traditional',
            'description' => 'Traditional headwear',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $traditionalCaps = Category::create([
            'parent_id' => $traditional->id,
            'name' => 'Traditional Caps',
            'slug' => 'traditional-caps',
            'description' => 'Traditional caps',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $heritage = Category::create([
            'name' => 'Heritage',
            'slug' => 'heritage',
            'description' => 'Heritage headwear',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $heritageHats = Category::create([
            'parent_id' => $heritage->id,
            'name' => 'Heritage Hats',
            'slug' => 'heritage-hats',
            'description' => 'Heritage hats',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Product::create([
            'category_id' => $traditionalCaps->id,
            'name' => 'Limerick Traditional Flat Cap',
            'slug' => 'limerick-traditional-flat-cap',
            'sku' => 'TRAD-LIVE-001',
            'price' => 79.00,
            'stock' => 10,
            'is_active' => true,
            'status' => 'active',
        ]);

        Product::create([
            'category_id' => $heritageHats->id,
            'name' => 'Emerald Heritage Hat',
            'slug' => 'emerald-heritage-hat',
            'sku' => 'HER-LIVE-001',
            'price' => 89.00,
            'stock' => 10,
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->get('/irish-traditional')
            ->assertOk()
            ->assertSeeText('Limerick Traditional Flat Cap')
            ->assertDontSeeText('Emerald Heritage Hat');

        $this->get('/irish-heritage')
            ->assertOk()
            ->assertSeeText('Emerald Heritage Hat')
            ->assertDontSeeText('Limerick Traditional Flat Cap');
    }
}
