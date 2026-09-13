<?php

namespace Tests\Feature;

use App\Models\{Company, SiteLayoutVersion, User};
use App\Services\SiteLayoutVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteLayoutVersionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_layout_is_empty_until_a_draft_is_created_and_published_as_one_snapshot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Layout Tenant', 'code' => 'LAYOUT', 'active' => true]);

        $this->withTenant($admin, $company)->get(route('admin.pages.layouts'))
            ->assertOk()
            ->assertSeeText('No shared layout versions yet.')
            ->assertSeeText('Create the first layout draft');

        $regions = SiteLayoutVersionService::DEFAULT_REGIONS;
        $regions['header']['announcement']['headline'] = 'A managed public shell';
        $this->withTenant($admin, $company)->post(route('admin.pages.layouts.store'), [
            'name' => 'Managed public shell',
            'environment' => 'production',
            'locale' => 'en',
            'regions' => $regions,
        ])->assertRedirect();

        $layout = SiteLayoutVersion::withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame(SiteLayoutVersion::STATUS_DRAFT, $layout->status);

        $this->withTenant($admin, $company)->get(route('admin.pages.layouts.preview', $layout))
            ->assertOk()
            ->assertSeeText('Previewing shared layout · Homepage render')
            ->assertSee('data-public-layout-source="draft-layout-preview"', false);

        foreach (['validate', 'submit', 'approve', 'activate'] as $action) {
            $this->withTenant($admin, $company)->post(route('admin.pages.layouts.action', ['layout' => $layout, 'action' => $action]))->assertRedirect();
            $layout = $layout->fresh();
        }

        $this->withSession(['company_id' => $company->id])->get('/')
            ->assertOk()
            ->assertSee('data-public-layout-source="active-layout-version"', false)
            ->assertSee('data-public-layout-version="1"', false)
            ->assertSeeText('A managed public shell');

        $this->assertDatabaseHas('audit_logs', ['action' => 'pages.layout.draft_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pages.layout.validated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pages.layout.submitted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pages.layout.approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pages.layout.activated']);
    }

    public function test_unsafe_shared_layout_links_are_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Unsafe Layout Tenant', 'code' => 'UNSAFE-LAYOUT', 'active' => true]);
        $regions = SiteLayoutVersionService::DEFAULT_REGIONS;
        $regions['header']['primary_menu'][0]['href'] = 'javascript:alert(1)';

        $this->withTenant($admin, $company)->post(route('admin.pages.layouts.store'), [
            'name' => 'Unsafe shell',
            'environment' => 'production',
            'locale' => 'en',
            'regions' => $regions,
        ])->assertSessionHasErrors('header.primary_menu.0.href');

        $this->assertDatabaseCount('site_layout_versions', 0);
    }

    public function test_layout_versions_are_not_mutable_from_another_company_context(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = Company::create(['name' => 'First Layout Tenant', 'code' => 'FIRST-LAYOUT', 'active' => true]);
        $second = Company::create(['name' => 'Second Layout Tenant', 'code' => 'SECOND-LAYOUT', 'active' => true]);
        $this->withTenant($admin, $first)->post(route('admin.pages.layouts.store'), [
            'name' => 'First shell', 'environment' => 'production', 'locale' => 'en', 'regions' => SiteLayoutVersionService::DEFAULT_REGIONS,
        ])->assertRedirect();
        $layout = SiteLayoutVersion::withoutGlobalScopes()->where('company_id', $first->id)->firstOrFail();

        $this->withTenant($admin, $second)->get(route('admin.pages.layouts', ['version' => $layout->uuid]))->assertNotFound();
        $this->withTenant($admin, $second)->post(route('admin.pages.layouts.action', ['layout' => $layout, 'action' => 'validate']))->assertNotFound();
    }

    private function withTenant(User $admin, Company $company): self
    {
        return $this->withSession(['company_id' => $company->id])->actingAs($admin);
    }
}
