<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_assign_products_to_a_collection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $firstProduct = Product::create(['name' => 'Emerald Cap', 'slug' => 'emerald-cap', 'sku' => 'ER-CAP-001', 'price' => 34.99, 'stock' => 12, 'status' => 'active', 'is_active' => true]);
        $secondProduct = Product::create(['name' => 'Rozalia Flat Cap', 'slug' => 'rozalia-flat-cap', 'sku' => 'ER-CAP-002', 'price' => 44.99, 'stock' => 8, 'status' => 'active', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.collections.index'))
            ->assertOk()
            ->assertSee(['Collections', 'Collection Summary', 'Add Collection'])
            ->assertDontSee('Add Record');

        $this->actingAs($admin)
            ->post(route('admin.collections.store'), [
                'name' => 'Spring Edit',
                'type' => 'seasonal',
                'season' => 'Spring / Summer 2026',
                'status' => 'active',
                'visibility' => 'visible',
                'sort_order' => 1,
                'is_featured' => 1,
                'allow_in_filters' => 1,
            ])
            ->assertRedirect();

        $collection = ProductCollection::query()->where('slug', 'spring-edit')->firstOrFail();
        $this->assertTrue($collection->is_featured);
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.created', 'subject_id' => $collection->id]);

        $this->actingAs($admin)
            ->post(route('admin.collections.products.sync', $collection), ['product_ids' => [$firstProduct->id, $secondProduct->id]])
            ->assertRedirect();

        $this->assertCount(2, $collection->fresh()->products);

        $this->actingAs($admin)
            ->put(route('admin.collections.update', $collection), [
                'name' => 'Spring Edit Updated',
                'type' => 'seasonal',
                'season' => 'Spring / Summer 2026',
                'status' => 'active',
                'visibility' => 'hidden',
                'sort_order' => 2,
                'allow_in_filters' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('Spring Edit Updated', $collection->fresh()->name);
        $this->assertSame('hidden', $collection->fresh()->visibility);
    }
}
