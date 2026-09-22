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
                '/css/home-collections.css?v=20260922-category-products',
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

    public function test_homepage_root_category_count_includes_published_products_from_visible_descendants(): void
    {
        $root = Category::create([
            'name' => 'Traditional Count Root',
            'slug' => 'traditional-count-root',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 940,
        ]);
        $child = Category::create([
            'parent_id' => $root->id,
            'name' => 'Traditional Count Caps',
            'slug' => 'traditional-count-caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        Product::create([
            'category_id' => $child->id,
            'name' => 'Descendant Count Cap',
            'slug' => 'descendant-count-cap',
            'sku' => 'DESC-COUNT-001',
            'price' => 39.00,
            'stock' => 4,
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-home-category="traditional-count-root"', false)
            ->assertSeeText('1 product');
    }

    public function test_homepage_displays_published_products_under_their_root_category(): void
    {
        $root = Category::create([
            'name' => 'Traditional Product Row',
            'slug' => 'traditional-product-row',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 945,
        ]);
        $child = Category::create([
            'parent_id' => $root->id,
            'name' => 'Baseball Caps Product Row',
            'slug' => 'baseball-caps-product-row',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $child->id,
            'name' => 'Homepage Traditional Driver Cap',
            'slug' => 'homepage-traditional-driver-cap',
            'sku' => 'HOME-CAT-001',
            'price' => 49.00,
            'stock' => 8,
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-home-category-products="traditional-product-row"', false)
            ->assertSee('data-home-category-product="homepage-traditional-driver-cap"', false)
            ->assertSeeText('Homepage Traditional Driver Cap')
            ->assertSee(route('product', $product), false)
            ->assertSee(route('category', ['category' => $root->slug]), false);
    }

    public function test_homepage_product_card_css_preserves_the_full_product_image(): void
    {
        $css = file_get_contents(public_path('css/home-collections.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('object-fit:contain !important', $css);
        $this->assertStringContainsString('aspect-ratio:4 / 3', $css);
        $this->assertStringContainsString('.home-page .home-product-card .home-product-media.home-managed-media--product', $css);
    }

    public function test_homepage_bestsellers_use_the_best_sellers_collection_instead_of_new_arrivals(): void
    {
        $collection = ProductCollection::query()->where('slug', 'best-sellers')->first();
        if (! $collection) {
            $collection = ProductCollection::create([
                'name' => 'Best Sellers',
                'slug' => 'best-sellers',
                'type' => 'curated',
                'status' => 'active',
                'visibility' => 'visible',
                'sort_order' => 1,
            ]);
        } else {
            $collection->update(['status' => 'active', 'visibility' => 'visible']);
        }

        $collection->products()->detach();

        foreach (range(1, 6) as $index) {
            $product = Product::create([
                'name' => 'Bestseller Row '.$index,
                'slug' => 'bestseller-row-'.$index,
                'sku' => 'BEST-ROW-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'price' => 30 + $index,
                'stock' => 5,
                'status' => 'active',
                'is_active' => true,
                'is_new' => false,
            ]);

            $collection->products()->attach($product->id, ['sort_order' => $index]);
        }

        Product::create([
            'name' => 'New Arrival Fallback Must Not Render',
            'slug' => 'new-arrival-fallback-must-not-render',
            'sku' => 'NEW-FALLBACK-001',
            'price' => 99,
            'stock' => 5,
            'status' => 'active',
            'is_active' => true,
            'is_new' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('Bestseller Row 1')
            ->assertSeeText('Bestseller Row 6')
            ->assertDontSeeText('New Arrival Fallback Must Not Render')
            ->assertSee('data-home-product-count="6"', false)
            ->assertSee('home-product-carousel--static', false);
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
