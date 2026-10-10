<?php

namespace Tests\Feature;

use App\Models\{Category, ContentPage, Product, SeoRedirect, SeoSetting, User};
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
            ->assertSee(['SEO & Content', 'SEO Health Overview', 'Meta Management']);

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


    public function test_robots_txt_advertises_all_public_sitemaps_even_with_saved_legacy_rules(): void
    {
        SeoSetting::create([
            'key' => 'robots_txt',
            'value' => "User-agent: *\nAllow: /\nDisallow: /admin\n\nSitemap: http://localhost/sitemap.xml\n",
        ]);

        $this->get(route('seo.robots'))
            ->assertOk()
            ->assertSee('Sitemap: http://localhost/sitemap.xml', false)
            ->assertSee('Sitemap: http://localhost/video-sitemap.xml', false)
            ->assertSee('Sitemap: http://localhost/360-sitemap.xml', false);
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

    public function test_product_and_category_pages_publish_search_structured_data(): void
    {
        $category = Category::create([
            'name' => 'FIFA',
            'slug' => 'fifa',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Ireland Heritage Cap',
            'slug' => 'ireland-heritage-cap',
            'sku' => 'SEO-IRE-001',
            'description' => null,
            'price' => 75,
            'stock' => 8,
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->get(route('product', $product))
            ->assertOk()
            ->assertSee('<title>Ireland Heritage Cap | Emerald Rozalia</title>', false)
            ->assertSee('name="robots" content="index,follow"', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"priceCurrency":"EUR"', false)
            ->assertSee('"availability":"https://schema.org/InStock"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        $this->get(route('category', $category))
            ->assertOk()
            ->assertSee('<title>FIFA Hats &amp; Caps | Emerald Rozalia</title>', false)
            ->assertSee('name="robots" content="index,follow"', false)
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }



    public function test_thin_product_and_category_copy_gets_useful_meta_descriptions(): void
    {
        $category = Category::create([
            'name' => 'Corporate Beanies',
            'slug' => 'corporate-beanies',
            'description' => 'Premium beanies.',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Ivory Feather Occasion Hat',
            'slug' => 'ivory-feather-occasion-hat-er-gfh-016',
            'sku' => 'ER-GFH-016',
            'description' => '100% Irish wool',
            'material' => 'Irish wool',
            'price' => 75,
            'stock' => 5,
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->get(route('product', $product))
            ->assertOk()
            ->assertSee('Made with Irish wool.', false)
            ->assertSee('Limerick, Ireland.', false);

        $this->get(route('category', $category))
            ->assertOk()
            ->assertSee('Shop Corporate Beanies hats, caps and headwear from Emerald Rozalia.', false);
    }




    public function test_sitemap_excludes_private_login_required_and_future_managed_pages(): void
    {
        ContentPage::create([
            'title' => 'Public Page',
            'slug' => 'public-page',
            'body' => '<h1>Public Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'published_at' => now(),
        ]);

        ContentPage::create([
            'title' => 'Private Page',
            'slug' => 'private-page',
            'body' => '<h1>Private Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'private']],
            'published_at' => now(),
        ]);

        ContentPage::create([
            'title' => 'Members Page',
            'slug' => 'members-page',
            'body' => '<h1>Members Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['login_required' => true]],
            'published_at' => now(),
        ]);

        ContentPage::create([
            'title' => 'Future Page',
            'slug' => 'future-page',
            'body' => '<h1>Future Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'scheduled_for' => now()->addDay(),
        ]);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee('/public-page', false)
            ->assertDontSee('/private-page', false)
            ->assertDontSee('/members-page', false)
            ->assertDontSee('/future-page', false);
    }




    public function test_generic_page_aliases_use_clean_public_canonicals(): void
    {
        $aliases = [
            '/page/collections' => '/collections',
            '/page/virtual-tryon' => '/virtual-tryon',
            '/page/size-guide' => '/size-guide',
            '/page/shipping-delivery' => '/shipping-delivery',
            '/page/privacy-policy' => '/privacy-policy',
            '/page/terms-conditions' => '/terms-conditions',
        ];

        foreach ($aliases as $from => $canonical) {
            $this->get($from)
                ->assertOk()
                ->assertSee('<link rel="canonical" href="http://localhost'.$canonical.'">', false);
        }
    }

    public function test_store_owner_alias_uses_franchise_canonical_without_changing_route_contract(): void
    {
        $this->get('/be-a-store-owner')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/franchise">', false);
    }

    public function test_help_and_legal_sitemap_urls_resolve_on_canonical_routes(): void
    {
        $this->get('/size-guide')->assertOk();
        $this->get('/shipping-delivery')->assertOk();
        $this->get('/privacy-policy')->assertOk();
        $this->get('/terms-conditions')->assertOk();
    }

    public function test_public_help_and_legal_pages_are_in_main_sitemap(): void
    {
        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee('<loc>http://localhost/size-guide</loc>', false)
            ->assertSee('<loc>http://localhost/shipping-delivery</loc>', false)
            ->assertSee('<loc>http://localhost/privacy-policy</loc>', false)
            ->assertSee('<loc>http://localhost/terms-conditions</loc>', false);
    }

    public function test_public_sitemap_excludes_draft_products_and_hidden_categories(): void
    {
        $visibleCategory = Category::create([
            'name' => 'Visible Category',
            'slug' => 'visible-category',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Category::create([
            'name' => 'Hidden Category',
            'slug' => 'hidden-category',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => false,
        ]);

        $published = Product::create([
            'category_id' => $visibleCategory->id,
            'name' => 'Published Cap',
            'slug' => 'published-cap',
            'sku' => 'PUB-001',
            'price' => 75,
            'stock' => 3,
            'status' => 'published',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $visibleCategory->id,
            'name' => 'Draft Cap',
            'slug' => 'draft-cap',
            'sku' => 'DRAFT-001',
            'price' => 75,
            'stock' => 3,
            'status' => 'draft',
            'is_active' => true,
        ]);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee('/category/visible-category', false)
            ->assertSee('/product/'.$published->slug, false)
            ->assertDontSee('/category/hidden-category', false)
            ->assertDontSee('/product/draft-cap', false);
    }

    public function test_public_sitemap_uses_current_categories_even_when_saved_snapshot_is_stale(): void
    {
        SeoSetting::create([
            'key' => 'sitemap_xml',
            'value' => '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>',
        ]);

        $category = Category::create([
            'name' => 'FIFA',
            'slug' => 'fifa',
            'description' => 'International football headwear.',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertSee('<loc>'.route('category', $category).'</loc>', false);
    }



    public function test_legacy_home_alias_redirects_to_canonical_homepage(): void
    {
        $this->get('/home')
            ->assertStatus(301)
            ->assertRedirect('/');
    }

    public function test_search_console_legacy_404_urls_redirect_to_live_public_pages(): void
    {
        $redirects = [
            '/category/sports-training-caps' => '/shop',
            '/category/workwear-warehouse-team-caps' => '/shop',
            '/category/limerick-heritage-collection' => '/irish-heritage',
            '/category/heritage-baseball-caps' => '/irish-heritage',
            '/category/winter-fleece-hats' => '/shop',
            '/category/8-panel-caps' => '/shop',
            '/category/snapbacks' => '/shop',
            '/category/kids-beanies' => '/shop',
            '/category/classic-6-panel-caps' => '/shop',
            '/category/bucket-hats' => '/shop',
            '/category/classic-fashion-caps' => '/shop',
            '/product/baseball-cap' => '/shop',
            '/category/costume-party-hats' => '/shop',
        ];

        foreach ($redirects as $from => $to) {
            $this->get($from)
                ->assertStatus(301)
                ->assertRedirect($to);
        }
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

    public function test_broken_link_check_persists_a_reviewable_issue(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = ContentPage::create([
            'title' => 'Broken Link Page',
            'slug' => 'broken-link-page',
            'body' => '<h1>Broken Link Page</h1><p><a href="/missing-page">Missing page</a></p>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'navigation_visible' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.links'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'content']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'broken-internal-link',
            'status' => 'open',
        ]);
    }

    public function test_page_seo_fixes_can_add_alt_text_and_an_internal_link(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = ContentPage::create([
            'title' => 'Image Page',
            'slug' => 'image-page',
            'body' => '<img src="/storage/image.webp"><p>Page copy.</p>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.seo.audit'))->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.seo.issue.fix', ['page', $page->id, 'missing-alt-text']))
            ->assertRedirect();
        $this->assertStringContainsString('alt="Image Page"', (string) $page->fresh()->body);

        $page->update(['body' => '<h1>Image Page</h1>']);
        $this->actingAs($admin)
            ->post(route('admin.seo.issue.fix', ['page', $page->id, 'missing-internal-links']))
            ->assertRedirect();
        $this->assertStringContainsString('href="/shop"', (string) $page->fresh()->body);
    }
}
