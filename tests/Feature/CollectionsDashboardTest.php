<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            ->assertSee(['Collections', 'Collection Summary', 'Quick Actions', 'UUID Traceability', 'Import Collections', 'Export Collections'])
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
        $this->assertNotEmpty($collection->public_uuid);
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

        $this->actingAs($admin)
            ->patch(route('admin.collections.settings', $collection), [
                'status' => 'draft',
                'visibility' => 'visible',
                'show_on_homepage' => 1,
            ])
            ->assertRedirect();

        $collection->refresh();
        $this->assertSame('draft', $collection->status);
        $this->assertSame('visible', $collection->visibility);
        $this->assertTrue($collection->show_on_homepage);
        $this->assertFalse($collection->is_featured);
    }

    public function test_bulk_reorder_export_and_audit_are_functional(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = ProductCollection::create(['name' => 'First', 'slug' => 'first', 'type' => 'curated', 'status' => 'active', 'visibility' => 'visible', 'sort_order' => 20]);
        $second = ProductCollection::create(['name' => 'Second', 'slug' => 'second', 'type' => 'occasion', 'status' => 'active', 'visibility' => 'visible', 'sort_order' => 10]);

        $this->actingAs($admin)
            ->post(route('admin.collections.bulk'), [
                'collections' => [$first->id, $second->id],
                'status' => 'draft',
                'visibility' => 'hidden',
                'featured' => 'yes',
            ])
            ->assertRedirect();

        $this->assertSame('draft', $first->fresh()->status);
        $this->assertSame('hidden', $second->fresh()->visibility);
        $this->assertTrue($first->fresh()->is_featured);

        $this->actingAs($admin)
            ->post(route('admin.collections.reorder'), ['order' => [$first->id, $second->id]])
            ->assertRedirect(route('admin.collections.index'));

        $this->assertSame(1, (int) $first->fresh()->sort_order);
        $this->assertSame(2, (int) $second->fresh()->sort_order);

        $this->actingAs($admin)
            ->get(route('admin.collections.audit', $first))
            ->assertOk()
            ->assertSee(['Collection Audit Log', $first->public_uuid, 'Bulk']);

        $this->actingAs($admin)
            ->get(route('admin.collections.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_csv_import_add_by_category_and_product_csv_import_work(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Baseball Caps', 'slug' => 'baseball-caps', 'status' => 'active', 'is_active' => true, 'is_visible' => true, 'sort_order' => 1]);
        $firstProduct = Product::create(['category_id' => $category->id, 'name' => 'Emerald Baseball Cap', 'slug' => 'emerald-baseball-cap', 'sku' => 'ER-CAP-101', 'price' => 39.99, 'stock' => 10, 'status' => 'active', 'is_active' => true]);
        $secondProduct = Product::create(['name' => 'Heritage Cap', 'slug' => 'heritage-cap', 'sku' => 'ER-CAP-102', 'price' => 49.99, 'stock' => 6, 'status' => 'active', 'is_active' => true]);

        $collectionCsv = UploadedFile::fake()->createWithContent(
            'collections.csv',
            "name,type,status,visibility,sort_order,is_featured\nIrish Summer,seasonal,active,visible,3,1\n"
        );

        $this->actingAs($admin)
            ->post(route('admin.collections.import'), ['file' => $collectionCsv])
            ->assertRedirect(route('admin.collections.index'));

        $collection = ProductCollection::query()->where('slug', 'irish-summer')->firstOrFail();
        $this->assertTrue($collection->is_featured);

        $this->actingAs($admin)
            ->post(route('admin.collections.products.category', $collection), ['category_id' => $category->id])
            ->assertRedirect();

        $this->assertTrue($collection->fresh()->products->contains($firstProduct->id));

        $productCsv = UploadedFile::fake()->createWithContent('products.csv', "sku,sort_order\nER-CAP-102,9\n");
        $this->actingAs($admin)
            ->post(route('admin.collections.products.import', $collection), ['file' => $productCsv])
            ->assertRedirect();

        $collection->refresh();
        $this->assertTrue($collection->products->contains($secondProduct->id));
        $this->assertSame(9, (int) $collection->products->firstWhere('id', $secondProduct->id)->pivot->sort_order);
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.products.csv_imported', 'subject_id' => $collection->id]);
    }
}
