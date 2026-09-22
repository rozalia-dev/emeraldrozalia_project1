<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SiteLayoutVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderFooterManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_header_navigation_footer_and_publish_to_public_shell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create([
            'name' => 'Header Footer Tenant',
            'code' => 'HEADER-FOOTER',
            'active' => true,
        ]);

        $this->withTenant($admin, $company)
            ->get(route('admin.pages.header-footer'))
            ->assertOk()
            ->assertSeeText('Header & Footer Manager')
            ->assertSeeText('Header Logo')
            ->assertSeeText('Header Navigation')
            ->assertSeeText('Footer Columns & Links')
            ->assertSeeText('Add Menu Item')
            ->assertSeeText('Add Footer Column');

        $this->withTenant($admin, $company)
            ->post(route('admin.pages.header-footer.save'), [
                'locale' => 'en',
                'header_logo' => '/assets/logo/logo_two_line.png',
                'header_logo_alt' => 'Emerald Rozalia Limited',
                'navigation' => [
                    ['label' => 'HOME', 'href' => '/', 'enabled' => 1],
                    ['label' => 'MANAGED SHOP', 'href' => '/shop', 'enabled' => 1],
                    ['label' => 'REMOVE ME', 'href' => '/factory', 'enabled' => 0],
                ],
                'footer_logo' => '/assets/logo/logo_one_line.png',
                'footer_logo_alt' => 'Emerald Rozalia Limited',
                'footer_brand_description' => 'Managed from the dedicated footer manager.',
                'footer_copyright_text' => 'Managed footer copyright',
                'footer_manufacturing_text' => 'Managed in Limerick, Ireland',
                'footer_columns' => [
                    [
                        'title' => 'CUSTOMER CARE',
                        'links' => [
                            ['label' => 'Contact Team', 'href' => '/contact'],
                            ['label' => 'Delete Later', 'href' => '/factory'],
                        ],
                    ],
                    [
                        'title' => 'COMPANY',
                        'links' => [
                            ['label' => 'About Manufacturing', 'href' => '/factory'],
                        ],
                    ],
                ],
                'footer_legal_links' => [
                    ['label' => 'Privacy', 'href' => '/factory'],
                    ['label' => 'Terms', 'href' => '/factory'],
                ],
            ])
            ->assertRedirect(route('admin.pages.header-footer', ['locale' => 'en']));

        $draft = SiteLayoutVersion::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', SiteLayoutVersion::STATUS_DRAFT)
            ->firstOrFail();

        $this->assertSame('/assets/logo/logo_two_line.png', data_get($draft->regions, 'header.logo.path'));
        $this->assertSame('MANAGED SHOP', data_get($draft->regions, 'header.primary_menu.1.label'));
        $this->assertSame('/assets/logo/logo_one_line.png', data_get($draft->regions, 'footer.logo.path'));
        $this->assertSame('Contact Team', data_get($draft->regions, 'footer.columns.0.links.0.label'));

        // Edit existing values and delete rows by omitting them from the saved arrays.
        $this->withTenant($admin, $company)
            ->post(route('admin.pages.header-footer.save'), [
                'locale' => 'en',
                'header_logo' => '/assets/logo/logo_one_line.png',
                'header_logo_alt' => 'Emerald Rozalia',
                'navigation' => [
                    ['label' => 'SHOP NOW', 'href' => '/shop', 'enabled' => 1],
                ],
                'footer_logo' => '/assets/logo/logo_two_line.png',
                'footer_logo_alt' => 'Emerald Rozalia Limited',
                'footer_brand_description' => 'Updated footer copy.',
                'footer_copyright_text' => 'Updated copyright',
                'footer_manufacturing_text' => 'Designed & Manufactured in Limerick, Ireland',
                'footer_columns' => [
                    [
                        'title' => 'HELP',
                        'links' => [
                            ['label' => 'Contact Us', 'href' => '/contact'],
                        ],
                    ],
                ],
                'footer_legal_links' => [
                    ['label' => 'Privacy Policy', 'href' => '/factory'],
                ],
            ])
            ->assertRedirect();

        $draft->refresh();
        $this->assertCount(1, data_get($draft->regions, 'header.primary_menu'));
        $this->assertSame('SHOP NOW', data_get($draft->regions, 'header.primary_menu.0.label'));
        $this->assertCount(1, data_get($draft->regions, 'footer.columns'));
        $this->assertSame('HELP', data_get($draft->regions, 'footer.columns.0.title'));
        $this->assertCount(1, data_get($draft->regions, 'footer.legal_links'));

        $this->withTenant($admin, $company)
            ->post(route('admin.pages.header-footer.publish', $draft))
            ->assertRedirect(route('admin.pages.header-footer', ['locale' => 'en']));

        $draft->refresh();
        $this->assertSame(SiteLayoutVersion::STATUS_ACTIVE, $draft->status);

        $this->withSession(['company_id' => $company->id])
            ->get('/shop')
            ->assertOk()
            ->assertSeeText('SHOP NOW')
            ->assertSeeText('Updated footer copy.')
            ->assertSeeText('HELP')
            ->assertSeeText('Contact Us')
            ->assertSeeText('Privacy Policy')
            ->assertDontSeeText('REMOVE ME')
            ->assertDontSeeText('Delete Later');
    }

    public function test_header_footer_manager_rejects_unapproved_logo_and_unsafe_navigation_url(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create([
            'name' => 'Safe Header Footer Tenant',
            'code' => 'SAFE-HEADER-FOOTER',
            'active' => true,
        ]);

        $this->withTenant($admin, $company)
            ->post(route('admin.pages.header-footer.save'), [
                'locale' => 'en',
                'header_logo' => '/uploads/random-logo.svg',
                'header_logo_alt' => 'Random',
                'navigation' => [
                    ['label' => 'Unsafe', 'href' => 'javascript:alert(1)', 'enabled' => 1],
                ],
                'footer_logo' => '/assets/logo/logo_two_line.png',
                'footer_logo_alt' => 'Emerald Rozalia Limited',
            ])
            ->assertSessionHasErrors('header_logo');

        $this->assertDatabaseCount('site_layout_versions', 0);
    }

    private function withTenant(User $admin, Company $company): self
    {
        return $this->withSession(['company_id' => $company->id])->actingAs($admin);
    }
}
