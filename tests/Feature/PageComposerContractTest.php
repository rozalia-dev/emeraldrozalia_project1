<?php

namespace Tests\Feature;

use App\Models\{ContentPage, PageSection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageComposerContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_composer_persists_page_and_section_contracts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = [
            'title' => 'Composer Contract Page',
            'slug' => 'composer-contract-page',
            'route_path' => '/composer-contract-page',
            'page_kind' => 'landing',
            'template' => 'standard',
            'status' => 'published',
            'locale' => 'en',
            'sections' => [[
                'type' => 'hero',
                'label' => 'Editable headline',
                'settings' => ['title' => 'Editable headline', 'content' => 'Editable body', 'url' => '/shop'],
                'visible' => true,
                'sort_order' => 0,
                'animation' => 'fade',
                'devices' => ['desktop', 'mobile'],
                'analytics_key' => 'composer.hero',
            ]],
        ];

        $this->actingAs($admin)->post(route('admin.pages.store'), $payload)->assertRedirect();
        $page = ContentPage::query()->where('slug', 'composer-contract-page')->firstOrFail();
        $section = PageSection::query()->where('content_page_id', $page->id)->firstOrFail();

        $this->assertSame('/composer-contract-page', $page->route_path);
        $this->assertSame('landing', $page->page_kind);
        $this->assertSame('fade', $section->animation);
        $this->assertSame(['desktop', 'mobile'], $section->devices);
        $this->assertSame('composer.hero', $section->analytics_key);

        $this->actingAs($admin)->get(route('admin.pages.preview', $page))
            ->assertOk()
            ->assertSee('class="site-body', false)
            ->assertSeeText('Previewing Composer Contract Page')
            ->assertSeeText('Editable headline')
            ->assertSeeText('Edit page');
    }

    public function test_reserved_homepage_preview_uses_the_public_home_renderer(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $homepage = ContentPage::query()->where('slug', 'home')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.pages.preview', $homepage))
            ->assertOk()
            ->assertSee('data-homepage-page-uuid="'.$homepage->uuid.'"', false)
            ->assertSee('class="home-hero home-hero--structured"', false)
            ->assertSee('class="home-hero-structured-copy"', false)
            ->assertSee('class="home-hero-structured-product', false)
            ->assertSee('class="home-hero-tryon"', false)
            ->assertSee('data-home-tryon-form', false)
            ->assertSee('class="managed-page-preview-banner"', false)
            ->assertSee('data-public-layout-source="default-layout-fallback"', false);
    }

    public function test_homepage_is_composed_from_persisted_sections_and_updates_the_public_root(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $homepage = ContentPage::query()->with('sections')->where('slug', 'home')->firstOrFail();
        $sections = $homepage->sections->map(function (PageSection $section): array {
            $settings = is_array($section->settings) ? $section->settings : [];
            if ($section->type === 'hero') {
                $settings['title'] = 'A managed homepage headline';
            }

            return [
                'type' => $section->type,
                'label' => $section->label,
                'settings' => $settings,
                'media_uuid' => $section->media_uuid,
                'visible' => $section->visible,
                'sort_order' => $section->sort_order,
                'animation' => $section->animation,
                'devices' => $section->devices,
                'analytics_key' => $section->analytics_key,
            ];
        })->all();

        $this->actingAs($admin)->put(route('admin.pages.update', $homepage), [
            'title' => $homepage->title,
            'slug' => $homepage->slug,
            'route_path' => '/',
            'page_kind' => 'home',
            'template' => $homepage->template,
            'status' => 'published',
            'locale' => $homepage->locale,
            'navigation_visible' => $homepage->navigation_visible,
            'sections' => $sections,
        ])->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('A managed homepage headline');
    }

    public function test_builder_contains_pointer_drop_keyboard_reorder_undo_and_server_fallback_contracts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $html = $this->actingAs($admin)->get(route('admin.pages.create'))->assertOk()->getContent();

        foreach (['Drag sections to reorder', 'data-builder-undo', 'data-builder-section-editor'] as $contract) {
            $this->assertStringContainsString($contract, $html);
        }
    }
}
