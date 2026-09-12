<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PublishedSiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPrivateSettingsContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_save_creates_versioned_public_snapshot_and_the_public_shell_consumes_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.settings.save', 'company-branding'), [
            'trading_name' => 'Emerald Rozalia Acceptance',
            'legal_name' => 'Emerald Rozalia Acceptance Limited',
            'description' => 'Acceptance copy from the private branding record.',
            'footer_text' => 'Acceptance footer copy.',
            'city' => 'Limerick',
            'country' => 'Ireland',
            'brand_primary' => '#123456',
            'brand_secondary' => '#234567',
            'brand_accent' => '#345678',
            'logo_path' => '/private/originals/not-public.svg',
        ])->assertRedirect();

        $revision = PublishedSiteSetting::query()
            ->whereNull('company_id')
            ->where('section', 'company-branding')
            ->firstOrFail();

        $this->assertSame(1, $revision->version);
        $this->assertSame('Emerald Rozalia Acceptance', data_get($revision->data, 'trading_name'));
        $this->assertSame('/assets/logo/logo_two_line.png', data_get($revision->data, 'logo_path'));
        $this->assertArrayNotHasKey('custom_css', $revision->data);

        $this->get('/')->assertOk()
            ->assertSee('data-public-settings-source="published-site-settings"', false)
            ->assertSee('data-public-settings-version="1"', false)
            ->assertSee('Emerald Rozalia Acceptance', false)
            ->assertSee('Acceptance copy from the private branding record.', false)
            ->assertSee('--site-brand-primary: #123456', false)
            ->assertDontSee('/private/originals/not-public.svg', false);
    }

    public function test_public_revisions_are_append_only_and_tenant_specific(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = Company::create(['name' => 'First Company', 'code' => 'FIRST', 'active' => true]);
        $second = Company::create(['name' => 'Second Company', 'code' => 'SECOND', 'active' => true]);

        $this->withSession(['company_id' => $first->id])->actingAs($admin)
            ->post(route('admin.settings.save', 'company-branding'), [
                'trading_name' => 'First Public Brand',
                'legal_name' => 'First Public Brand Limited',
                'description' => 'First tenant copy.',
            ])->assertRedirect();

        $this->withSession(['company_id' => $first->id])->actingAs($admin)
            ->post(route('admin.settings.save', 'company-branding'), [
                'trading_name' => 'First Revised Brand',
                'legal_name' => 'First Revised Brand Limited',
                'description' => 'First revised tenant copy.',
            ])->assertRedirect();

        $this->withSession(['company_id' => $second->id])->actingAs($admin)
            ->post(route('admin.settings.save', 'company-branding'), [
                'trading_name' => 'Second Public Brand',
                'legal_name' => 'Second Public Brand Limited',
                'description' => 'Second tenant copy.',
            ])->assertRedirect();

        $firstRevisions = PublishedSiteSetting::query()->where('company_id', $first->id)->where('section', 'company-branding')->orderBy('version')->get();
        $this->assertSame([1, 2], $firstRevisions->pluck('version')->all());
        $this->assertSame('First Public Brand', data_get($firstRevisions->first()->data, 'trading_name'));

        $this->withSession(['company_id' => $first->id])->get('/')
            ->assertSee('First Revised Brand', false)
            ->assertSee('First revised tenant copy.', false)
            ->assertDontSee('Second Public Brand', false);

        $this->withSession(['company_id' => $second->id])->get('/')
            ->assertSee('Second Public Brand', false)
            ->assertSee('Second tenant copy.', false)
            ->assertDontSee('First Revised Brand', false);
    }

    public function test_private_settings_are_saved_without_being_exposed_as_public_site_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.settings.save', 'security-access'), [
            'ip_allowlist' => "10.0.0.1\n10.0.0.0/24",
            'password_min_length' => 16,
        ])->assertRedirect();

        $this->assertDatabaseHas('admin_records', [
            'module' => 'system-settings',
            'reference' => 'security-access',
        ]);
        $this->assertDatabaseCount('published_site_settings', 0);
        $this->get('/')->assertOk()->assertDontSee('10.0.0.0/24', false);
    }

    public function test_published_localization_controls_the_public_context_when_no_session_override_exists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.settings.save', 'localization'), [
            'default_language' => 'ga',
            'default_currency' => 'GBP',
            'country_code' => 'IE',
            'timezone' => 'Europe/Dublin',
        ])->assertRedirect();

        $this->get('/')->assertOk()
            ->assertSee('<html lang="ga"', false)
            ->assertSee('data-public-settings-version="1"', false);
    }
}
