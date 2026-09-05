<?php

namespace Tests\Feature;

use App\Models\{Product, SeoRedirect, SeoSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_seo_dashboard_and_run_an_audit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Audit Cap',
            'slug' => 'audit-cap',
            'sku' => 'AUDIT-001',
            'description' => 'A test product for the SEO audit.',
            'price' => 29.99,
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.seo.dashboard'))
            ->assertOk()
            ->assertSee(['SEO & Content', 'SEO Health Overview', 'Meta Management'], false);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        $this->assertDatabaseHas('seo_audits', ['created_by' => $admin->id]);
        $this->assertDatabaseHas('seo_issues', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'issue_type' => 'missing-meta-title',
            'status' => 'open',
        ]);
    }

    public function test_saved_metadata_is_published_to_the_storefront(): void
    {
        SeoSetting::create([
            'key' => 'home_meta',
            'value' => [
                'title' => 'Irish Headwear Made in Limerick',
                'description' => 'Discover Emerald Rozalia headwear made in Ireland.',
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>Irish Headwear Made in Limerick</title>', false)
            ->assertSee('name="robots" content="index,follow"', false)
            ->assertSee('application/ld+json', false);
    }

    public function test_admin_can_save_product_metadata_and_publish_seo_files(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::create([
            'name' => 'Signature Cap',
            'slug' => 'signature-cap',
            'sku' => 'SIG-001',
            'description' => 'A premium test cap.',
            'price' => 34.99,
            'stock' => 8,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.seo.meta.update', ['product', $product->id]), [
                'meta_title' => 'Signature Cap | Emerald Rozalia',
                'meta_description' => 'A premium Irish-made signature cap from Emerald Rozalia.',
                'focus_keyword' => 'signature cap ireland',
            ])
            ->assertRedirect();

        $this->assertSame('Signature Cap | Emerald Rozalia', $product->fresh()->meta_title);
        $this->assertSame('signature cap ireland', $product->fresh()->seo['focus_keyword']);

        $this->get(route('product', $product))
            ->assertOk()
            ->assertSee('<title>Signature Cap | Emerald Rozalia</title>', false);

        $this->actingAs($admin)
            ->post(route('admin.seo.sitemap.generate'))
            ->assertRedirect();

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee('/product/signature-cap', false);

        $this->actingAs($admin)
            ->post(route('admin.seo.robots.update'), ['robots_txt' => "User-agent: *\nDisallow: /private\n"])
            ->assertRedirect();

        $this->get(route('seo.robots'))
            ->assertOk()
            ->assertSee('Disallow: /private', false);
    }

    public function test_redirect_manager_enforces_an_active_redirect(): void
    {
        SeoRedirect::create([
            'from_path' => '/legacy-hats',
            'to_path' => '/shop',
            'status_code' => 301,
            'active' => true,
        ]);

        $this->get('/legacy-hats')->assertRedirect('/shop')->assertStatus(301);
    }
}
