<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_settings_reference_pages_render_inside_the_existing_admin_shell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $pages = [
            'general-configuration' => 'General Configuration',
            'company-branding' => 'Company & Branding',
            'email-notifications' => 'Email & Notifications',
            'whatsapp-messaging' => 'WhatsApp & Messaging',
            'payment-gateways' => 'Payment Gateways',
            'localization' => 'Localization',
            'security-access' => 'Security & Access',
            'api-roles' => 'Users & Roles',
            'application-settings' => 'Application Settings',
            'document-storage' => 'Document & Storage',
            'integrations' => 'Integrations',
            'automations' => 'Automations',
            'backup-recovery' => 'Backup & Recovery',
            'audit-logs' => 'Audit & Logs',
            'system-maintenance' => 'System Maintenance',
            'other-settings' => 'Other Settings',
        ];

        $this->actingAs($admin)->get(route('admin.settings.overview'))
            ->assertOk()
            ->assertSeeText('Settings')
            ->assertSeeText('Settings Categories')
            ->assertSee('/css/settings-reference.css?v=20260910-1', false);

        foreach ($pages as $section => $heading) {
            $this->actingAs($admin)->get(route('admin.settings.page', $section))
                ->assertOk()
                ->assertSeeText($heading)
                ->assertSeeText('Save Changes')
                ->assertSee('/css/settings-reference.css?v=20260910-1', false);
        }
    }

    public function test_settings_are_persisted_with_company_sync_and_audit_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.settings.save', 'general-configuration'), [
            'company_name' => 'Emerald Rozalia Test Tenant',
            'system_name' => 'Project 1 Test Configuration',
            'primary_email' => 'urmos@rozalia.ie',
            'default_language' => 'en',
            'default_currency' => 'EUR',
            'tab' => 'basic',
        ])->assertRedirect(route('admin.settings.page', ['section' => 'general-configuration', 'tab' => 'basic']));

        $record = AdminRecord::query()->where('module', 'system-settings')->where('reference', 'general-configuration')->firstOrFail();
        $this->assertSame('Project 1 Test Configuration', data_get($record->data, 'system_name'));
        $this->assertSame('Emerald Rozalia Test Tenant', data_get($record->data, 'company_name'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.general-configuration.updated', 'subject_id' => $record->id]);
    }

    public function test_api_roles_automations_and_settings_backups_are_functional(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.settings.api-roles.store'), [
            'name' => 'Test Reporting API',
            'description' => 'Read-only reporting integration',
            'scopes' => 'read:reports, read:orders',
            'environment' => 'staging',
            'active' => '1',
        ])->assertRedirect();

        $role = AdminRecord::query()->where('module', 'api-roles')->where('title', 'Test Reporting API')->firstOrFail();
        $this->assertSame(['read:reports', 'read:orders'], data_get($role->data, 'scopes'));
        $this->actingAs($admin)->post(route('admin.settings.api-roles.toggle', $role))->assertRedirect();
        $this->assertSame('revoked', $role->fresh()->status);

        $this->actingAs($admin)->post(route('admin.settings.automations.store'), [
            'name' => 'Test order notification',
            'event' => 'order.created',
            'actions' => 'Send email, Create task',
            'enabled' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('automation_rules', ['name' => 'Test order notification', 'event' => 'order.created', 'enabled' => true]);

        $this->actingAs($admin)->post(route('admin.settings.backups.store'))->assertRedirect();
        $this->assertDatabaseHas('backup_runs', ['type' => 'Settings', 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.backup.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.api-role.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.automation.created']);
    }
}
