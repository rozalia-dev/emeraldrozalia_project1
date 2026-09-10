<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopReferencePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_page_exposes_the_full_storefront_contract(): void
    {
        $this->get('/shop')
            ->assertOk()
            ->assertSee([
                'SHOP ALL HATS &amp; CAPS',
                'Irish made. Premium quality. Made in Limerick.',
                'IRISH MADE',
                'WORLDWIDE DELIVERY',
                'FILTERS',
                'CATEGORY',
                'COLOUR',
                'MATERIAL',
                'SIZE',
                'PRICE',
                'AVAILABILITY',
                'SEE IT ON YOU',
                'LOVE IT OR RETURN IT',
                '/css/shop.css?v=20260910-reference',
                '/js/shop.js?v=20260910-reference',
                'data-shop-page',
            ], false);
    }

    public function test_shop_filters_sort_product_links_and_cart_are_functional(): void
    {
        $flatCaps = Category::create([
            'name' => 'Irish Traditional Flat Caps',
            'slug' => 'irish-traditional-flat-caps',
            'description' => 'Traditional flat caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $baseball = Category::create([
            'name' => 'Baseball Caps',
            'slug' => 'baseball-caps',
            'description' => 'Baseball caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 2,
        ]);

        $matching = Product::create([
            'category_id' => $flatCaps->id,
            'name' => 'Emerald Tweed Flat Cap',
            'slug' => 'emerald-tweed-flat-cap',
            'sku' => 'SHOP-001',
            'price' => 44.99,
            'compare_price' => 54.99,
            'stock' => 12,
            'material' => 'Premium Tweed Wool',
            'colours' => ['Green', 'Olive'],
            'sizes' => ['M', 'L'],
            'is_new' => true,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $baseball->id,
            'name' => 'Navy Cotton Baseball Cap',
            'slug' => 'navy-cotton-baseball-cap',
            'sku' => 'SHOP-002',
            'price' => 39.99,
            'stock' => 20,
            'material' => 'Cotton',
            'colours' => ['Navy'],
            'sizes' => ['One Size'],
            'is_new' => false,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $flatCaps->id,
            'name' => 'Out of Stock Green Flat Cap',
            'slug' => 'out-of-stock-green-flat-cap',
            'sku' => 'SHOP-003',
            'price' => 42.00,
            'stock' => 0,
            'material' => 'Tweed',
            'colours' => ['Green'],
            'sizes' => ['M'],
            'is_new' => false,
            'is_active' => true,
        ]);

        $url = '/shop?'.http_build_query([
            'category' => ['irish-traditional-flat-caps'],
            'material' => ['Tweed'],
            'colour' => ['green'],
            'size' => ['M'],
            'max_price' => 50,
            'availability' => 'in_stock',
            'sort' => 'price_low',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Emerald Tweed Flat Cap', false)
            ->assertSee('€44.99', false)
            ->assertSee('href="'.route('product', $matching).'"', false)
            ->assertSee('action="'.route('cart.add', $matching).'"', false)
            ->assertSee('name="quantity" value="1"', false)
            ->assertDontSee('Navy Cotton Baseball Cap', false)
            ->assertDontSee('Out of Stock Green Flat Cap', false);
    }

    public function test_category_route_uses_the_same_shop_experience_and_keeps_category_scope(): void
    {
        $flatCaps = Category::create([
            'name' => 'Flat Caps',
            'slug' => 'flat-caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $baseball = Category::create([
            'name' => 'Baseball Caps',
            'slug' => 'baseball-caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 2,
        ]);

        Product::create([
            'category_id' => $flatCaps->id,
            'name' => 'Scoped Flat Cap',
            'slug' => 'scoped-flat-cap',
            'sku' => 'SCOPE-001',
            'price' => 45,
            'stock' => 5,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $baseball->id,
            'name' => 'Outside Baseball Cap',
            'slug' => 'outside-baseball-cap',
            'sku' => 'SCOPE-002',
            'price' => 30,
            'stock' => 5,
            'is_active' => true,
        ]);

        $this->get(route('category', $flatCaps))
            ->assertOk()
            ->assertSee('FLAT CAPS', false)
            ->assertSee('Scoped Flat Cap', false)
            ->assertDontSee('Outside Baseball Cap', false)
            ->assertSee('/css/shop.css?v=20260910-reference', false);
    }
}
