<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_cpanel_sidebar_matches_the_canonical_project_one_hierarchy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        preg_match('/<aside id="admin-sidebar".*?<\/aside>/s', $html, $matches);
        $sidebar = $matches[0] ?? '';
        $this->assertNotSame('', $sidebar);

        $groups = [
            'WEBSITE &amp; PRODUCTS',
            'ONLINE SALES',
            'ORDER MANAGEMENT (6 CATEGORIES)',
            'FRANCHISE MANAGEMENT',
            'COMMUNICATION CENTER',
            'REPORTS',
            'USERS &amp; ROLES',
            'SETTINGS',
        ];
        $positions = [];
        foreach ($groups as $group) {
            $positions[$group] = strpos($sidebar, $group);
            $this->assertNotFalse($positions[$group], "Missing cPanel navigation group: {$group}");
        }
        foreach (array_values($positions) as $index => $position) {
            if ($index === 0) {
                continue;
            }
            $this->assertGreaterThan(array_values($positions)[$index - 1], $position);
        }

        $this->assertSame(1, substr_count($sidebar, 'data-admin-nav-group="order-management-6-categories"'));
        $this->assertSame(1, substr_count($sidebar, 'data-admin-nav-group="settings"'));

        foreach (['online', 'corporate', 'bulk', 'franchise', 'franchise_retail', 'buyer'] as $type) {
            $this->assertSame(1, substr_count($sidebar, 'href="'.route('admin.order-master', $type).'"'));
        }

        foreach ([
            'Settings', 'General Configuration', 'Company &amp; Branding', 'Theme Manager',
            'Email &amp; Notifications', 'WhatsApp &amp; Messaging', 'Payment Gateways',
            'Localization', 'Security &amp; Access', 'Users &amp; Roles / API Roles',
            'Application Settings', 'Document &amp; Storage', 'Integrations', 'Automations',
            'Backup &amp; Recovery', 'Audit &amp; Logs', 'System Maintenance', 'Other Settings',
        ] as $label) {
            $this->assertStringContainsString('<span>'.$label.'</span>', $sidebar, "Missing Settings item: {$label}");
        }

        $this->assertStringNotContainsString('<span>Data Management</span>', $sidebar);

        foreach (['Production', 'Finance', 'Payroll', 'HR', 'POS', 'Franchise Portal'] as $excludedArea) {
            $this->assertStringNotContainsString($excludedArea, $sidebar);
        }
    }
}
