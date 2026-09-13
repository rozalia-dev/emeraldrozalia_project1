<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ThemeVersion;
use App\Models\User;
use App\Services\ThemeVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeVersionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_theme_manager_is_honest_and_the_full_lifecycle_publishes_one_snapshot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Theme Tenant', 'code' => 'THEME', 'active' => true]);

        $this->withTenant($admin, $company)->get(route('admin.settings.theme.index'))
            ->assertOk()
            ->assertSeeText('No theme versions yet.')
            ->assertSeeText('Create a theme draft');

        $theme = $this->createDraft($admin, $company, 'Emerald Premium');
        $this->assertSame(ThemeVersion::STATUS_DRAFT, $theme->status);

        $this->withTenant($admin, $company)->post($this->actionUrl($theme, 'validate'))->assertRedirect();
        $this->assertNotNull($theme->fresh()->validated_at);

        $this->withTenant($admin, $company)->post($this->actionUrl($theme, 'submit'))->assertRedirect();
        $this->assertSame(ThemeVersion::STATUS_PENDING_APPROVAL, $theme->fresh()->status);

        $this->withTenant($admin, $company)->post($this->actionUrl($theme, 'approve'))->assertRedirect();
        $this->assertSame(ThemeVersion::STATUS_APPROVED, $theme->fresh()->status);

        $this->withTenant($admin, $company)->post($this->actionUrl($theme, 'activate'))->assertRedirect();
        $this->assertSame(ThemeVersion::STATUS_ACTIVE, $theme->fresh()->status);

        $this->withSession(['company_id' => $company->id])->get('/')
            ->assertOk()
            ->assertSee('data-public-theme-source="active-theme-version"', false)
            ->assertSee('data-public-theme-version="1"', false)
            ->assertSee('--site-surface:', false)
            ->assertSee('--site-motion-duration: 180ms', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.theme.draft_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.theme.validated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.theme.submitted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.theme.approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.theme.activated']);
    }

    public function test_invalid_theme_tokens_are_rejected_without_creating_a_revision(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Invalid Theme Tenant', 'code' => 'INVALID', 'active' => true]);
        $tokens = ThemeVersionService::DEFAULT_TOKENS;
        $tokens['colors']['primary'] = '#12';

        $this->withTenant($admin, $company)->post(route('admin.settings.theme.store'), [
            'name' => 'Invalid theme',
            'environment' => 'production',
            'locale' => 'en',
            'tokens' => $tokens,
        ])->assertSessionHasErrors('colors.primary');

        $this->assertDatabaseCount('theme_versions', 0);
    }

    public function test_activation_supersedes_the_previous_version_and_rollback_creates_a_new_active_version(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Rollback Tenant', 'code' => 'ROLLBACK', 'active' => true]);
        $first = $this->activateTheme($admin, $company, 'First theme', '#075b2f');
        $second = $this->createDraft($admin, $company, 'Second theme', '#123456');

        $this->withTenant($admin, $company)->post($this->actionUrl($second, 'validate'))->assertRedirect();
        $this->withTenant($admin, $company)->post($this->actionUrl($second, 'submit'))->assertRedirect();
        $this->withTenant($admin, $company)->post($this->actionUrl($second, 'approve'))->assertRedirect();
        $this->withTenant($admin, $company)->post($this->actionUrl($second, 'activate'))->assertRedirect();

        $this->assertSame(ThemeVersion::STATUS_SUPERSEDED, $first->fresh()->status);
        $this->assertSame(ThemeVersion::STATUS_ACTIVE, $second->fresh()->status);

        $this->withTenant($admin, $company)->post($this->actionUrl($first, 'rollback'))->assertRedirect();
        $rollback = ThemeVersion::withoutGlobalScopes()->where('company_id', $company->id)->latest('version')->firstOrFail();

        $this->assertSame(3, $rollback->version);
        $this->assertSame(ThemeVersion::STATUS_ACTIVE, $rollback->status);
        $this->assertSame($first->uuid, $rollback->source_version_uuid);
        $this->assertSame('#075b2f', data_get($rollback->token_payload, 'colors.primary'));
        $this->assertSame(ThemeVersion::STATUS_SUPERSEDED, $second->fresh()->status);
        $this->assertSame('#075b2f', data_get(app(\App\Services\ThemeVersionService::class)->publicSnapshot($company)['tokens'], 'colors.primary'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.theme.rolled_back', 'subject_uuid' => $rollback->uuid]);
    }

    public function test_theme_versions_are_not_readable_or_mutable_from_another_company_context(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = Company::create(['name' => 'First Theme Tenant', 'code' => 'FIRST-THEME', 'active' => true]);
        $second = Company::create(['name' => 'Second Theme Tenant', 'code' => 'SECOND-THEME', 'active' => true]);
        $theme = $this->createDraft($admin, $first, 'First theme');

        $this->withTenant($admin, $second)->get(route('admin.settings.theme.index', ['version' => $theme->uuid]))->assertNotFound();
        $this->withTenant($admin, $second)->post($this->actionUrl($theme, 'validate'))->assertNotFound();
    }

    private function createDraft(User $admin, Company $company, string $name, ?string $primary = null): ThemeVersion
    {
        $tokens = ThemeVersionService::DEFAULT_TOKENS;
        if ($primary) $tokens['colors']['primary'] = $primary;

        $this->withTenant($admin, $company)->post(route('admin.settings.theme.store'), [
            'name' => $name,
            'environment' => 'production',
            'locale' => 'en',
            'tokens' => $tokens,
        ])->assertRedirect();

        return ThemeVersion::withoutGlobalScopes()->where('company_id', $company->id)->where('name', $name)->firstOrFail();
    }

    private function activateTheme(User $admin, Company $company, string $name, string $primary): ThemeVersion
    {
        $theme = $this->createDraft($admin, $company, $name, $primary);
        foreach (['validate', 'submit', 'approve', 'activate'] as $action) {
            $this->withTenant($admin, $company)->post($this->actionUrl($theme, $action))->assertRedirect();
        }

        return $theme->fresh();
    }

    private function actionUrl(ThemeVersion $theme, string $action): string
    {
        return route('admin.settings.theme.action', ['theme' => $theme, 'action' => $action]);
    }

    private function withTenant(User $admin, Company $company): self
    {
        return $this->withSession(['company_id' => $company->id])->actingAs($admin);
    }
}
