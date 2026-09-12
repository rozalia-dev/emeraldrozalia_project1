<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerDashboardReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_banner_dashboard_renders_the_reference_shell_in_preview_mode(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.banners.index'))
            ->assertOk()
            ->assertSeeText('Banners / Sliders')
            ->assertSeeText('Total Banners / Sliders')
            ->assertSeeText('Banner Summary')
            ->assertSeeText('Banner Types')
            ->assertSeeText('UUID Traceability')
            ->assertSeeText('Eye-Catching Visibility')
            ->assertSee('/css/banners-reference.css?v=20260912-1', false)
            ->assertSee('/js/banners-reference.js?v=20260912-1', false)
            ->assertSee('data-banner-root', false)
            ->assertSee('New Arrivals 2025');
    }

    public function test_banner_lifecycle_filters_revisions_and_settings_are_postgresql_backed(): void
    {
        $admin = $this->admin();
        $banner = Banner::create([
            'title' => 'CI Banner',
            'subtitle' => 'A live PostgreSQL campaign',
            'type' => 'slider',
            'position' => 'Home - Main Slider',
            'target_url' => '/collections/ci',
            'target_type' => 'internal',
            'status' => 'published',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'clicks' => 12,
            'impressions' => 100,
            'priority' => 2,
            'device_visibility' => ['desktop', 'mobile'],
            'specific_pages' => ['Home'],
            'alt_text' => 'CI banner',
        ]);

        $this->actingAs($admin)->get(route('admin.banners.index', ['q' => 'CI Banner', 'device' => 'mobile']))
            ->assertOk()
            ->assertSeeText('CI Banner')
            ->assertDontSeeText('New Arrivals 2025');

        $this->actingAs($admin)->put(route('admin.banners.update', $banner->id), [
            'title' => 'CI Banner Updated', 'subtitle' => 'Updated copy', 'type' => 'banner',
            'position' => 'Top Banner', 'target_url' => '/updated', 'target_type' => 'internal',
            'status' => 'published', 'starts_at' => now()->subDay()->format('Y-m-d H:i'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i'), 'priority' => 4,
            'devices' => ['desktop', 'tablet'], 'specific_pages' => 'Home, Shop',
            'alt_text' => 'Updated banner', 'title_text' => 'Updated', 'aria_label' => 'Updated banner link',
            'animation' => 'slide', 'autoplay' => 1, 'autoplay_speed' => 7,
            'show_arrows' => 1, 'show_dots' => 1, 'pause_on_hover' => 1,
        ])->assertRedirect();

        $this->assertSame('CI Banner Updated', $banner->fresh()->title);
        $this->assertDatabaseHas('banner_revisions', ['banner_id' => $banner->id, 'reason' => 'Updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'banners.updated', 'subject_id' => (string) $banner->id]);

        $this->actingAs($admin)->patch(route('admin.banners.settings', $banner->id), [
            'animation' => 'zoom', 'autoplay_speed' => 9, 'devices' => ['mobile'],
            'specific_pages' => 'Home', 'show_arrows' => 1,
        ])->assertRedirect();
        $this->assertSame('zoom', $banner->fresh()->animation);
        $this->assertSame(['mobile'], $banner->fresh()->device_visibility);

        $this->actingAs($admin)->post(route('admin.banners.duplicate', $banner->id))->assertRedirect();
        $copy = Banner::where('title', 'CI Banner Updated Copy')->firstOrFail();
        $this->assertSame('draft', $copy->status);

        $this->actingAs($admin)->post(route('admin.banners.action', [$copy->id, 'publish']))->assertRedirect();
        $this->assertSame('published', $copy->fresh()->status);

        $revision = $banner->revisions()->oldest('version')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.banners.restore-revision', [$banner->id, $revision->id]))->assertRedirect();
        $this->assertSame('CI Banner', $banner->fresh()->title);
    }

    public function test_banner_export_downloads_and_non_admins_are_forbidden(): void
    {
        $admin = $this->admin();
        Banner::create(['title' => 'Export Banner', 'type' => 'banner', 'position' => 'Top Banner', 'status' => 'draft']);

        $this->actingAs($admin)->get(route('admin.banners.export', ['status' => 'draft']))
            ->assertOk()
            ->assertDownload();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.banners.index'))
            ->assertForbidden();
    }
}
