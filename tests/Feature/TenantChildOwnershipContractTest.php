<?php

namespace Tests\Feature;

use App\Models\{Company, ContentPage, Order, OrderItem, Product, ProductMedia, ProductVariant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantChildOwnershipContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_domain_tables_have_company_foreign_keys(): void
    {
        foreach ([
            'addresses', 'product_variants', 'product_media', 'variant_media', 'product_spins',
            'spin_visits', 'try_on_assets', 'try_on_visits', 'video_plays', 'order_items',
            'inventory_movements', 'wishlists', 'reviews', 'returns', 'reward_transactions',
            'customer_groups', 'customer_segments', 'customer_profiles', 'content_pages',
            'page_sections', 'page_revisions', 'banner_revisions', 'media_asset_versions',
            'franchise_milestones', 'conversation_messages', 'integration_connections',
            'audit_logs', 'automation_rules', 'backup_runs',
        ] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'company_id'), "{$table}.company_id is required.");
            $this->assertTrue(collect(Schema::getForeignKeys($table))->contains(
                fn (array $foreignKey): bool => ($foreignKey['columns'] ?? []) === ['company_id']
                    && ($foreignKey['foreign_table'] ?? null) === 'companies'
                    && ($foreignKey['foreign_columns'] ?? []) === ['id'],
            ), "{$table}.company_id must reference companies.id.");
        }
    }

    public function test_parent_company_is_inherited_and_global_scope_isolation_applies_to_children(): void
    {
        $first = Company::create(['name' => 'First Tenant', 'code' => 'CHILD-FIRST', 'active' => true]);
        $second = Company::create(['name' => 'Second Tenant', 'code' => 'CHILD-SECOND', 'active' => true]);
        $firstProduct = Product::create([
            'company_id' => $first->id,
            'name' => 'First Tenant Cap',
            'slug' => 'first-tenant-cap',
            'sku' => 'CHILD-FIRST-001',
            'price' => 30,
            'stock' => 4,
            'is_active' => true,
        ]);
        $secondProduct = Product::create([
            'company_id' => $second->id,
            'name' => 'Second Tenant Cap',
            'slug' => 'second-tenant-cap',
            'sku' => 'CHILD-SECOND-001',
            'price' => 30,
            'stock' => 4,
            'is_active' => true,
        ]);

        $firstVariant = ProductVariant::create([
            'product_id' => $firstProduct->id,
            'sku' => 'CHILD-FIRST-001-A',
            'price' => 30,
            'stock' => 2,
            'is_active' => true,
        ]);
        $secondVariant = ProductVariant::create([
            'product_id' => $secondProduct->id,
            'sku' => 'CHILD-SECOND-001-A',
            'price' => 30,
            'stock' => 2,
            'is_active' => true,
        ]);

        $this->assertSame($first->id, $firstVariant->company_id);
        $this->assertSame($second->id, $secondVariant->company_id);

        $this->withSession(['company_id' => $first->id]);
        $this->assertSame(['CHILD-FIRST-001-A'], ProductVariant::query()->pluck('sku')->all());

        $this->withSession(['company_id' => $second->id]);
        $this->assertSame(['CHILD-SECOND-001-A'], ProductVariant::query()->pluck('sku')->all());
    }

    public function test_order_items_and_public_product_media_cannot_cross_company_boundaries(): void
    {
        $first = Company::create(['name' => 'Order Tenant', 'code' => 'CHILD-ORDER', 'active' => true]);
        $second = Company::create(['name' => 'Media Tenant', 'code' => 'CHILD-MEDIA', 'active' => true]);
        $firstProduct = Product::create([
            'company_id' => $first->id,
            'name' => 'Order Tenant Cap',
            'slug' => 'order-tenant-cap',
            'sku' => 'CHILD-ORDER-001',
            'price' => 30,
            'stock' => 4,
            'is_active' => true,
        ]);
        $secondProduct = Product::create([
            'company_id' => $second->id,
            'name' => 'Media Tenant Cap',
            'slug' => 'media-tenant-cap',
            'sku' => 'CHILD-MEDIA-001',
            'price' => 30,
            'stock' => 4,
            'is_active' => true,
        ]);
        $order = Order::create([
            'company_id' => $first->id,
            'number' => 'CHILD-ORDER-001',
            'status' => 'processing',
            'payment_status' => 'pending',
            'subtotal' => 30,
            'shipping' => 0,
            'total' => 30,
            'currency' => 'EUR',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $firstProduct->id,
            'name' => $firstProduct->name,
            'sku' => $firstProduct->sku,
            'quantity' => 1,
            'unit_price' => 30,
            'total' => 30,
        ]);
        Storage::fake('public');
        Storage::disk('public')->put('tenant-media/second.webp', 'second');
        $media = ProductMedia::create([
            'product_id' => $secondProduct->id,
            'type' => 'image',
            'disk' => 'public',
            'path' => 'tenant-media/second.webp',
            'mime_type' => 'image/webp',
            'active' => true,
            'approval_status' => 'approved',
        ]);

        $this->assertSame($first->id, $item->company_id);
        $this->assertSame($second->id, $media->company_id);

        $this->withSession(['company_id' => $first->id]);
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'company_id' => $first->id]);
        $this->get(route('media.public', $media->uuid))->assertNotFound();

        $this->withSession(['company_id' => $second->id]);
        $this->get(route('media.public', $media->uuid))->assertOk();
    }

    public function test_content_pages_and_explicit_for_company_scope_are_tenant_safe(): void
    {
        $first = Company::create(['name' => 'Page Tenant One', 'code' => 'CHILD-PAGE-1', 'active' => true]);
        $second = Company::create(['name' => 'Page Tenant Two', 'code' => 'CHILD-PAGE-2', 'active' => true]);
        $one = ContentPage::create([
            'company_id' => $first->id,
            'title' => 'Tenant One Page',
            'slug' => 'tenant-one-page',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
        ]);
        $two = ContentPage::create([
            'company_id' => $second->id,
            'title' => 'Tenant Two Page',
            'slug' => 'tenant-two-page',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
        ]);

        $this->withSession(['company_id' => $first->id]);
        $this->assertSame([$one->id], ContentPage::query()->whereIn('id', [$one->id, $two->id])->pluck('id')->all());
        $this->get(route('content.page', ['page' => $two->slug]))->assertNotFound();

        $this->app['session']->forget('company_id');
        $this->assertSame([$one->id], ContentPage::forCompany($first->id)->pluck('id')->all());
        $this->assertSame([$two->id], ContentPage::forCompany($second->id)->pluck('id')->all());
    }
}
