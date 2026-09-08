<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewArrivalsReferencePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_arrivals_page_matches_approved_reference_contract(): void
    {
        $this->get('/new-arrivals')
            ->assertOk()
            ->assertSee([
                'NEW ARRIVALS',
                'Fresh styles. Timeless heritage.',
                'JUST LANDED',
                'IRISH CRAFTSMANSHIP',
                'PREMIUM QUALITY',
                'LIMITED STOCK',
                'FAST DELIVERY',
                'FILTERS',
                'CATEGORY',
                'COLOR',
                'MATERIAL',
                'PRICE',
                'SEE IT ON YOU',
                'LOVE IT OR RETURN IT',
                'STAY IN THE LOOP',
                '/css/new-arrivals.css?v=20260908-approved',
                'data-approved-reference="new arrival page.png"',
            ], false);
    }

    public function test_new_arrival_filters_product_links_cart_and_newsletter_are_functional(): void
    {
        $flatCaps = Category::create([
            'name' => 'Irish Traditional Flat Caps',
            'slug' => 'irish-traditional-flat-caps',
            'description' => 'Traditional flat caps',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $baseball = Category::create([
            'name' => 'Baseball Caps',
            'slug' => 'baseball-caps',
            'description' => 'Baseball caps',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $matching = Product::create([
            'category_id' => $flatCaps->id,
            'name' => 'Olive Herringbone Flat Cap',
            'slug' => 'olive-herringbone-flat-cap',
            'sku' => 'NEW-001',
            'price' => 44.99,
            'stock' => 12,
            'material' => 'Premium Tweed Wool',
            'colours' => ['Green', 'Olive'],
            'is_new' => true,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $baseball->id,
            'name' => 'Premium Navy Baseball Cap',
            'slug' => 'premium-navy-baseball-cap',
            'sku' => 'NEW-002',
            'price' => 89.99,
            'stock' => 9,
            'material' => 'Cotton',
            'colours' => ['Navy'],
            'is_new' => true,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $flatCaps->id,
            'name' => 'Archive Flat Cap',
            'slug' => 'archive-flat-cap',
            'sku' => 'OLD-001',
            'price' => 39.99,
            'stock' => 6,
            'material' => 'Tweed',
            'colours' => ['Green'],
            'is_new' => false,
            'is_active' => true,
        ]);

        $url = '/new-arrivals?'.http_build_query([
            'category' => ['irish-traditional-flat-caps'],
            'material' => ['Tweed'],
            'colour' => ['green'],
            'max_price' => 50,
            'sort' => 'price_low',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Olive Herringbone Flat Cap', false)
            ->assertSee('€44.99', false)
            ->assertSee('href="'.route('product', ['product'=>$matching->slug]).'"', false)
            ->assertSee('action="'.route('cart.add', $matching).'"', false)
            ->assertSee('action="'.route('inquiry').'"', false)
            ->assertSee('name="email"', false)
            ->assertDontSee('Premium Navy Baseball Cap', false)
            ->assertDontSee('Archive Flat Cap', false);
    }
}
