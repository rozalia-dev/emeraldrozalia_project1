<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsNavigationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_complete_settings_catalogue_and_branding_tabs_are_reachable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sections = [
            'general-configuration', 'company-branding', 'email-notifications', 'whatsapp-messaging',
            'payment-gateways', 'localization', 'security-access', 'api-roles', 'application-settings',
            'document-storage', 'integrations', 'automations', 'backup-recovery', 'audit-logs',
            'system-maintenance', 'other-settings',
        ];

        $response = $this->actingAs($admin)->get(route('admin.settings.overview'))->assertOk();
        foreach ($sections as $section) {
            $response->assertSee(route('admin.settings.page', $section), false);
        }
        $response->assertSee(route('admin.settings.theme.index'), false);
        $response->assertDontSee('186 total', false)->assertDontSee('82%', false)->assertDontSee('8.4%', false);

        $branding = $this->actingAs($admin)->get(route('admin.settings.page', 'company-branding'))->assertOk();
        foreach (['Company Information', 'Branding & Logo', 'Brand Assets', 'Theme & Colors', 'Headers & Footers', 'Legal & Compliance', 'Social Media', 'Print & Documents'] as $tab) {
            $branding->assertSeeText($tab);
        }
        $branding->assertSee(route('admin.settings.theme.index'), false);
    }
}
