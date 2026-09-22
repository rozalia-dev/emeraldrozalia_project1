<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAutoCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_sku_and_hs_code_are_generated_automatically(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $root = Category::create([
            'name' => 'Traditional',
            'slug' => 'traditional-auto-code',
            'taxonomy_type' => 'traditional',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $caps = Category::create([
            'parent_id' => $root->id,
            'name' => 'Caps',
            'slug' => 'traditional-auto-code-caps',
            'taxonomy_type' => 'traditional',
            'product_type' => 'caps',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.add-product.store'), [
                'name' => 'Auto Code Cap',
                'short_description' => 'Product with automatic SKU and HS code.',
                'slug' => 'auto-code-cap',
                'sku' => '',
                'hs_code' => '',
                'category_root_id' => $root->id,
                'category_id' => $caps->id,
                'brand' => 'Emerald Rozalia',
                'product_type' => 'simple',
                'tax_class' => 'standard',
                'description' => 'Automatic code generation test product.',
                'price' => 49.00,
                'vat_rate' => 23,
                'currency' => 'EUR',
                'stock' => 10,
                'status' => 'active',
                'save_action' => 'save',
            ])
            ->assertRedirect(route('admin.resource', 'product-manager'))
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('slug', 'auto-code-cap')->firstOrFail();

        $this->assertMatchesRegularExpression('/^ER-TRD-CAP-\d{6}$/', $product->sku);
        $this->assertSame(sprintf('ER-TRD-CAP-%06d', $product->id), $product->sku);
        $this->assertSame('650500', $product->hs_code);
    }

    public function test_manual_sku_and_hs_code_override_are_preserved(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $root = Category::create([
            'name' => 'Heritage',
            'slug' => 'heritage-auto-code',
            'taxonomy_type' => 'heritage',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $hats = Category::create([
            'parent_id' => $root->id,
            'name' => 'Hats',
            'slug' => 'heritage-auto-code-hats',
            'taxonomy_type' => 'heritage',
            'product_type' => 'hats',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.add-product.store'), [
                'name' => 'Manual Code Hat',
                'short_description' => 'Product with manual code overrides.',
                'slug' => 'manual-code-hat',
                'sku' => 'ER-CUSTOM-SKU-001',
                'hs_code' => '650699',
                'category_root_id' => $root->id,
                'category_id' => $hats->id,
                'brand' => 'Emerald Rozalia',
                'product_type' => 'simple',
                'tax_class' => 'standard',
                'description' => 'Manual code override test product.',
                'price' => 59.00,
                'vat_rate' => 23,
                'currency' => 'EUR',
                'stock' => 5,
                'status' => 'active',
                'save_action' => 'save',
            ])
            ->assertRedirect(route('admin.resource', 'product-manager'))
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('slug', 'manual-code-hat')->firstOrFail();

        $this->assertSame('ER-CUSTOM-SKU-001', $product->sku);
        $this->assertSame('650699', $product->hs_code);
    }
}
