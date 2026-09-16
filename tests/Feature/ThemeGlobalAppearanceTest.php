<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ThemeVersion;
use App\Models\User;
use App\Services\ThemeVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeGlobalAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_manager_exposes_simple_apply_reset_and_separate_cpanel_appearance(): void
    {
        [$admin, $company] = $this->context('THEME-UI');

        $response = $this->withTenant($admin, $company)->get(route('admin.settings.theme.index'));

        $response->assertOk()
            ->assertSeeText('Theme & Appearance')
            ->assertSeeText('Apply theme globally')
            ->assertSeeText('Reset to default')
            ->assertSeeText('cPanel appearance')
            ->assertSeeText('Edit → Apply globally')
            ->assertDontSeeText('Request approval')
            ->assertSee('data-cpanel-theme="guide-dark"', false)
            ->assertSee('data-cpanel-header', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSeeText('Logout');
    }

    public function test_one_action_applies_theme_to_shared_public_pages_and_factory_shell(): void
    {
        [$admin, $company] = $this->context('THEME-GLOBAL');
        $tokens = ThemeVersionService::DEFAULT_TOKENS;
        $tokens['colors']['primary'] = '#123456';
        $tokens['colors']['accent'] = '#abcdef';

        $this->withTenant($admin, $company)->post(route('admin.settings.theme.apply'), [
            'name' => 'Global verification theme',
            'environment' => 'production',
            'locale' => 'en',
            'notes' => 'Automated global contract verification.',
            'tokens' => $tokens,
        ])->assertRedirect();

        $theme = ThemeVersion::withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame(ThemeVersion::STATUS_ACTIVE, $theme->status);
        $this->assertNotNull($theme->validated_at);
        $this->assertNotNull($theme->approved_at);
        $this->assertNotNull($theme->activated_at);

        foreach (['/', '/contact', '/franchise', '/corporate-orders', '/bulk-orders', '/global-network', '/login'] as $path) {
            $this->withSession(['company_id' => $company->id])->get($path)
                ->assertOk()
                ->assertSee('data-public-theme-source="active-theme-version"', false)
                ->assertSee('--site-brand-primary: #123456', false)
                ->assertSee('--site-brand-accent: #abcdef', false);
        }

        $this->withSession(['company_id' => $company->id])->get('/factory')
            ->assertOk()
            ->assertSee('data-public-theme-source="active-theme-version"', false)
            ->assertSee('data-public-theme-contract="global"', false)
            ->assertSee('factory-reference-body', false)
            ->assertSee('--site-brand-primary: #123456', false)
            ->assertSee('--site-brand-accent: #abcdef', false);
    }

    public function test_reset_default_creates_audited_live_baseline_version(): void
    {
        [$admin, $company] = $this->context('THEME-RESET');
        $tokens = ThemeVersionService::DEFAULT_TOKENS;
        $tokens['colors']['primary'] = '#123456';

        $this->withTenant($admin, $company)->post(route('admin.settings.theme.apply'), [
            'name' => 'Temporary custom theme',
            'environment' => 'production',
            'locale' => 'en',
            'tokens' => $tokens,
        ])->assertRedirect();

        $this->withTenant($admin, $company)->post(route('admin.settings.theme.reset-default'), [
            'environment' => 'production',
        ])->assertRedirect();

        $active = ThemeVersion::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', ThemeVersion::STATUS_ACTIVE)
            ->firstOrFail();

        $this->assertSame('Emerald Rozalia Default', $active->name);
        $this->assertSame('#075b2f', data_get($active->token_payload, 'colors.primary'));
        $this->assertSame(2, $active->version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.theme.activated', 'subject_uuid' => $active->uuid]);

        $this->withSession(['company_id' => $company->id])->get('/')
            ->assertOk()
            ->assertSee('--site-brand-primary: #075b2f', false);
    }

    public function test_cpanel_theme_is_persistent_separate_and_audited(): void
    {
        [$admin, $company] = $this->context('CPANEL-THEME');

        $this->withTenant($admin, $company)->post(route('admin.settings.cpanel-theme.update'), [
            'cpanel_theme' => 'light',
        ])->assertRedirect();

        $company->refresh();
        $this->assertSame('light', data_get($company->settings, 'cpanel.theme'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.cpanel_theme.updated',
            'subject_type' => Company::class,
            'subject_id' => $company->id,
        ]);

        $this->withTenant($admin, $company)->get(route('admin.settings.theme.index'))
            ->assertOk()
            ->assertSee('data-cpanel-theme="light"', false)
            ->assertSee('cpanel-theme-light', false);

        $this->withSession(['company_id' => $company->id])->get('/')
            ->assertOk()
            ->assertSee('data-public-theme-source="default-theme-fallback"', false);
    }

    private function context(string $code): array
    {
        $admin = User::factory()->create(['is_admin' => true, 'department' => 'Administration']);
        $company = Company::create(['name' => $code.' Tenant', 'code' => $code, 'active' => true]);

        return [$admin, $company];
    }

    private function withTenant(User $admin, Company $company): self
    {
        return $this->withSession(['company_id' => $company->id])->actingAs($admin);
    }
}
