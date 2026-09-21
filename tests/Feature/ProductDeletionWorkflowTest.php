<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeletionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_manager_exposes_safe_delete_restore_and_permanent_delete_workflow(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Delete Workflow Cap',
            'slug' => 'delete-workflow-cap',
            'sku' => 'ER-DELETE-001',
            'price' => 34.99,
            'stock' => 12,
            'brand' => 'Emerald Rozalia',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.resource', ['module' => 'product-manager']))
            ->assertOk()
            ->assertSee('Trash')
            ->assertSee('data-product-delete-actions', false)
            ->assertSee('pm-action-menu-portal', false)
            ->assertSee('Delete product')
            ->assertSee('Delete Selected')
            ->assertSee('Delete All')
            ->assertSee('data-delete-selected', false)
            ->assertSee('data-delete-all', false)
            ->assertSee('Delete Workflow Cap')
            ->assertDontSee('pm-delete-product', false);

        $this->actingAs($admin)
            ->delete(route('admin.product-manager.destroy', ['product' => $product->id]))
            ->assertRedirect(route('admin.resource', ['module' => 'product-manager']));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.trashed',
            'subject_id' => $product->id,
        ]);
        $this->get(route('product', ['product' => $product->slug]))->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.resource', ['module' => 'product-manager', 'tab' => 'trash']))
            ->assertOk()
            ->assertSee('Trash (1)')
            ->assertSee('Restore product')
            ->assertSee('Permanently delete')
            ->assertSee('Delete Workflow Cap');

        $this->actingAs($admin)
            ->post(route('admin.product-manager.restore', ['product' => $product->id]))
            ->assertRedirect(route('admin.resource', ['module' => 'product-manager', 'tab' => 'trash']));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.restored',
            'subject_id' => $product->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.product-manager.destroy', ['product' => $product->id]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('admin.product-manager.permanent-destroy', ['product' => $product->id]))
            ->assertRedirect(route('admin.resource', ['module' => 'product-manager', 'tab' => 'trash']));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.permanently_deleted',
            'subject_id' => $product->id,
        ]);
    }

    public function test_product_manager_can_bulk_delete_selected_products_and_then_delete_all_remaining_products(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $first = Product::create([
            'name' => 'Bulk Delete Cap One',
            'slug' => 'bulk-delete-cap-one',
            'sku' => 'ER-BULK-DELETE-001',
            'price' => 25.00,
            'stock' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);
        $second = Product::create([
            'name' => 'Bulk Delete Cap Two',
            'slug' => 'bulk-delete-cap-two',
            'sku' => 'ER-BULK-DELETE-002',
            'price' => 30.00,
            'stock' => 7,
            'status' => 'active',
            'is_active' => true,
        ]);
        $third = Product::create([
            'name' => 'Bulk Delete Cap Three',
            'slug' => 'bulk-delete-cap-three',
            'sku' => 'ER-BULK-DELETE-003',
            'price' => 35.00,
            'stock' => 9,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.product-manager.bulk-destroy'), [
                'mode' => 'selected',
                'products' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('admin.resource', ['module' => 'product-manager']));

        $this->assertSoftDeleted('products', ['id' => $first->id]);
        $this->assertSoftDeleted('products', ['id' => $second->id]);
        $this->assertDatabaseHas('products', ['id' => $third->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.trashed',
            'subject_id' => $first->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.trashed',
            'subject_id' => $second->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.product-manager.bulk-destroy'), [
                'mode' => 'all',
            ])
            ->assertRedirect(route('admin.resource', ['module' => 'product-manager']));

        $this->assertSoftDeleted('products', ['id' => $third->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.trashed',
            'subject_id' => $third->id,
        ]);
    }
}
