<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionsReferencePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_page_matches_approved_hats_collection_contract(): void
    {
        $response = $this->get('/collections');

        $response->assertOk()->assertSee([
            'MADE IN LIMERICK',
            'PREMIUM QUALITY',
            'TRADE &amp; BULK ORDERS WELCOME',
            'FAST DISPATCH WORLDWIDE',
            'GLOBAL REACH',
            'SHOP BY COLLECTIONS',
            'BASEBALL CAPS',
            'BUCKET HATS',
            'SNAPBACKS',
            'IRISH TRADITIONAL FLAT CAPS',
            'IRISH HERITAGE HATS',
            'BEANIES &amp; MORE',
            'THE IRISH HERITAGE',
            'Tradition, Made in Limerick.',
            'BESTSELLERS',
            'VIEW ALL',
            '/css/collections.css?v=20260908-approved',
            'data-approved-reference="hats collection.png"',
        ], false)->assertDontSee('IMAGE PENDING', false);
    }

    public function test_active_categories_and_products_generate_real_collection_product_and_cart_links(): void
    {
        $category = Category::create([
            'name' => 'Baseball Caps',
            'slug' => 'baseball-caps',
            'description' => 'Premium caps',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Classic Emerald Cap',
            'slug' => 'classic-emerald-cap',
            'sku' => 'COL-001',
            'price' => 34.99,
            'stock' => 12,
            'is_active' => true,
        ]);

        $this->get('/collections')
            ->assertOk()
            ->assertSee('href="'.route('category', ['category'=>$category->slug]).'"', false)
            ->assertSee('href="'.route('product', ['product'=>$product->slug]).'"', false)
            ->assertSee('action="'.route('cart.add', $product).'"', false)
            ->assertSee('Classic Emerald Cap', false)
            ->assertSee('€34.99', false);
    }
}
