<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ThemeVersion;
use App\Models\User;
use App\Services\CpanelThemeVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CpanelThemeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_cpanel_theme_editor_is_available_and_defaults_are_safe(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'cPanel Theme Tenant', 'code' => 'CPANEL-THEME', 'active' => true]);

        $this->withTenant($admin, $company)->get(route('admin.settings.cpanel-theme.index'))
            ->assertOk()
            ->assertSeeText('cPanel Appearance')
            ->assertSeeText('No cPanel theme versions yet.')
            ->assertSeeText('Safe activation contract.');

        $this->withTenant($admin, $company)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="cpanel-theme-runtime"', false)
            ->assertSee('data-cpanel-theme-source="default-cpanel-theme-fallback"', false)
            ->assertDontSee('--cpanel-topbar:', false)
            ->assertDontSee('--cpanel-sidebar-width:', false);
    }

    public function test_draft_does_not_change_live_cpanel_but_activation_does(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'cPanel Activation Tenant', 'code' => 'CPANEL-ACTIVE', 'active' => true]);
        $tokens = CpanelThemeVersionService::DEFAULT_TOKENS;
        $tokens['colors']['topbar'] = '#123456';
        $tokens['colors']['accent'] = '#abcdef';
        $tokens['spacing']['sidebar_width'] = '268px';

        $this->withTenant($admin, $company)->post(route('admin.settings.cpanel-theme.store'), [
            'name' => 'Independent cPanel theme',
            'environment' => 'production',
            'locale' => 'en',
            'tokens' => $tokens,
            'notes' => 'Proves draft isolation and activation.',
        ])->assertRedirect();

        $theme = ThemeVersion::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('scope', 'admin')
            ->where('name', 'Independent cPanel theme')
            ->firstOrFail();

        $this->assertSame(ThemeVersion::STATUS_DRAFT, $theme->status);

        $this->withTenant($admin, $company)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-cpanel-theme-source="default-cpanel-theme-fallback"', false)
            ->assertDontSee('--cpanel-topbar:#123456;', false)
            ->assertDontSee('--cpanel-sidebar-width:268px;', false);

        foreach (['validate', 'submit', 'approve', 'activate'] as $action) {
            $this->withTenant($admin, $company)
                ->post(route('admin.settings.cpanel-theme.action', ['theme' => $theme, 'action' => $action]))
                ->assertRedirect();
        }

        $this->assertSame(ThemeVersion::STATUS_ACTIVE, $theme->fresh()->status);
        $this->withTenant($admin, $company)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-cpanel-theme-source="active-cpanel-theme-version"', false)
            ->assertSee('data-cpanel-theme-version="1"', false)
            ->assertSee('--cpanel-topbar:#123456;', false)
            ->assertSee('--cpanel-accent:#abcdef;', false)
            ->assertSee('--cpanel-sidebar-width:268px;', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.cpanel-theme.draft_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.cpanel-theme.validated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.cpanel-theme.submitted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.cpanel-theme.approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.cpanel-theme.activated']);
    }

    public function test_cpanel_logo_is_versioned_and_changes_only_after_activation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'cPanel Logo Tenant', 'code' => 'CPANEL-LOGO', 'active' => true]);
        $tokens = CpanelThemeVersionService::DEFAULT_TOKENS;
        $tokens['branding']['logo_path'] = '/assets/logo/logo_one_line.png';
        $tokens['branding']['logo_alt'] = 'Emerald Rozalia Limited cPanel';

        $this->withTenant($admin, $company)->post(route('admin.settings.cpanel-theme.store'), [
            'name' => 'cPanel logo revision',
            'environment' => 'production',
            'locale' => 'en',
            'tokens' => $tokens,
        ])->assertRedirect();

        $theme = ThemeVersion::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('scope', 'admin')
            ->where('name', 'cPanel logo revision')
            ->firstOrFail();

        $this->withTenant($admin, $company)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('assets/logo/logo_two_line.png', false)
            ->assertDontSee('Emerald Rozalia Limited cPanel', false);

        foreach (['validate', 'submit', 'approve', 'activate'] as $action) {
            $this->withTenant($admin, $company)
                ->post(route('admin.settings.cpanel-theme.action', ['theme' => $theme, 'action' => $action]))
                ->assertRedirect();
        }

        $this->withTenant($admin, $company)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('assets/logo/logo_one_line.png', false)
            ->assertSee('Emerald Rozalia Limited cPanel', false)
            ->assertSeeText('cPanel Appearance & Logo');
    }

    public function test_cpanel_theme_scope_is_independent_from_public_theme_scope(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Separated Theme Tenant', 'code' => 'THEME-SEPARATED', 'active' => true]);
        $tokens = CpanelThemeVersionService::DEFAULT_TOKENS;
        $tokens['colors']['topbar'] = '#234567';

        $this->withTenant($admin, $company)->post(route('admin.settings.cpanel-theme.store'), [
            'name' => 'Admin only theme',
            'environment' => 'production',
            'locale' => 'en',
            'tokens' => $tokens,
        ])->assertRedirect();

        $theme = ThemeVersion::withoutGlobalScopes()->where('company_id', $company->id)->where('scope', 'admin')->firstOrFail();
        foreach (['validate', 'submit', 'approve', 'activate'] as $action) {
            $this->withTenant($admin, $company)->post(route('admin.settings.cpanel-theme.action', [$theme, $action]))->assertRedirect();
        }

        $this->withSession(['company_id' => $company->id])->get('/')
            ->assertOk()
            ->assertSee('data-public-theme-source="default-theme-fallback"', false);

        $this->assertDatabaseMissing('theme_versions', ['company_id' => $company->id, 'scope' => 'public']);
    }

    public function test_invalid_cpanel_theme_tokens_are_rejected_and_tenant_isolation_is_enforced(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = Company::create(['name' => 'First cPanel Tenant', 'code' => 'CP-FIRST', 'active' => true]);
        $second = Company::create(['name' => 'Second cPanel Tenant', 'code' => 'CP-SECOND', 'active' => true]);
        $invalid = CpanelThemeVersionService::DEFAULT_TOKENS;
        $invalid['colors']['topbar'] = '#12';

        $this->withTenant($admin, $first)->post(route('admin.settings.cpanel-theme.store'), [
            'name' => 'Invalid cPanel theme',
            'environment' => 'production',
            'locale' => 'en',
            'tokens' => $invalid,
        ])->assertSessionHasErrors('colors.topbar');
        $this->assertDatabaseCount('theme_versions', 0);

        $valid = CpanelThemeVersionService::DEFAULT_TOKENS;
        $this->withTenant($admin, $first)->post(route('admin.settings.cpanel-theme.store'), [
            'name' => 'First tenant draft',
            'environment' => 'production',
            'locale' => 'en',
            'tokens' => $valid,
        ])->assertRedirect();
        $theme = ThemeVersion::withoutGlobalScopes()->where('company_id', $first->id)->where('scope', 'admin')->firstOrFail();

        $this->withTenant($admin, $second)
            ->get(route('admin.settings.cpanel-theme.index', ['version' => $theme->uuid]))
            ->assertNotFound();
        $this->withTenant($admin, $second)
            ->post(route('admin.settings.cpanel-theme.action', [$theme, 'validate']))
            ->assertNotFound();
    }

    private function withTenant(User $admin, Company $company): self
    {
        return $this->withSession(['company_id' => $company->id])->actingAs($admin);
    }
}
