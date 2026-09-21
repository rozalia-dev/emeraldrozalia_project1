<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCategoryCollectionArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_header_exposes_categories_under_shop_and_collections_under_collections(): void
    {
        $category = Category::create([
            'name' => 'Baseball Caps',
            'slug' => 'baseball-caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $childCategory = Category::create([
            'parent_id' => $category->id,
            'name' => 'Caps',
            'slug' => 'baseball-caps-caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Classic Emerald Cap',
            'slug' => 'classic-emerald-cap',
            'sku' => 'ER-ARCH-001',
            'price' => 34.99,
            'stock' => 10,
            'status' => 'published',
            'is_active' => true,
        ]);

        $collection = ProductCollection::create([
            'name' => 'Irish Heritage Edit',
            'slug' => 'irish-heritage-edit',
            'type' => 'curated',
            'status' => 'active',
            'visibility' => 'visible',
            'sort_order' => 1,
        ]);
        $collection->products()->attach($product->id, ['sort_order' => 1]);

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('data-catalog-nav="categories"', false)
            ->assertSee('<a href="'.route('category', ['category' => $category->slug]).'"><span>Baseball Caps</span></a>', false)
            ->assertDontSee('<a href="'.route('shop').'">SHOP ALL</a>', false)
            ->assertDontSee(route('category', ['category' => $childCategory->slug]), false)
            ->assertSee('data-catalog-nav="collections"', false)
            ->assertSee('Irish Heritage Edit');
    }

    public function test_collections_index_and_detail_use_real_collection_assignments_not_category_slugs(): void
    {
        $category = Category::create([
            'name' => 'Bucket Hats',
            'slug' => 'bucket-hats',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $assigned = Product::create([
            'category_id' => $category->id,
            'name' => 'Heritage Bucket Hat',
            'slug' => 'heritage-bucket-hat',
            'sku' => 'ER-ARCH-002',
            'price' => 44.99,
            'stock' => 12,
            'status' => 'published',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Unassigned Bucket Hat',
            'slug' => 'unassigned-bucket-hat',
            'sku' => 'ER-ARCH-003',
            'price' => 39.99,
            'stock' => 8,
            'status' => 'published',
            'is_active' => true,
        ]);

        $collection = ProductCollection::create([
            'name' => 'Autumn Heritage',
            'slug' => 'autumn-heritage',
            'description' => 'A curated cross-category heritage collection.',
            'type' => 'seasonal',
            'status' => 'active',
            'visibility' => 'visible',
            'sort_order' => 2,
        ]);
        $collection->products()->attach($assigned->id, ['sort_order' => 1]);

        $this->get(route('collections'))
            ->assertOk()
            ->assertSee('Autumn Heritage')
            ->assertSee(route('collection.show', ['collection' => 'autumn-heritage']), false);

        $this->get(route('collection.show', ['collection' => 'autumn-heritage']))
            ->assertOk()
            ->assertSee('AUTUMN HERITAGE')
            ->assertSee('Heritage Bucket Hat')
            ->assertDontSee('Unassigned Bucket Hat');
    }

    public function test_hidden_or_draft_collections_are_not_publicly_navigable(): void
    {
        $hidden = ProductCollection::create([
            'name' => 'Private Edit',
            'slug' => 'private-edit',
            'type' => 'curated',
            'status' => 'draft',
            'visibility' => 'hidden',
            'sort_order' => 3,
        ]);

        $this->get(route('shop'))
            ->assertOk()
            ->assertDontSee('Private Edit');

        $this->get(route('collection.show', ['collection' => $hidden->slug]))
            ->assertNotFound();
    }
}
