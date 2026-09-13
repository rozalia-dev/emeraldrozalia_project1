<?php

namespace Tests\Feature;

use App\Models\{ContentPage, PageSection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageComposerContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_homepage_is_listed_with_its_root_route_and_cannot_be_trashed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $homepage = ContentPage::query()->where('slug', 'home')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.pages'))
            ->assertOk()
            ->assertSeeText('Homepage')
            ->assertSeeText('/')
            ->assertSee(route('admin.pages.edit', $homepage), false);

        $this->actingAs($admin)->post(route('admin.pages.action', [$homepage, 'trash']))->assertStatus(409);
        $this->assertDatabaseHas('content_pages', ['id' => $homepage->id, 'is_reserved' => true, 'route_path' => '/']);
    }

    public function test_page_sections_persist_typed_layout_metadata_and_preview_uses_the_public_shell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = [
            'title' => 'Composer Contract Page',
            'slug' => 'composer-contract-page',
            'intro' => 'A managed preview page.',
            'body' => 'Server-rendered body copy.',
            'status' => 'draft',
            'locale' => 'en',
            'template' => 'landing',
            'navigation_visible' => '1',
            'indexable' => '1',
            'visibility' => 'public',
            'devices' => ['desktop', 'tablet', 'mobile'],
            'sections' => json_encode([[
                'type' => 'hero',
                'label' => 'Managed hero',
                'region' => 'main',
                'locale' => 'en',
                'devices' => ['desktop', 'mobile'],
                'variant' => 'split',
                'animation' => 'fade',
                'analytics_key' => 'composer.hero',
                'settings' => ['title' => 'Editable headline', 'content' => 'Editable copy'],
                'visible' => true,
            ]]),
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
            ->assertSee('class="home-hero-composition"', false)
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
                'region' => $section->region,
                'locale' => $section->locale,
                'media_uuid' => $section->media_uuid,
                'focal_point' => $section->focal_point,
                'devices' => $section->devices,
                'variant' => $section->variant,
                'animation' => $section->animation,
                'analytics_key' => $section->analytics_key,
                'settings' => $settings,
                'visible' => $section->visible,
            ];
        })->values()->all();

        $this->actingAs($admin)->put(route('admin.pages.update', $homepage), [
            'title' => $homepage->title,
            'slug' => 'home',
            'intro' => $homepage->intro,
            'body' => $homepage->body,
            'status' => 'published',
            'locale' => 'en',
            'template' => 'home',
            'navigation_visible' => '0',
            'show_in_footer' => '0',
            'indexable' => '1',
            'visibility' => 'public',
            'devices' => ['desktop', 'tablet', 'mobile'],
            'sections' => json_encode($sections),
        ])->assertRedirect();

        $this->assertDatabaseHas('page_sections', [
            'content_page_id' => $homepage->id,
            'type' => 'hero',
        ]);
        $this->get('/')->assertOk()->assertSeeText('A managed homepage headline');
    }

    public function test_homepage_unpublish_removes_the_public_root_without_deleting_the_reserved_record(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $homepage = ContentPage::query()->where('slug', 'home')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.pages.action', [$homepage, 'unpublish']))->assertRedirect();
        $this->get('/')->assertNotFound();
        $this->assertDatabaseHas('content_pages', ['id' => $homepage->id, 'status' => 'unpublished', 'is_reserved' => true]);
    }

    public function test_builder_contains_pointer_drop_keyboard_reorder_undo_and_server_first_markup(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $html = $this->actingAs($admin)->get(route('admin.pages.create'))->assertOk()->getContent();

        foreach (['Drag sections to reorder', 'data-builder-undo', 'Enable JavaScript to edit this block inline.'] as $contract) {
            $this->assertStringContainsString($contract, $html);
        }

        $script = file_get_contents(public_path('js/app.js'));
        foreach (['block.draggable=true', 'data-builder-drop-target', 'Alt+ArrowUp', 'const undo='] as $contract) {
            $this->assertStringContainsString($contract, $script);
        }
    }
}
