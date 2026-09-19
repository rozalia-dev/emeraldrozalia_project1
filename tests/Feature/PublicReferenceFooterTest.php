<?php

namespace Tests\Feature;

use App\Models\{Company, SiteLayoutVersion};
use App\Services\SiteLayoutVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReferenceFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_footer_matches_reference_structure_without_duplicate_bottom_brand_block(): void
    {
        $this->get('/shop')
            ->assertOk()
            ->assertSee('class="footer-main"', false)
            ->assertSee('class="footer-brand"', false)
            ->assertSee('class="footer-newsletter-form"', false)
            ->assertSee('placeholder="Your email address"', false)
            ->assertSee('class="payments"', false)
            ->assertSee('class="footer-bottom"', false)
            ->assertSee('class="footer-manufacturing"', false)
            ->assertSee('class="footer-legal"', false)
            ->assertDontSee('footer-bottom-details', false)
            ->assertDontSee('footer-bottom-contact', false)
            ->assertDontSee('footer-bottom-social', false);
    }

    public function test_active_cpanel_footer_snapshot_drives_the_public_reference_footer(): void
    {
        $company = Company::create([
            'name' => 'Footer Layout Tenant',
            'code' => 'FOOTER-LAYOUT',
            'active' => true,
        ]);

        $regions = SiteLayoutVersionService::DEFAULT_REGIONS;
        $regions['footer']['brand_description'] = 'Managed footer description';
        $regions['footer']['copyright_text'] = 'Managed copyright';
        $regions['footer']['manufacturing_text'] = 'Managed manufacturing line';
        $regions['footer']['columns'] = [[
            'title' => 'MANAGED COLUMN',
            'links' => [
                ['label' => 'Managed footer link', 'href' => '/contact'],
            ],
        ]];
        $regions['footer']['newsletter'] = [
            'enabled' => true,
            'title' => 'MANAGED NEWSLETTER',
            'description' => 'Managed newsletter copy',
            'placeholder' => 'Managed email placeholder',
            'href' => '/contact',
            'cta_label' => 'Managed submit',
        ];
        $regions['footer']['legal_links'] = [
            ['label' => 'Managed privacy', 'href' => '/factory'],
        ];

        SiteLayoutVersion::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Managed Footer',
            'scope' => 'public',
            'environment' => 'production',
            'locale' => 'en',
            'version' => 1,
            'status' => SiteLayoutVersion::STATUS_ACTIVE,
            'regions' => $regions,
            'activated_at' => now(),
        ]);

        $this->withSession(['company_id' => $company->id])->get('/shop')
            ->assertOk()
            ->assertSeeText('Managed footer description')
            ->assertSeeText('MANAGED COLUMN')
            ->assertSeeText('Managed footer link')
            ->assertSeeText('MANAGED NEWSLETTER')
            ->assertSee('placeholder="Managed email placeholder"', false)
            ->assertSee('title="Managed submit"', false)
            ->assertSeeText('Managed copyright')
            ->assertSeeText('Managed manufacturing line')
            ->assertSeeText('Managed privacy');
    }

    public function test_reference_footer_migration_upgrades_only_legacy_default_newsletter_contract(): void
    {
        $company = Company::create([
            'name' => 'Legacy Footer Tenant',
            'code' => 'LEGACY-FOOTER',
            'active' => true,
        ]);

        $regions = SiteLayoutVersionService::DEFAULT_REGIONS;
        $regions['footer']['newsletter'] = [
            'enabled' => false,
            'title' => 'NEWSLETTER',
            'description' => 'Stay updated with new arrivals and offers.',
            'href' => '/contact',
            'cta_label' => 'Contact our team',
        ];
        unset($regions['footer']['copyright_text'], $regions['footer']['manufacturing_text']);

        $layout = SiteLayoutVersion::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Legacy Footer',
            'scope' => 'public',
            'environment' => 'production',
            'locale' => 'en',
            'version' => 1,
            'status' => SiteLayoutVersion::STATUS_ACTIVE,
            'regions' => $regions,
            'activated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_19_130000_align_active_footer_with_reference_contract.php');
        $migration->up();

        $layout->refresh();

        $this->assertTrue((bool) data_get($layout->regions, 'footer.newsletter.enabled'));
        $this->assertSame('Your email address', data_get($layout->regions, 'footer.newsletter.placeholder'));
        $this->assertSame('Submit email', data_get($layout->regions, 'footer.newsletter.cta_label'));
        $this->assertSame('Designed & Manufactured in Limerick, Ireland', data_get($layout->regions, 'footer.manufacturing_text'));
        $this->assertTrue(array_key_exists('copyright_text', data_get($layout->regions, 'footer')));
    }
}
