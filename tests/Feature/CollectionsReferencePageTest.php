<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionsReferencePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_page_preserves_approved_shell_without_inventing_collection_records(): void
    {
        $response = $this->get('/collections');

        $response->assertOk()->assertSee([
            'MADE IN LIMERICK',
            'PREMIUM QUALITY',
            'TRADE &amp; BULK ORDERS WELCOME',
            'FAST DISPATCH WORLDWIDE',
            'GLOBAL REACH',
            'SHOP BY COLLECTIONS',
            'No published collections are configured yet.',
            'THE IRISH HERITAGE',
            'Tradition, Made in Limerick.',
            'BESTSELLERS',
            'VIEW ALL',
            '/css/collections.css?v=20260915-live-collections',
            'data-public-media-register="collections"',
            'data-public-media-state="awaiting-approved-media"',
        ], false)
            ->assertDontSee('BASEBALL CAPS', false)
            ->assertDontSee('BUCKET HATS', false)
            ->assertDontSee('IMAGE PENDING', false);
    }

    public function test_live_categories_collections_and_products_generate_distinct_public_links(): void
    {
        $category = Category::create([
            'name' => 'Baseball Caps',
            'slug' => 'baseball-caps',
            'description' => 'Premium caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Classic Emerald Cap',
            'slug' => 'classic-emerald-cap',
            'sku' => 'COL-001',
            'price' => 34.99,
            'stock' => 12,
            'status' => 'published',
            'is_active' => true,
        ]);

        $collection = ProductCollection::create([
            'name' => 'Heritage Essentials',
            'slug' => 'heritage-essentials',
            'description' => 'A curated heritage edit.',
            'type' => 'curated',
            'status' => 'active',
            'visibility' => 'visible',
            'sort_order' => 1,
        ]);
        $collection->products()->attach($product->id, ['sort_order' => 1]);

        $this->get('/collections')
            ->assertOk()
            ->assertSee('href="'.route('category', ['category' => $category->slug]).'"', false)
            ->assertSee('href="'.route('collection.show', ['collection' => $collection->slug]).'"', false)
            ->assertSee('href="'.route('product', ['product' => $product->slug]).'"', false)
            ->assertSee('action="'.route('cart.add', $product).'"', false)
            ->assertSee('Baseball Caps', false)
            ->assertSee('HERITAGE ESSENTIALS', false)
            ->assertSee('Classic Emerald Cap', false)
            ->assertSee('€34.99', false);
    }
}
