<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeCollectionsReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_persisted_managed_section_contract(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee([
                'data-homepage-renderer="persisted-page-sections"',
                'data-home-section="hero"',
                'home-hero home-hero--structured',
                'home-hero-structured-copy',
                'home-hero-structured-product',
                'home-hero-tryon',
                'data-home-tryon-form',
                'data-home-section="banners"',
                'data-home-section="benefits"',
                'data-home-section="collections"',
                'data-home-section="heritage"',
                'data-home-section="products"',
                'data-home-section="quality"',
                'data-home-section="franchise"',
                'SHOP BY CATEGORY',
                'SHOP BY COLLECTION',
                'THE IRISH HERITAGE',
                'Tradition, Made in Limerick.',
                'BESTSELLERS',
                '/css/home-collections.css?v=20260922-all-category-collection',
                '/css/home-hero-layout.css?v=20260914-side-overlay-gradient',
                'data-home-carousel-track',
                'data-home-carousel-prev',
                'data-home-carousel-next',
            ], false);
        $this->get('/')->assertDontSee('<select name="product_id"', false)->assertDontSeeText('Select a product');
        $this->get('/')->assertDontSee('home-collections-reference', false)->assertDontSee('home-page-reference', false);
    }

    public function test_homepage_shows_every_public_root_category_and_every_public_collection(): void
    {
        $rootA = Category::create([
            'name' => 'Traditional',
            'slug' => 'traditional-home-test',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 901,
            'icon' => 'hat',
        ]);
        $rootB = Category::create([
            'name' => 'FIFA',
            'slug' => 'fifa-home-test',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 902,
            'icon' => 'globe',
        ]);
        Category::create([
            'parent_id' => $rootA->id,
            'name' => 'Child Category Should Not Be A Homepage Category Card',
            'slug' => 'child-home-test',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        Category::create([
            'name' => 'Hidden Homepage Category',
            'slug' => 'hidden-home-test',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => false,
            'sort_order' => 903,
        ]);

        $collectionA = ProductCollection::create([
            'name' => 'Heritage Collection Home Test',
            'slug' => 'heritage-collection-home-test',
            'description' => 'Public heritage collection.',
            'type' => 'curated',
            'status' => 'active',
            'visibility' => 'visible',
            'sort_order' => 901,
        ]);
        $collectionB = ProductCollection::create([
            'name' => 'County Collection Home Test',
            'slug' => 'county-collection-home-test',
            'description' => 'Public county collection.',
            'type' => 'curated',
            'status' => 'active',
            'visibility' => 'visible',
            'sort_order' => 902,
        ]);
        ProductCollection::create([
            'name' => 'Hidden Collection Home Test',
            'slug' => 'hidden-collection-home-test',
            'type' => 'curated',
            'status' => 'draft',
            'visibility' => 'hidden',
            'sort_order' => 903,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-home-shop-by-category', false)
            ->assertSee('SHOP BY CATEGORY')
            ->assertSee(route('category', ['category' => $rootA->slug]), false)
            ->assertSee(route('category', ['category' => $rootB->slug]), false)
            ->assertDontSee('child-home-test', false)
            ->assertDontSee('hidden-home-test', false)
            ->assertSee('data-home-shop-by-collection', false)
            ->assertSee('SHOP BY COLLECTION')
            ->assertSee(route('collection.show', ['collection' => $collectionA->slug]), false)
            ->assertSee(route('collection.show', ['collection' => $collectionB->slug]), false)
            ->assertDontSee('hidden-collection-home-test', false);
    }

    public function test_homepage_bestseller_cart_button_is_functional(): void
    {
        $product = Product::create([
            'name' => 'Emerald Reference Cap',
            'slug' => 'emerald-reference-cap',
            'sku' => 'HOME-REF-001',
            'price' => 34.99,
            'stock' => 5,
            'description' => 'Homepage bestseller reference product.',
            'is_active' => true,
            'is_new' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee(route('cart.add', $product), false)
            ->assertSee('name="quantity" value="1"', false)
            ->assertSee('Emerald Reference Cap', false);
    }
}
