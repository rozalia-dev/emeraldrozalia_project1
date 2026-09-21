<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_product_uses_category_subcategory_and_style_dropdowns(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $traditional = Category::create([
            'name' => 'Traditional',
            'slug' => 'traditional',
            'taxonomy_type' => 'traditional',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $caps = Category::create([
            'parent_id' => $traditional->id,
            'name' => 'Caps',
            'slug' => 'traditional-caps',
            'taxonomy_type' => 'traditional',
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.add-product'))
            ->assertOk()
            ->assertSee('Category &amp; Subcategory', false)
            ->assertSee('Traditional')
            ->assertSee('Caps')
            ->assertSee('Club / City / Town')
            ->assertSee('Back to College')
            ->assertSee('Corporate Gift');

        $this->actingAs($admin)
            ->post(route('admin.add-product.store'), [
                'name' => 'Traditional Test Cap',
                'short_description' => 'Traditional cap test product.',
                'slug' => 'traditional-test-cap',
                'sku' => 'ER-CLASSIFY-001',
                'category_root_id' => $traditional->id,
                'category_id' => $caps->id,
                'catalog_style' => 'walking-cap',
                'brand' => 'Emerald Rozalia',
                'product_type' => 'simple',
                'tax_class' => 'standard',
                'description' => 'Traditional cap created through the category and subcategory workflow.',
                'price' => 49.00,
                'vat_rate' => 23,
                'currency' => 'EUR',
                'stock' => 10,
                'status' => 'active',
                'save_action' => 'save',
            ])
            ->assertRedirect(route('admin.resource', 'product-manager'));

        $product = Product::query()->where('sku', 'ER-CLASSIFY-001')->firstOrFail();

        $this->assertSame($caps->id, $product->category_id);
        $this->assertSame($traditional->id, data_get($product->product_metadata, 'catalog_classification.category_root_id'));
        $this->assertSame('walking-cap', data_get($product->product_metadata, 'catalog_classification.style'));
    }

    public function test_gaa_english_uefa_and_fifa_club_dropdowns_are_category_and_country_scoped(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ireland = CatalogCountry::query()->where('code', 'IE')->firstOrFail();

        $roots = [];
        foreach (['gaa' => 'GAA', 'english' => 'English', 'uefa' => 'UEFA', 'fifa' => 'FIFA'] as $taxonomy => $label) {
            $root = Category::create([
                'name' => $label,
                'slug' => $taxonomy,
                'taxonomy_type' => $taxonomy,
                'status' => 'active',
                'is_active' => true,
                'is_visible' => true,
                'sort_order' => count($roots) + 1,
            ]);
            Category::create([
                'parent_id' => $root->id,
                'name' => 'Caps',
                'slug' => $taxonomy.'-caps',
                'taxonomy_type' => $taxonomy,
                'product_type' => 'caps',
                'status' => 'active',
                'is_active' => true,
                'is_visible' => true,
                'sort_order' => 1,
            ]);
            $roots[$taxonomy] = $root;

            CatalogClub::create([
                'catalog_country_id' => $ireland->id,
                'catalog_county_code' => 'IE-LK',
                'governing_body' => $taxonomy,
                'name' => strtoupper($taxonomy).' Test Club',
                'slug' => $taxonomy.'-test-club',
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.add-product'))
            ->assertOk()
            ->assertSee('data-taxonomy="gaa"', false)
            ->assertSee('data-taxonomy="english"', false)
            ->assertSee('data-taxonomy="uefa"', false)
            ->assertSee('data-taxonomy="fifa"', false)
            ->assertSee('data-body="gaa"', false)
            ->assertSee('data-body="english"', false)
            ->assertSee('data-body="uefa"', false)
            ->assertSee('data-body="fifa"', false)
            ->assertSee('data-county="IE-LK"', false)
            ->assertSee('Country → County → Club Name → Subcategory')
            ->assertSee('Manage Club Master');

        $gaaCaps = Category::query()->where('slug', 'gaa-caps')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.add-product'))
            ->post(route('admin.add-product.store'), [
                'name' => 'GAA Club Required Cap',
                'short_description' => 'GAA club validation.',
                'slug' => 'gaa-club-required-cap',
                'sku' => 'ER-GAA-CLUB-001',
                'category_root_id' => $roots['gaa']->id,
                'category_id' => $gaaCaps->id,
                'catalog_country_id' => $ireland->id,
                'catalog_county_code' => 'IE-LK',
                'product_type' => 'simple',
                'tax_class' => 'standard',
                'description' => 'GAA product requiring a matching club.',
                'price' => 39.00,
                'vat_rate' => 23,
                'currency' => 'EUR',
                'stock' => 5,
                'status' => 'active',
                'save_action' => 'save',
            ])
            ->assertRedirect(route('admin.add-product'))
            ->assertSessionHasErrors('catalog_club_id');
    }

    public function test_subcategory_must_belong_to_selected_category(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $traditional = Category::create([
            'name' => 'Traditional',
            'slug' => 'traditional',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $heritage = Category::create([
            'name' => 'Heritage',
            'slug' => 'heritage',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 2,
        ]);
        $heritageCaps = Category::create([
            'parent_id' => $heritage->id,
            'name' => 'Caps',
            'slug' => 'heritage-caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.add-product'))
            ->post(route('admin.add-product.store'), [
                'name' => 'Wrong Category Cap',
                'short_description' => 'Mismatch category test.',
                'slug' => 'wrong-category-cap',
                'sku' => 'ER-CLASSIFY-002',
                'category_root_id' => $traditional->id,
                'category_id' => $heritageCaps->id,
                'product_type' => 'simple',
                'tax_class' => 'standard',
                'description' => 'Mismatch category test.',
                'price' => 29.00,
                'vat_rate' => 23,
                'currency' => 'EUR',
                'stock' => 5,
                'status' => 'active',
                'save_action' => 'save',
            ])
            ->assertRedirect(route('admin.add-product'))
            ->assertSessionHasErrors('category_id');
    }
}
