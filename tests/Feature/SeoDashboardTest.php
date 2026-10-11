<?php

namespace Tests\Feature;

use App\Models\{Category, ContentPage, Product, SeoRedirect, SeoSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoDashboardTest extends TestCase
{
    use RefreshDatabase;



    public function test_homepage_defaults_do_not_create_false_missing_meta_issues(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'home',
            'issue_type' => 'missing-meta-title',
        ]);

        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'home',
            'issue_type' => 'missing-meta-description',
        ]);
    }

    public function test_fixed_public_pages_do_not_create_false_missing_meta_issues(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'fixed',
            'issue_type' => 'missing-meta-title',
        ]);

        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'fixed',
            'issue_type' => 'missing-meta-description',
        ]);
    }






    public function test_seo_summary_page_counts_reconcile_with_indexability(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        ContentPage::create([
            'title' => 'Private Count Page',
            'slug' => 'private-count-page',
            'body' => '<h1>Private Count Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'private', 'indexable' => false]],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        $audit = \App\Models\SeoAudit::query()->latest('completed_at')->firstOrFail();
        $total = (int) data_get($audit->summary, 'total_pages', 0);
        $indexed = (int) data_get($audit->summary, 'indexed_pages', 0);
        $noindex = (int) data_get($audit->summary, 'noindex_pages', 0);

        $this->assertSame($total, $indexed + $noindex);
    }

    public function test_seo_health_score_ignores_non_indexable_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        ContentPage::create([
            'title' => 'Private Thin Page',
            'slug' => 'private-thin-page',
            'body' => '',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'private', 'indexable' => false]],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        $audit = \App\Models\SeoAudit::query()->latest('completed_at')->firstOrFail();

        $this->assertGreaterThan(0, $audit->score);
        $this->assertSame(
            data_get($audit->summary, 'indexed_pages'),
            data_get($audit->summary, 'total_pages') - data_get($audit->summary, 'noindex_pages')
        );
    }

    public function test_broken_link_audit_ignores_non_indexable_managed_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $page = ContentPage::create([
            'title' => 'Private Broken Links',
            'slug' => 'private-broken-links',
            'body' => '<h1>Private</h1><a href="/missing-private-target">Broken</a>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'private', 'indexable' => false]],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.links'))
            ->assertRedirect();

        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'broken-internal-link',
        ]);
    }





    public function test_top_content_overview_is_not_filtered_by_dashboard_search(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.seo.dashboard', [
            'q' => 'definitely-no-match-for-top-content',
        ]));

        $response->assertOk();
        $response->assertSee('Top Content');
        $response->assertSee('Home');
    }

    public function test_top_content_overview_excludes_non_indexable_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        ContentPage::create([
            'title' => 'Private Top Content Page',
            'slug' => 'private-top-content-page',
            'body' => '<h1>Private Top Content Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'private', 'indexable' => false]],
            'published_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.seo.dashboard'));
        $response->assertOk();
        $response->assertDontSee('Private Top Content Page');
    }

    public function test_meta_status_cards_ignore_non_indexable_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        ContentPage::create([
            'title' => 'Private Meta Status Page',
            'slug' => 'private-meta-status-page',
            'body' => '',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'private', 'indexable' => false]],
            'published_at' => now(),
        ]);

        $this->actingAs($admin);

        $controller = app(\App\Http\Controllers\Admin\SeoController::class);
        $sourcesMethod = new \ReflectionMethod($controller, 'sources');
        $metaStatusMethod = new \ReflectionMethod($controller, 'metaStatus');

        $sources = $sourcesMethod->invoke($controller);
        $viewData = $metaStatusMethod->invoke($controller, $sources);

        $this->assertIsArray($viewData);
        $this->assertSame($sources->where('indexed', true)->count(), (int) data_get($viewData, 'total'));
        $this->assertSame(
            (int) data_get($viewData, 'total'),
            (int) data_get($viewData, 'optimized')
                + (int) data_get($viewData, 'missing')
                + (int) data_get($viewData, 'duplicate')
                + (int) data_get($viewData, 'too_long')
        );
    }


    public function test_dashboard_hides_persisted_issues_for_non_indexable_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $page = ContentPage::create([
            'title' => 'Hidden Issue Page',
            'slug' => 'hidden-issue-page',
            'body' => '',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => true]],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        $this->assertDatabaseHas('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'status' => 'open',
        ]);

        $page->update([
            'meta' => ['settings' => ['visibility' => 'private', 'indexable' => false]],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.seo.dashboard', ['tab' => 'content']))
            ->assertOk()
            ->assertDontSee('hidden-issue-page');
    }

    public function test_existing_seo_issues_are_resolved_when_page_becomes_non_indexable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $page = ContentPage::create([
            'title' => 'Audit Transition Page',
            'slug' => 'audit-transition-page',
            'body' => '',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => true]],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        $this->assertDatabaseHas('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'missing-internal-links',
            'status' => 'open',
        ]);

        $page->update([
            'meta' => ['settings' => ['visibility' => 'private', 'indexable' => false]],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        $this->assertDatabaseHas('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'missing-internal-links',
            'status' => 'resolved',
        ]);
    }

    public function test_non_indexable_managed_pages_do_not_create_seo_audit_issues(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $draft = ContentPage::create([
            'title' => 'Draft Audit Page',
            'slug' => 'draft-audit-page',
            'body' => '',
            'status' => 'draft',
            'locale' => 'en',
            'template' => 'standard',
        ]);

        $private = ContentPage::create([
            'title' => 'Private Audit Page',
            'slug' => 'private-audit-page',
            'body' => '',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'private', 'indexable' => false]],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        foreach ([$draft, $private] as $page) {
            $this->assertDatabaseMissing('seo_issues', [
                'source_type' => 'page',
                'source_id' => $page->id,
            ]);
        }
    }

    public function test_product_and_category_fallback_metadata_do_not_create_false_audit_issues(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create([
            'name' => 'Audit Category',
            'slug' => 'audit-category',
            'description' => 'Short category copy.',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Audit Fallback Cap',
            'slug' => 'audit-fallback-cap',
            'sku' => 'AUDIT-FALLBACK-001',
            'description' => 'Short product copy.',
            'price' => 49.99,
            'stock' => 5,
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect(route('admin.seo.dashboard', ['tab' => 'overview']));

        foreach ([
            ['product', $product->id],
            ['category', $category->id],
        ] as [$sourceType, $sourceId]) {
            $this->assertDatabaseMissing('seo_issues', [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'issue_type' => 'missing-meta-title',
            ]);

            $this->assertDatabaseMissing('seo_issues', [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'issue_type' => 'missing-meta-description',
            ]);
        }
    }

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
        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'issue_type' => 'missing-meta-title',
        ]);

        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'issue_type' => 'missing-meta-description',
        ]);
    }


    public function test_public_pages_publish_complete_twitter_metadata(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('name="twitter:card" content="summary"', false)
            ->assertSee('name="twitter:title"', false)
            ->assertSee('name="twitter:description"', false);
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




    public function test_thin_product_and_category_copy_is_enriched_on_public_pages(): void
    {
        $category = Category::create([
            'name' => 'Corporate Beanies',
            'slug' => 'corporate-beanies-visible',
            'description' => 'Premium beanies.',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Irish Wool Beanie',
            'slug' => 'irish-wool-beanie-visible',
            'sku' => 'VISIBLE-SEO-001',
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
            ->assertSee('Irish headwear designed and manufactured in Limerick, Ireland.', false);

        $this->get(route('category', $category))
            ->assertOk()
            ->assertSee('Shop Corporate Beanies hats, caps and headwear from Emerald Rozalia.', false)
            ->assertSee('Designed and manufactured in Limerick, Ireland.', false);
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






    public function test_admin_page_preview_is_noindex_and_uses_public_canonical(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = ContentPage::create([
            'title' => 'Preview SEO Page',
            'slug' => 'preview-seo-page',
            'body' => '<h1>Preview SEO Page</h1>',
            'status' => 'draft',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => true]],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pages.preview', $page))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false)
            ->assertSee('<link rel="canonical" href="http://localhost/preview-seo-page">', false);
    }

    public function test_login_required_managed_page_is_noindex_when_authenticated(): void
    {
        $user = User::factory()->create();
        ContentPage::create([
            'title' => 'Members SEO Page',
            'slug' => 'members-seo-page',
            'body' => '<h1>Members SEO Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => true, 'login_required' => true]],
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/members-seo-page')
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertDontSee('/members-seo-page', false);
    }

    public function test_managed_page_indexable_setting_controls_meta_robots_and_sitemap(): void
    {
        ContentPage::create([
            'title' => 'Indexable Managed Page',
            'slug' => 'indexable-managed-page',
            'body' => '<h1>Indexable Managed Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => true]],
            'published_at' => now(),
        ]);

        ContentPage::create([
            'title' => 'Noindex Managed Page',
            'slug' => 'noindex-managed-page',
            'body' => '<h1>Noindex Managed Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => false]],
            'published_at' => now(),
        ]);

        $this->get('/indexable-managed-page')
            ->assertOk()
            ->assertSee('name="robots" content="index,follow"', false);

        $this->get('/noindex-managed-page')
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee('/indexable-managed-page', false)
            ->assertDontSee('/noindex-managed-page', false);
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






    public function test_managed_page_fallback_metadata_matches_public_render_and_strips_html(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = ContentPage::create([
            'title' => 'Managed SEO Fallback',
            'slug' => 'managed-seo-fallback',
            'body' => '<p>Useful managed page copy with <strong>HTML</strong> that should become plain metadata.</p>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => true]],
            'published_at' => now(),
        ]);

        $this->get('/managed-seo-fallback')
            ->assertOk()
            ->assertSee('<title>Managed SEO Fallback - Emerald Rozalia</title>', false)
            ->assertSee('content="Useful managed page copy with HTML that should become plain metadata."', false)
            ->assertDontSee('name="description" content="&lt;p&gt;Useful managed page copy', false);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect();

        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'missing-meta-title',
        ]);
        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'missing-meta-description',
        ]);
        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'missing-h1',
        ]);
    }

    public function test_help_and_policy_pages_publish_tailored_search_metadata(): void
    {
        $expectations = [
            '/size-guide' => 'Hat &amp; Cap Size Guide | Emerald Rozalia',
            '/shipping-delivery' => 'Shipping &amp; Delivery | Emerald Rozalia',
            '/privacy-policy' => 'Privacy Policy | Emerald Rozalia',
            '/terms-conditions' => 'Terms &amp; Conditions | Emerald Rozalia',
            '/returns-refunds' => 'Returns &amp; Refunds | Emerald Rozalia',
            '/our-story' => 'Our Story | Emerald Rozalia',
        ];

        foreach ($expectations as $path => $title) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<title>'.$title.'</title>', false)
                ->assertSee('name="description"', false);
        }
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



    public function test_shadowed_managed_pages_do_not_override_dedicated_route_metadata_or_sitemap_source(): void
    {
        ContentPage::create([
            'title' => 'Shadow Contact Page',
            'slug' => 'contact',
            'body' => '<h1>Shadow Contact Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => [
                'title' => 'Wrong Contact Metadata',
                'description' => 'This metadata must not replace the dedicated contact page.',
                'settings' => ['visibility' => 'public', 'indexable' => true],
            ],
            'published_at' => now(),
        ]);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('<title>Contact Emerald Rozalia</title>', false)
            ->assertDontSee('<title>Wrong Contact Metadata</title>', false);

        $xml = $this->get(route('seo.sitemap'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($xml, '<loc>http://localhost/contact</loc>'));
        $this->assertStringNotContainsString('<loc>http://localhost/home</loc>', $xml);
    }

    public function test_live_managed_page_override_controls_fixed_route_sitemap_indexability(): void
    {
        ContentPage::create([
            'title' => 'Managed Size Guide Override',
            'slug' => 'size-guide',
            'body' => '<h1>Managed Size Guide Override</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => false]],
            'published_at' => now(),
        ]);

        $this->get('/size-guide')
            ->assertOk()
            ->assertSee('Managed Size Guide Override')
            ->assertSee('name="robots" content="noindex,follow"', false);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertDontSee('<loc>http://localhost/size-guide</loc>', false);
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


    public function test_sitemap_does_not_claim_static_pages_changed_on_every_request(): void
    {
        $xml = $this->get(route('seo.sitemap'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '~<loc>http://localhost/</loc>\s*</url>~',
            $xml
        );
        $this->assertMatchesRegularExpression(
            '~<loc>http://localhost/shop</loc>\s*</url>~',
            $xml
        );
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




    public function test_faceted_catalog_urls_are_noindex_but_pagination_remains_indexable(): void
    {
        $this->get('/shop?material=Wool')
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false)
            ->assertSee('<link rel="canonical" href="http://localhost/shop">', false);

        $this->get('/shop?page=2')
            ->assertOk()
            ->assertSee('name="robots" content="index,follow"', false)
            ->assertSee('<link rel="canonical" href="http://localhost/shop?page=2">', false);
    }

    public function test_legacy_home_alias_redirects_to_canonical_homepage(): void
    {
        $this->get('/home')
            ->assertStatus(301)
            ->assertRedirect('/');
    }

    public function test_search_console_legacy_404_urls_redirect_when_no_live_catalogue_record_exists(): void
    {
        $categorySlugs = [
            'sports-training-caps',
            'workwear-warehouse-team-caps',
            'limerick-heritage-collection',
            'heritage-baseball-caps',
            'winter-fleece-hats',
            '8-panel-caps',
            'snapbacks',
            'kids-beanies',
            'classic-6-panel-caps',
            'bucket-hats',
            'classic-fashion-caps',
            'costume-party-hats',
        ];

        Category::query()->whereIn('slug', $categorySlugs)->update([
            'status' => 'inactive',
            'is_active' => false,
            'is_visible' => false,
        ]);
        Product::query()->where('slug', 'baseball-cap')->update([
            'status' => 'draft',
            'is_active' => false,
        ]);

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

    public function test_legacy_catalogue_slug_renders_normally_when_a_live_record_now_exists(): void
    {
        $category = Category::create([
            'name' => 'Sports Training Caps',
            'slug' => 'sports-training-caps',
            'description' => 'Live sports training cap collection.',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Baseball Cap',
            'slug' => 'baseball-cap',
            'sku' => 'LEGACY-LIVE-001',
            'description' => 'Live public baseball cap.',
            'price' => 45,
            'stock' => 3,
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->get('/category/sports-training-caps')
            ->assertOk()
            ->assertSee('SPORTS TRAINING CAPS');

        $this->get('/product/baseball-cap')
            ->assertOk()
            ->assertSee('<h1>Baseball Cap</h1>', false);

        $xml = $this->get(route('seo.sitemap'))->assertOk()->getContent();
        $this->assertStringContainsString('<loc>http://localhost/category/sports-training-caps</loc>', $xml);
        $this->assertStringContainsString('<loc>http://localhost/product/baseball-cap</loc>', $xml);
    }

    public function test_redirect_manager_normalizes_source_paths_and_allows_external_same_path_targets(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.seo.redirects.store'), [
                'from_path' => 'legacy-path/',
                'to_path' => '/shop',
                'status_code' => 301,
                'active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/legacy-path',
            'to_path' => '/shop',
            'status_code' => 301,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.redirects.store'), [
                'from_path' => '/external-path',
                'to_path' => 'https://example.com/external-path',
                'status_code' => 302,
                'active' => '1',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_redirect_manager_rejects_self_redirects_and_query_sources(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.seo.redirects.store'), [
                'from_path' => '/shop',
                'to_path' => '/shop',
                'status_code' => 301,
                'active' => '1',
            ])
            ->assertSessionHasErrors('to_path');

        $this->actingAs($admin)
            ->post(route('admin.seo.redirects.store'), [
                'from_path' => '/shop?legacy=1',
                'to_path' => '/shop',
                'status_code' => 301,
                'active' => '1',
            ])
            ->assertSessionHasErrors('from_path');
    }

    public function test_schema_manager_requires_a_json_object_with_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.seo.schema.update'), [
                'schema_json' => '[]',
            ])
            ->assertSessionHasErrors('schema_json');

        $this->actingAs($admin)
            ->post(route('admin.seo.schema.update'), [
                'schema_json' => json_encode(['name' => 'Emerald Rozalia Limited']),
            ])
            ->assertSessionHasErrors('schema_json');

        $this->actingAs($admin)
            ->post(route('admin.seo.schema.update'), [
                'schema_json' => json_encode([
                    '@type' => 'Organization',
                    'name' => 'Emerald Rozalia Limited',
                ]),
            ])
            ->assertSessionHasNoErrors();
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


    public function test_fixed_public_page_metadata_can_be_edited_and_noindexed_from_seo_manager(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.seo.meta.update', ['fixed', 18]), [
                'meta_title' => 'Custom Size Guide | Emerald Rozalia',
                'meta_description' => 'Custom size guidance for Emerald Rozalia customers choosing hats and caps.',
                'focus_keyword' => 'hat size guide ireland',
                'noindex' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('seo_settings', ['key' => 'fixed_page_meta']);

        $this->get('/size-guide')
            ->assertOk()
            ->assertSee('<title>Custom Size Guide | Emerald Rozalia</title>', false)
            ->assertSee('name="robots" content="noindex,follow"', false);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertDontSee('/size-guide', false);
    }

    public function test_page_meta_manager_noindex_checkbox_updates_managed_page_indexability(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = ContentPage::create([
            'title' => 'Editable Index Page',
            'slug' => 'editable-index-page',
            'body' => '<h1>Editable Index Page</h1>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => true]],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.seo.meta.update', ['page', $page->id]), [
                'meta_title' => 'Editable Index Page | Emerald Rozalia',
                'meta_description' => 'Managed page metadata edited from the Emerald Rozalia SEO dashboard.',
                'noindex' => '1',
            ])
            ->assertRedirect();

        $page->refresh();
        $this->assertFalse((bool) data_get($page->meta, 'settings.indexable'));

        $this->get('/editable-index-page')
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertDontSee('/editable-index-page', false);
    }


    public function test_escaped_body_markup_does_not_create_false_alt_or_broken_link_issues(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = ContentPage::create([
            'title' => 'Rendered Section Audit Page',
            'slug' => 'rendered-section-audit-page',
            'body' => '<img src="/storage/example.webp" alt=""><p><a href="/missing-body-target">Escaped body link</a></p>',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => true]],
            'published_at' => now(),
        ]);
        $page->sections()->create([
            'type' => 'cta',
            'label' => 'Shop link',
            'sort_order' => 0,
            'region' => 'main',
            'settings' => ['url' => '/shop/', 'button_label' => 'Shop'],
            'visible' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.seo.audit'))->assertRedirect();

        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'missing-alt-text',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->post(route('admin.seo.links'))->assertRedirect();

        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'broken-internal-link',
            'status' => 'open',
        ]);
    }

    public function test_duplicate_meta_title_autofix_generates_a_unique_product_title(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $first = Product::create([
            'name' => 'Duplicate Cap',
            'slug' => 'duplicate-cap-one',
            'sku' => 'DUP-ONE',
            'price' => 50,
            'stock' => 2,
            'status' => 'published',
            'is_active' => true,
        ]);
        $second = Product::create([
            'name' => 'Duplicate Cap',
            'slug' => 'duplicate-cap-two',
            'sku' => 'DUP-TWO',
            'price' => 50,
            'stock' => 2,
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.audit'))
            ->assertRedirect();

        $issue = \App\Models\SeoIssue::query()
            ->where('source_type', 'product')
            ->where('source_id', $second->id)
            ->where('issue_type', 'duplicate-meta-title')
            ->where('status', 'open')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.seo.issue.fix', ['product', $second->id, 'duplicate-meta-title']))
            ->assertRedirect();

        $second->refresh();
        $this->assertNotSame($first->name.' | Emerald Rozalia', $second->meta_title);
        $this->assertStringContainsString('DUP TWO', strtoupper((string) $second->meta_title));
        $this->assertSame('resolved', $issue->fresh()->status);

        $this->actingAs($admin)->post(route('admin.seo.audit'))->assertRedirect();
        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'product',
            'source_id' => $second->id,
            'issue_type' => 'duplicate-meta-title',
            'status' => 'open',
        ]);
    }

    public function test_page_internal_link_autofix_creates_a_rendered_cta_section(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = ContentPage::create([
            'title' => 'Internal Link Page',
            'slug' => 'internal-link-page',
            'body' => 'Page copy without contextual links.',
            'status' => 'published',
            'locale' => 'en',
            'template' => 'standard',
            'meta' => ['settings' => ['visibility' => 'public', 'indexable' => true]],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.seo.audit'))->assertRedirect();

        $this->assertDatabaseHas('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'missing-internal-links',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seo.issue.fix', ['page', $page->id, 'missing-internal-links']))
            ->assertRedirect();

        $this->assertDatabaseHas('page_sections', [
            'content_page_id' => $page->id,
            'type' => 'cta',
            'label' => 'Explore Emerald Rozalia',
            'visible' => true,
        ]);

        $this->get('/internal-link-page')
            ->assertOk()
            ->assertSee('href="/shop"', false)
            ->assertSee('Shop the Collection');

        $this->actingAs($admin)->post(route('admin.seo.audit'))->assertRedirect();
        $this->assertDatabaseMissing('seo_issues', [
            'source_type' => 'page',
            'source_id' => $page->id,
            'issue_type' => 'missing-internal-links',
            'status' => 'open',
        ]);
    }

}
