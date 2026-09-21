<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_category_dashboard_and_create_hierarchy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee(['Categories', 'Category Tree', 'UUID Traceability', 'Edit', 'Delete', 'Delete All'])
            ->assertSee('data-edit-selected', false)
            ->assertSee('data-delete-selected', false)
            ->assertSee('data-delete-all', false);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Caps',
                'slug' => 'caps',
                'parent_id' => null,
                'status' => 'active',
                'is_visible' => 1,
                'sort_order' => 1,
                'description' => 'Premium caps.',
                'meta_title' => 'Premium Caps | Emerald Rozalia',
                'meta_description' => 'Premium Emerald Rozalia caps.',
            ])
            ->assertRedirect();

        $parent = Category::query()->where('slug', 'caps')->firstOrFail();
        $this->assertNotNull($parent->public_uuid);
        $this->assertSame($admin->id, $parent->created_by);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Baseball Caps',
                'slug' => 'baseball-caps',
                'parent_id' => $parent->id,
                'status' => 'active',
                'is_visible' => 1,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $child = Category::query()->where('slug', 'baseball-caps')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.created', 'subject_id' => $child->id]);
    }

    public function test_visibility_and_status_control_public_category_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create([
            'name' => 'Irish Hats',
            'slug' => 'irish-hats',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('category', $category))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.categories.visibility', $category))
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_visible);
        $this->get('/category/irish-hats')->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.categories.update', $category), [
                'name' => 'Irish Hats',
                'slug' => 'irish-hats',
                'parent_id' => null,
                'status' => 'draft',
                'is_visible' => 1,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $category->refresh();
        $this->assertSame('draft', $category->status);
        $this->assertFalse($category->is_active);
        $this->get('/category/irish-hats')->assertNotFound();
    }

    public function test_admin_can_delete_selected_categories_and_delete_all_categories(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $parent = Category::create([
            'name' => 'Parent Category',
            'slug' => 'parent-category',
            'status' => 'active',
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $child = Category::create([
            'name' => 'Child Category',
            'slug' => 'child-category',
            'parent_id' => $parent->id,
            'status' => 'active',
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $keep = Category::create([
            'name' => 'Keep Category',
            'slug' => 'keep-category',
            'status' => 'active',
            'is_visible' => true,
            'sort_order' => 2,
        ]);
        $product = Product::create([
            'category_id' => $parent->id,
            'name' => 'Mapped Category Cap',
            'slug' => 'mapped-category-cap',
            'sku' => 'ER-CATEGORY-DELETE-001',
            'price' => 39.00,
            'stock' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.bulk-destroy'), [
                'mode' => 'selected',
                'categories' => [$parent->public_uuid],
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $parent->id]);
        $this->assertDatabaseHas('categories', ['id' => $child->id, 'parent_id' => null]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
        $this->assertDatabaseHas('categories', ['id' => $keep->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.deleted', 'subject_id' => $parent->id]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.bulk-destroy'), ['mode' => 'all'])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
    }

    public function test_single_category_delete_unmaps_products_and_promotes_children(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $parent = Category::create([
            'name' => 'Delete Parent',
            'slug' => 'delete-parent',
            'status' => 'active',
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $child = Category::create([
            'name' => 'Promoted Child',
            'slug' => 'promoted-child',
            'parent_id' => $parent->id,
            'status' => 'active',
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $product = Product::create([
            'category_id' => $parent->id,
            'name' => 'Delete Parent Product',
            'slug' => 'delete-parent-product',
            'sku' => 'ER-CATEGORY-DELETE-002',
            'price' => 29.00,
            'stock' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $parent))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $parent->id]);
        $this->assertDatabaseHas('categories', ['id' => $child->id, 'parent_id' => null]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
    }

    public function test_admin_can_bulk_update_reorder_export_and_delete_empty_categories(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = Category::create(['name' => 'Caps', 'slug' => 'caps', 'status' => 'active', 'is_visible' => true, 'sort_order' => 1]);
        $second = Category::create(['name' => 'Hats', 'slug' => 'hats', 'status' => 'active', 'is_visible' => true, 'sort_order' => 2]);

        $this->actingAs($admin)
            ->postJson(route('admin.categories.reorder'), ['order' => [$second->public_uuid, $first->public_uuid]])
            ->assertNoContent();

        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(1, $second->fresh()->sort_order);

        $this->actingAs($admin)
            ->post(route('admin.categories.bulk'), [
                'categories' => [$first->public_uuid, $second->public_uuid],
                'status' => 'draft',
                'visibility' => 'hidden',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', ['id' => $first->id, 'status' => 'draft', 'is_visible' => false]);
        $this->assertDatabaseHas('categories', ['id' => $second->id, 'status' => 'draft', 'is_visible' => false]);

        $this->actingAs($admin)
            ->get(route('admin.categories.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $second))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $second->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.deleted', 'subject_id' => $second->id]);
    }
}
