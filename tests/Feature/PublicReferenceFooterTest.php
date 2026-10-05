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
            ->assertSee('href="/page/size-guide"', false)
            ->assertSee('href="/page/shipping-delivery"', false)
            ->assertSee('href="/returns-refunds"', false)
            ->assertSee('href="/our-story"', false)
            ->assertSee('href="/page/privacy-policy"', false)
            ->assertSee('href="/page/terms-conditions"', false)
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
    public function test_public_help_and_legal_fallback_pages_are_available(): void
    {
        $this->get('/page/size-guide')
            ->assertOk()
            ->assertSeeText('Find the right fit');

        $this->get('/page/shipping-delivery')
            ->assertOk()
            ->assertSeeText('Ireland: up to 5 working days');

        $this->get('/page/privacy-policy')
            ->assertOk()
            ->assertSeeText('Your privacy');

        $this->get('/page/terms-conditions')
            ->assertOk()
            ->assertSeeText('Website and ordering terms');
    }

    public function test_footer_destination_migration_repairs_only_legacy_factory_placeholders(): void
    {
        $company = Company::create([
            'name' => 'Legacy Footer Destination Tenant',
            'code' => 'LEGACY-FOOTER-LINKS',
            'active' => true,
        ]);

        $regions = SiteLayoutVersionService::DEFAULT_REGIONS;
        $regions['footer']['columns'][2]['links'][0]['href'] = '/factory';
        $regions['footer']['columns'][2]['links'][1]['href'] = '/factory';
        $regions['footer']['columns'][2]['links'][2]['href'] = '/factory';
        $regions['footer']['columns'][3]['links'][0]['href'] = '/factory';
        $regions['footer']['columns'][3]['links'][] = ['label' => 'Custom Factory Link', 'href' => '/factory'];
        $regions['footer']['legal_links'][0]['href'] = '/factory';
        $regions['footer']['legal_links'][1]['href'] = '/factory';

        $layout = SiteLayoutVersion::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Legacy Footer Destinations',
            'scope' => 'public',
            'environment' => 'production',
            'locale' => 'en',
            'version' => 1,
            'status' => SiteLayoutVersion::STATUS_ACTIVE,
            'regions' => $regions,
            'activated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_05_140000_repair_public_footer_destinations.php');
        $migration->up();

        $layout->refresh();
        $footer = data_get($layout->regions, 'footer');

        $this->assertSame('/page/size-guide', data_get($footer, 'columns.2.links.0.href'));
        $this->assertSame('/page/shipping-delivery', data_get($footer, 'columns.2.links.1.href'));
        $this->assertSame('/returns-refunds', data_get($footer, 'columns.2.links.2.href'));
        $this->assertSame('/our-story', data_get($footer, 'columns.3.links.0.href'));
        $this->assertSame('/factory', data_get($footer, 'columns.3.links.4.href'));
        $this->assertSame('/page/privacy-policy', data_get($footer, 'legal_links.0.href'));
        $this->assertSame('/page/terms-conditions', data_get($footer, 'legal_links.1.href'));
    }

}
