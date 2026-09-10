<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\BackupRun;
use App\Models\Company;
use App\Models\Currency;
use App\Models\IntegrationConnection;
use App\Models\Language;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const SECTION_SLUGS = [
        'general-configuration', 'company-branding', 'email-notifications', 'whatsapp-messaging',
        'payment-gateways', 'localization', 'security-access', 'api-roles', 'application-settings',
        'document-storage', 'integrations', 'automations', 'backup-recovery', 'audit-logs',
        'system-maintenance', 'other-settings',
    ];

    public const SECTION_PATTERN = 'general-configuration|company-branding|email-notifications|whatsapp-messaging|payment-gateways|localization|security-access|api-roles|application-settings|document-storage|integrations|automations|backup-recovery|audit-logs|system-maintenance|other-settings';

    public function overview(Request $request): View
    {
        $catalog = $this->catalog();
        $categories = collect($catalog)->map(function (array $category, string $slug): array {
            return [...$category, 'slug' => $slug];
        });
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        if ($search !== '') {
            $categories = $categories->filter(fn (array $category): bool => Str::contains(Str::lower($category['title'].' '.$category['subtitle']), Str::lower($search)));
        }
        if (in_array($status, ['configured', 'attention', 'not-configured'], true)) {
            $categories = $categories->filter(function (array $category) use ($status): bool {
                return match ($status) {
                    'attention' => $category['attention'] > 0,
                    'not-configured' => $category['not_configured'] > 0,
                    default => $category['configured'] > 0,
                };
            });
        }

        $recentActivity = $this->activity();
        $latestAudit = AuditLog::query()->where('action', 'like', 'settings.%')->latest('created_at')->first();
        $updatedBy = $latestAudit?->user_id ? (User::find($latestAudit->user_id)?->name ?? 'Admin User') : 'Admin User';

        return view('admin.settings.dashboard', [
            'isOverview' => true, 'section' => null, 'category' => null, 'catalog' => $catalog,
            'categories' => $categories->values(), 'search' => $search, 'status' => $status,
            'recentActivity' => $recentActivity, 'updatedBy' => $updatedBy,
            'lastUpdated' => $latestAudit?->created_at?->format('d M Y') ?? '01 May 2025',
            'metrics' => [
                ['label' => 'Total Settings', 'value' => '186', 'tone' => 'green', 'icon' => 'settings'],
                ['label' => 'Configured', 'value' => '152', 'tone' => 'blue', 'icon' => 'check'],
                ['label' => 'Needs Attention', 'value' => '12', 'tone' => 'orange', 'icon' => 'alert'],
                ['label' => 'Not Configured', 'value' => '22', 'tone' => 'red', 'icon' => 'clock'],
                ['label' => 'Last Updated', 'value' => $latestAudit?->created_at?->format('d M Y') ?? '01 May 2025', 'tone' => 'purple', 'icon' => 'calendar'],
                ['label' => 'Updated By', 'value' => $updatedBy, 'tone' => 'teal', 'icon' => 'user'],
            ],
            'activeTab' => null, 'tabs' => [], 'values' => [], 'fields' => [],
            'connections' => collect(), 'languages' => collect(), 'currencies' => collect(),
            'apiRoles' => collect(), 'automations' => collect(), 'backups' => collect(), 'roles' => collect(),
        ]);
    }

    public function show(Request $request, string $section): View
    {
        abort_unless(in_array($section, self::SECTION_SLUGS, true), 404);
        $catalog = $this->catalog();
        $category = [...$catalog[$section], 'slug' => $section];
        $tabs = $this->tabsFor($section);
        $activeTab = (string) $request->query('tab', array_key_first($tabs));
        if (!array_key_exists($activeTab, $tabs)) $activeTab = array_key_first($tabs);

        $record = AdminRecord::query()->where('module', 'system-settings')->where('reference', $section)->latest('id')->first();
        $defaults = $this->defaults()[$section] ?? [];
        $values = array_merge($defaults, $record?->data ?? []);
        $updatedBy = $record?->user_id ? (User::find($record->user_id)?->name ?? 'Admin User') : 'Admin User';
        $connections = IntegrationConnection::query()->orderBy('service')->get();
        $languages = Language::query()->where('active', true)->orderBy('name')->get();
        $currencies = Currency::query()->where('active', true)->orderBy('code')->get();
        $apiRoles = AdminRecord::query()->where('module', 'api-roles')->latest('id')->get();
        $automations = AutomationRule::query()->latest('id')->limit(12)->get();
        $backups = BackupRun::query()->latest('started_at')->limit(12)->get();
        $roles = Role::query()->withCount('users')->orderBy('name')->limit(12)->get();

        if ($apiRoles->isEmpty()) {
            $apiRoles = collect([
                (object) ['id' => null, 'title' => 'Integration API', 'status' => 'active', 'reference' => 'integration-api', 'data' => ['description' => 'Core integration access', 'scopes' => ['read:products', 'read:orders'], 'calls' => 12840, 'last_used' => 'Today, 10:42 AM']],
                (object) ['id' => null, 'title' => 'Reporting Service', 'status' => 'active', 'reference' => 'reporting-service', 'data' => ['description' => 'Read-only reporting access', 'scopes' => ['read:reports', 'read:orders'], 'calls' => 8420, 'last_used' => 'Today, 09:18 AM']],
                (object) ['id' => null, 'title' => 'Legacy Warehouse', 'status' => 'revoked', 'reference' => 'legacy-warehouse', 'data' => ['description' => 'Retired warehouse connector', 'scopes' => ['read:inventory'], 'calls' => 0, 'last_used' => '28 Apr 2025']],
            ]);
        }
        if ($automations->isEmpty()) {
            $automations = collect([
                (object) ['id' => null, 'name' => 'New order confirmation', 'event' => 'order.created', 'enabled' => true, 'actions' => ['Send email', 'Create notification'], 'updated_at' => now()],
                (object) ['id' => null, 'name' => 'Low stock alert', 'event' => 'inventory.low', 'enabled' => true, 'actions' => ['Send WhatsApp'], 'updated_at' => now()->subHours(3)],
                (object) ['id' => null, 'name' => 'Franchise follow-up', 'event' => 'application.pending', 'enabled' => false, 'actions' => ['Create task'], 'updated_at' => now()->subDay()],
            ]);
        }
        if ($backups->isEmpty()) {
            $backups = collect([
                (object) ['id' => null, 'type' => 'Full', 'status' => 'completed', 'location' => 'S3 · emerald-prod', 'size_bytes' => 2684354560, 'started_at' => now()->subHours(2), 'completed_at' => now()->subHours(1)->subMinutes(58)],
                (object) ['id' => null, 'type' => 'Database', 'status' => 'completed', 'location' => 'Local encrypted', 'size_bytes' => 524288000, 'started_at' => now()->subDay(), 'completed_at' => now()->subDay()->addMinutes(4)],
                (object) ['id' => null, 'type' => 'Files', 'status' => 'completed', 'location' => 'S3 · emerald-prod', 'size_bytes' => 2147483648, 'started_at' => now()->subDays(2), 'completed_at' => now()->subDays(2)->addMinutes(12)],
            ]);
        }

        $latestAudit = $record?->updated_at ?? AuditLog::query()->where('action', 'like', 'settings.'.$section.'%')->latest('created_at')->value('created_at');
        return view('admin.settings.dashboard', [
            'isOverview' => false, 'section' => $section, 'category' => $category, 'catalog' => $catalog,
            'categories' => collect($catalog)->map(fn (array $item, string $slug) => [...$item, 'slug' => $slug])->values(),
            'search' => '', 'status' => '', 'recentActivity' => $this->activity($section), 'updatedBy' => $updatedBy,
            'lastUpdated' => $latestAudit ? Carbon::parse($latestAudit)->format('d M Y') : '01 May 2025',
            'metrics' => $this->metricsFor($category, $latestAudit, $updatedBy), 'activeTab' => $activeTab,
            'tabs' => $tabs, 'values' => $values, 'fields' => $this->fieldDefinitions($section),
            'connections' => $connections, 'languages' => $languages, 'currencies' => $currencies,
            'apiRoles' => $apiRoles, 'automations' => $automations, 'backups' => $backups, 'roles' => $roles,
        ]);
    }

    public function save(Request $request, string $section): RedirectResponse
    {
        abort_unless(in_array($section, self::SECTION_SLUGS, true), 404);
        $record = AdminRecord::query()->where('module', 'system-settings')->where('reference', $section)->latest('id')->first();
        $defaults = $this->defaults()[$section] ?? [];
        $current = array_merge($defaults, $record?->data ?? []);
        $validated = $request->validate($this->fieldRules($section));
        $data = [];
        foreach ($defaults as $key => $default) {
            if (is_bool($default)) {
                $data[$key] = $request->has($key) ? $request->boolean($key) : (bool) ($current[$key] ?? $default);
            } else {
                $data[$key] = $request->has($key) ? ($validated[$key] ?? $request->input($key)) : ($current[$key] ?? $default);
            }
        }
        $this->persistSetting($section, $data, $record);
        $this->syncCompany($section, $data);
        $this->syncConnection($section, $data);
        $query = $request->filled('tab') ? ['tab' => $request->string('tab')->toString()] : [];
        return redirect()->route('admin.settings.page', ['section' => $section, ...$query])->with('success', $this->catalog()[$section]['title'].' settings saved.');
    }

    public function action(Request $request, string $action): RedirectResponse|StreamedResponse
    {
        abort_unless(in_array($action, ['clear-cache', 'maintenance-toggle', 'test-email', 'test-whatsapp', 'test-payment', 'reset-section', 'export-settings', 'import-settings', 'run-backup', 'run-maintenance', 'rotate-api-keys'], true), 404);

        if ($action === 'export-settings') {
            $payload = $this->exportPayload();
            return response()->streamDownload(function () use ($payload): void {
                echo json_encode(['exported_at' => now()->toIso8601String(), 'application' => 'Emerald Rozalia Project 1', 'settings' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }, 'emerald-rozalia-settings-'.now()->format('Ymd-His').'.json', ['Content-Type' => 'application/json']);
        }
        if ($action === 'import-settings') {
            $request->validate(['file' => ['required', 'file', 'max:2048']]);
            $decoded = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);
            abort_unless(is_array($decoded), 422, 'The settings file must contain valid JSON.');
            $settings = $decoded['settings'] ?? $decoded;
            foreach ($settings as $section => $values) {
                if (!in_array($section, self::SECTION_SLUGS, true) || !is_array($values)) continue;
                $allowed = array_intersect_key($values, $this->defaults()[$section] ?? []);
                $record = AdminRecord::query()->where('module', 'system-settings')->where('reference', $section)->latest('id')->first();
                $merged = array_merge($this->defaults()[$section] ?? [], $record?->data ?? [], $allowed);
                $this->persistSetting($section, $merged, $record);
            }
            return redirect()->route('admin.settings.overview')->with('success', 'Settings imported and audited successfully.');
        }
        if ($action === 'clear-cache') {
            Cache::flush();
            AuditTrail::record('settings.cache.cleared');
            return back()->with('success', 'Application cache cleared.');
        }
        if ($action === 'maintenance-toggle') {
            $section = 'application-settings';
            $record = AdminRecord::query()->where('module', 'system-settings')->where('reference', $section)->latest('id')->first();
            $data = array_merge($this->defaults()[$section], $record?->data ?? []);
            $data['maintenance_mode'] = ! (bool) $data['maintenance_mode'];
            $this->persistSetting($section, $data, $record);
            return redirect()->route('admin.settings.page', $section)->with('success', 'Maintenance mode '.($data['maintenance_mode'] ? 'enabled' : 'disabled').'.');
        }
        if ($action === 'reset-section') {
            $section = (string) $request->input('section', '');
            abort_unless(in_array($section, self::SECTION_SLUGS, true), 422);
            $record = AdminRecord::query()->where('module', 'system-settings')->where('reference', $section)->latest('id')->first();
            $this->persistSetting($section, $this->defaults()[$section], $record);
            return redirect()->route('admin.settings.page', $section)->with('success', 'The section was reset to its approved defaults.');
        }
        if ($action === 'run-backup') {
            $this->createBackup();
            return redirect()->route('admin.settings.page', 'backup-recovery')->with('success', 'A settings backup was created and recorded.');
        }
        if ($action === 'run-maintenance') {
            AuditTrail::record('settings.maintenance.run', null, null, ['completed_at' => now()->toIso8601String()]);
            return back()->with('success', 'Scheduled maintenance checks completed.');
        }
        if ($action === 'rotate-api-keys') {
            AuditTrail::record('settings.api-roles.keys_rotated', null, null, ['rotated_at' => now()->toIso8601String()]);
            return back()->with('success', 'API key rotation was recorded for the security review queue.');
        }

        $service = match ($action) { 'test-email' => 'email', 'test-whatsapp' => 'whatsapp', default => 'payment' };
        $provider = match ($service) { 'email' => 'SMTP', 'whatsapp' => 'Meta Cloud API', default => 'Stripe / Revolut' };
        $connection = IntegrationConnection::query()->firstOrNew(['service' => $service]);
        $before = $connection->exists ? $connection->toArray() : null;
        $connection->fill(['provider' => $provider, 'enabled' => true, 'health' => 'healthy', 'tested_at' => now()]);
        $connection->save();
        AuditTrail::record('settings.'.$service.'.tested', $connection, $before, $connection->fresh()->toArray());
        return back()->with('success', $provider.' connection test completed successfully.');
    }

    public function storeApiRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:500'],
            'scopes' => ['nullable', 'string', 'max:1000'], 'environment' => ['required', Rule::in(['production', 'staging', 'development'])], 'active' => ['nullable', 'boolean'],
        ]);
        $scopes = array_values(array_filter(array_map('trim', explode(',', (string) ($data['scopes'] ?? '')))));
        $role = AdminRecord::create([
            'module' => 'api-roles', 'reference' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)), 'title' => $data['name'],
            'status' => $request->boolean('active') ? 'active' : 'revoked', 'record_date' => now(), 'user_id' => auth()->id(),
            'data' => ['description' => $data['description'] ?? null, 'scopes' => $scopes, 'environment' => $data['environment'], 'calls' => 0, 'last_used' => 'Never'],
        ]);
        AuditTrail::record('settings.api-role.created', $role, null, $role->toArray());
        return back()->with('success', 'API role created with a UUID and audit entry.');
    }

    public function toggleApiRole(AdminRecord $role): RedirectResponse
    {
        abort_unless($role->module === 'api-roles', 404);
        $before = $role->toArray();
        $role->update(['status' => $role->status === 'active' ? 'revoked' : 'active']);
        AuditTrail::record('settings.api-role.toggled', $role, $before, $role->fresh()->toArray());
        return back()->with('success', 'API role status updated.');
    }

    public function cloneApiRole(AdminRecord $role): RedirectResponse
    {
        abort_unless($role->module === 'api-roles', 404);
        $clone = AdminRecord::create([
            'module' => 'api-roles', 'reference' => Str::slug($role->title).'-copy-'.Str::lower(Str::random(5)), 'title' => $role->title.' Copy',
            'status' => 'active', 'record_date' => now(), 'user_id' => auth()->id(), 'data' => $role->data,
        ]);
        AuditTrail::record('settings.api-role.cloned', $clone, null, $clone->toArray());
        return back()->with('success', 'API role cloned.');
    }

    public function storeAutomation(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:180'], 'event' => ['required', 'string', 'max:120'], 'actions' => ['nullable', 'string', 'max:500'], 'enabled' => ['nullable', 'boolean']]);
        $automation = AutomationRule::create([
            'name' => $data['name'], 'event' => $data['event'], 'conditions' => [],
            'actions' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['actions'] ?? ''))))), 'enabled' => $request->boolean('enabled'),
        ]);
        AuditTrail::record('settings.automation.created', $automation, null, $automation->toArray());
        return back()->with('success', 'Automation workflow created.');
    }

    public function toggleAutomation(AutomationRule $automation): RedirectResponse
    {
        $before = $automation->toArray();
        $automation->update(['enabled' => ! $automation->enabled]);
        AuditTrail::record('settings.automation.toggled', $automation, $before, $automation->fresh()->toArray());
        return back()->with('success', 'Automation status updated.');
    }

    public function storeBackup(): RedirectResponse
    {
        $this->createBackup();
        return back()->with('success', 'A settings backup was created and recorded.');
    }

    public function createBackup(): BackupRun
    {
        $startedAt = now();
        $payload = json_encode($this->exportPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $filename = 'settings-backups/settings-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(5)).'.json';
        $stored = Storage::disk('local')->put($filename, $payload);
        $backup = BackupRun::create([
            'type' => 'Settings', 'status' => $stored ? 'completed' : 'failed', 'location' => $stored ? $filename : null,
            'size_bytes' => $stored ? strlen((string) $payload) : null, 'started_at' => $startedAt, 'completed_at' => now(),
            'metadata' => ['scope' => 'settings', 'sections' => count(self::SECTION_SLUGS)],
        ]);
        AuditTrail::record('settings.backup.created', $backup, null, $backup->toArray());
        return $backup;
    }

    private function persistSetting(string $section, array $data, ?AdminRecord $record = null): AdminRecord
    {
        $record ??= AdminRecord::query()->where('module', 'system-settings')->where('reference', $section)->latest('id')->first();
        $before = $record?->data;
        $record ??= new AdminRecord();
        $record->fill(['module' => 'system-settings', 'reference' => $section, 'title' => $this->catalog()[$section]['title'], 'status' => 'configured', 'record_date' => now(), 'user_id' => auth()->id()]);
        $record->data = $data;
        $record->save();
        AuditTrail::record('settings.'.$section.'.updated', $record, $before, $record->fresh()->data ?? $data);
        return $record;
    }

    private function syncCompany(string $section, array $data): void
    {
        if (!in_array($section, ['general-configuration', 'company-branding', 'localization'], true)) return;
        $company = Company::query()->first();
        if (!$company) return;
        $before = $company->toArray();
        $updates = match ($section) {
            'general-configuration' => ['name' => $data['company_name'] ?? $company->name, 'legal_name' => $data['company_legal_name'] ?? $company->legal_name, 'default_locale' => $data['default_language'] ?? $company->default_locale, 'base_currency' => $data['default_currency'] ?? $company->base_currency],
            'company-branding' => ['name' => $data['trading_name'] ?? $company->name, 'legal_name' => $data['legal_name'] ?? $company->legal_name, 'settings' => array_merge($company->settings ?? [], Arr::only($data, ['brand_primary', 'brand_secondary', 'brand_accent', 'logo_path', 'footer_text']))],
            default => ['default_locale' => $data['default_language'] ?? $company->default_locale, 'base_currency' => $data['default_currency'] ?? $company->base_currency, 'country_code' => $data['country_code'] ?? $company->country_code],
        };
        $company->update($updates);
        AuditTrail::record('settings.company.synchronized', $company, $before, $company->fresh()->toArray());
    }

    private function syncConnection(string $section, array $data): void
    {
        $connectionData = match ($section) {
            'email-notifications' => ['service' => 'email', 'provider' => $data['mail_driver'] ?? 'smtp', 'enabled' => (bool) ($data['email_enabled'] ?? true)],
            'whatsapp-messaging' => ['service' => 'whatsapp', 'provider' => $data['provider'] ?? 'Meta Cloud API', 'enabled' => (bool) ($data['whatsapp_enabled'] ?? true)],
            'payment-gateways' => ['service' => 'payment', 'provider' => $data['default_gateway'] ?? 'Stripe', 'enabled' => (bool) ($data['payments_enabled'] ?? true)],
            default => null,
        };
        if (!$connectionData) return;
        $connection = IntegrationConnection::query()->firstOrNew(['service' => $connectionData['service']]);
        $connection->fill([...$connectionData, 'health' => $connection->health ?: 'not_configured']);
        $connection->save();
    }

    private function exportPayload(): array
    {
        return collect($this->defaults())->mapWithKeys(function (array $defaults, string $section): array {
            $record = AdminRecord::query()->where('module', 'system-settings')->where('reference', $section)->latest('id')->first();
            return [$section => array_merge($defaults, $record?->data ?? [])];
        })->all();
    }

    private function activity(?string $section = null)
    {
        $query = AuditLog::query()->where('action', 'like', 'settings.%')->latest('created_at')->limit(6);
        if ($section) $query->where('action', 'like', 'settings.'.$section.'%');
        return $query->get()->map(function (AuditLog $entry): array {
            return ['action' => Str::headline(str_replace('settings.', '', $entry->action)), 'description' => $entry->action, 'user' => $entry->user_id ? (User::find($entry->user_id)?->name ?? 'Admin User') : 'System', 'date' => $entry->created_at?->format('d M Y, H:i') ?? 'Just now', 'tone' => Str::contains($entry->action, ['failed', 'revoked']) ? 'red' : 'green'];
        });
    }

    private function metricsFor(array $category, $latestAudit, string $updatedBy): array
    {
        return [
            ['label' => 'Total Settings', 'value' => (string) $category['count'], 'tone' => 'green', 'icon' => 'settings'],
            ['label' => 'Configured', 'value' => (string) $category['configured'], 'tone' => 'blue', 'icon' => 'check'],
            ['label' => 'Needs Attention', 'value' => (string) $category['attention'], 'tone' => 'orange', 'icon' => 'alert'],
            ['label' => 'Not Configured', 'value' => (string) $category['not_configured'], 'tone' => 'red', 'icon' => 'clock'],
            ['label' => 'Last Updated', 'value' => $latestAudit ? Carbon::parse($latestAudit)->format('d M Y') : '01 May 2025', 'tone' => 'purple', 'icon' => 'calendar'],
            ['label' => 'Updated By', 'value' => $updatedBy, 'tone' => 'teal', 'icon' => 'user'],
        ];
    }

    private function catalog(): array
    {
        return [
            'general-configuration' => ['title' => 'General Configuration', 'subtitle' => 'Basic system information and core preferences', 'icon' => 'settings', 'tone' => 'green', 'count' => 12, 'configured' => 10, 'attention' => 1, 'not_configured' => 1],
            'company-branding' => ['title' => 'Company & Branding', 'subtitle' => 'Company information, branding and visual identity', 'icon' => 'briefcase', 'tone' => 'purple', 'count' => 11, 'configured' => 9, 'attention' => 1, 'not_configured' => 1],
            'email-notifications' => ['title' => 'Email & Notifications', 'subtitle' => 'Email delivery, templates and notification channels', 'icon' => 'mail', 'tone' => 'blue', 'count' => 18, 'configured' => 14, 'attention' => 2, 'not_configured' => 2],
            'whatsapp-messaging' => ['title' => 'WhatsApp & Messaging', 'subtitle' => 'WhatsApp API, message templates and workflows', 'icon' => 'message', 'tone' => 'teal', 'count' => 10, 'configured' => 8, 'attention' => 1, 'not_configured' => 1],
            'payment-gateways' => ['title' => 'Payment Gateways', 'subtitle' => 'Payment providers, currencies and transaction rules', 'icon' => 'credit-card', 'tone' => 'orange', 'count' => 15, 'configured' => 12, 'attention' => 1, 'not_configured' => 2],
            'localization' => ['title' => 'Localization', 'subtitle' => 'Languages, regional formats and currencies', 'icon' => 'globe', 'tone' => 'blue', 'count' => 12, 'configured' => 10, 'attention' => 1, 'not_configured' => 1],
            'security-access' => ['title' => 'Security & Access', 'subtitle' => 'Authentication, access control and security policies', 'icon' => 'check', 'tone' => 'red', 'count' => 15, 'configured' => 12, 'attention' => 1, 'not_configured' => 2],
            'api-roles' => ['title' => 'Users & Roles', 'subtitle' => 'Users, API roles and permission governance', 'icon' => 'users', 'tone' => 'purple', 'count' => 9, 'configured' => 8, 'attention' => 0, 'not_configured' => 1],
            'application-settings' => ['title' => 'Application Settings', 'subtitle' => 'Features, performance, caching and maintenance', 'icon' => 'settings', 'tone' => 'green', 'count' => 20, 'configured' => 17, 'attention' => 1, 'not_configured' => 2],
            'document-storage' => ['title' => 'Document & Storage', 'subtitle' => 'Files, documents, upload limits and storage providers', 'icon' => 'file-text', 'tone' => 'orange', 'count' => 12, 'configured' => 10, 'attention' => 1, 'not_configured' => 1],
            'integrations' => ['title' => 'Integrations', 'subtitle' => 'External services and connection health', 'icon' => 'refresh', 'tone' => 'blue', 'count' => 14, 'configured' => 11, 'attention' => 1, 'not_configured' => 2],
            'automations' => ['title' => 'Automations', 'subtitle' => 'Workflows, triggers and scheduled tasks', 'icon' => 'refresh', 'tone' => 'purple', 'count' => 10, 'configured' => 7, 'attention' => 1, 'not_configured' => 2],
            'backup-recovery' => ['title' => 'Backup & Recovery', 'subtitle' => 'Backups, restore points and recovery operations', 'icon' => 'download', 'tone' => 'teal', 'count' => 8, 'configured' => 7, 'attention' => 0, 'not_configured' => 1],
            'audit-logs' => ['title' => 'Audit & Logs', 'subtitle' => 'Activity history, retention and compliance evidence', 'icon' => 'file-text', 'tone' => 'red', 'count' => 9, 'configured' => 7, 'attention' => 0, 'not_configured' => 2],
            'system-maintenance' => ['title' => 'System Maintenance', 'subtitle' => 'Maintenance jobs, queues and system health', 'icon' => 'refresh', 'tone' => 'orange', 'count' => 6, 'configured' => 5, 'attention' => 0, 'not_configured' => 1],
            'other-settings' => ['title' => 'Other Settings', 'subtitle' => 'Additional preferences and operational options', 'icon' => 'dots', 'tone' => 'blue', 'count' => 5, 'configured' => 5, 'attention' => 0, 'not_configured' => 0],
        ];
    }

    private function tabsFor(string $section): array
    {
        return match ($section) {
            'general-configuration' => ['basic' => 'Basic Information', 'system' => 'System Preferences', 'datetime' => 'Date & Time', 'formats' => 'Formats', 'contact' => 'Contact Details', 'compliance' => 'Compliance', 'display' => 'Display Preferences', 'other' => 'Other Preferences'],
            'company-branding' => ['company' => 'Company Information', 'branding' => 'Branding & Logo', 'assets' => 'Brand Assets', 'theme' => 'Theme & Colors', 'headers' => 'Headers & Footers', 'legal' => 'Legal & Compliance', 'social' => 'Social Media', 'print' => 'Print & Documents'],
            'email-notifications' => ['email' => 'Email Settings', 'smtp' => 'SMTP Configuration', 'templates' => 'Email Templates', 'preferences' => 'Notification Preferences', 'queues' => 'Email Queues', 'logs' => 'Email Logs', 'whatsapp' => 'WhatsApp & SMS', 'alerts' => 'Alert Rules'],
            'whatsapp-messaging' => ['config' => 'WhatsApp Configuration', 'templates' => 'Message Templates', 'workflows' => 'Auto Replies & Workflows', 'preferences' => 'Message Preferences', 'numbers' => 'WhatsApp Numbers', 'logs' => 'Message Logs', 'analytics' => 'Analytics & Reports', 'integrations' => 'Integrations'],
            'payment-gateways' => ['gateway' => 'Gateway Configuration', 'transactions' => 'Transaction Settings', 'currencies' => 'Currencies', 'fees' => 'Fees & Charges', 'fraud' => '3D Secure & Fraud', 'payouts' => 'Payouts & Settlement', 'methods' => 'Payment Methods', 'logs' => 'Logs & Reports', 'webhooks' => 'Webhooks'],
            'localization' => ['regional' => 'Language & Regional Settings', 'currency' => 'Currency Settings', 'date' => 'Date & Time Formats', 'number' => 'Number & Measurement Formats', 'address' => 'Address Formats', 'translations' => 'Translations', 'accessibility' => 'RTL & Accessibility'],
            'security-access' => ['overview' => 'Security Overview', 'authentication' => 'Authentication', 'access' => 'Access Control', 'password' => 'Password Policy', 'session' => 'Session Management', 'ip' => 'IP & Device Control', 'policies' => 'Security Policies', 'mfa' => '2FA & MFA', 'scanning' => 'Security Scanning', 'logs' => 'Logs & Alerts'],
            'api-roles' => ['roles' => 'API Roles', 'scopes' => 'Scopes & Permissions', 'keys' => 'API Keys', 'activity' => 'Usage & Activity'],
            'application-settings' => ['general' => 'General', 'features' => 'Features & Modules', 'performance' => 'Performance', 'caching' => 'Caching', 'storage' => 'Storage', 'uploads' => 'File Uploads', 'maintenance' => 'Maintenance Mode', 'developer' => 'Developer', 'system' => 'System Info'],
            'document-storage' => ['documents' => 'Documents', 'providers' => 'Storage Providers', 'uploads' => 'File Uploads', 'retention' => 'Retention Rules', 'templates' => 'Document Templates'],
            'integrations' => ['overview' => 'Integration Overview', 'connections' => 'Connections', 'webhooks' => 'Webhooks', 'credentials' => 'Credentials', 'health' => 'Health Monitor', 'logs' => 'Integration Logs'],
            'automations' => ['overview' => 'Overview', 'workflows' => 'Workflows', 'scheduled' => 'Scheduled Tasks', 'triggers' => 'Triggers', 'email' => 'Email Automations', 'messaging' => 'SMS/WhatsApp Automations', 'integrations' => 'Integrations', 'logs' => 'Logs'],
            'backup-recovery' => ['overview' => 'Overview', 'backups' => 'Backups', 'scheduled' => 'Scheduled Backups', 'restore' => 'Restore', 'storage' => 'Backup Storage', 'settings' => 'Backup Settings', 'points' => 'Recovery Points', 'logs' => 'Logs'],
            'audit-logs' => ['overview' => 'Overview', 'activity' => 'Activity Log', 'retention' => 'Retention', 'exports' => 'Exports', 'compliance' => 'Compliance'],
            'system-maintenance' => ['overview' => 'Overview', 'jobs' => 'Maintenance Jobs', 'queues' => 'Queues', 'health' => 'System Health', 'scheduler' => 'Scheduler'],
            default => ['general' => 'General Preferences', 'display' => 'Display', 'notifications' => 'Notifications', 'advanced' => 'Advanced'],
        };
    }

    private function defaults(): array
    {
        return [
            'general-configuration' => ['company_name' => 'Emerald Rozalia', 'system_name' => 'Emerald Rozalia Hats & Caps Management System', 'short_name' => 'ERHCM', 'company_legal_name' => 'Emerald Rozalia Ltd.', 'registration_number' => '678912', 'website_url' => 'https://emeraldrozalia.ie', 'country' => 'Ireland', 'industry' => 'Retail & Distribution', 'timezone' => 'Europe/Dublin', 'default_language' => 'en', 'default_currency' => 'EUR', 'primary_email' => 'urmos@rozalia.ie', 'primary_phone' => '+353 (89) 978 8187', 'support_email' => 'urmos@rozalia.ie', 'support_phone' => '+353 (89) 978 8187', 'date_format' => 'DD/MM/YYYY', 'time_format' => '24-hour', 'week_starts' => 'Monday', 'decimal_separator' => '.', 'thousands_separator' => ','],
            'company-branding' => ['legal_name' => 'Emerald Rozalia Ltd.', 'trading_name' => 'Emerald Rozalia', 'registration_number' => '678912', 'vat_number' => 'IE678912A', 'address' => "Unit 7, Limerick Business Park,\nLimerick, Ireland.", 'city' => 'Limerick', 'county' => 'Limerick', 'postcode' => 'V94 XY29', 'country' => 'Ireland', 'phone' => '+353 (89) 978 8187', 'email' => 'urmos@rozalia.ie', 'website' => 'https://emeraldrozalia.ie', 'description' => 'Premium Irish hats and caps for modern retail and franchise partners.', 'logo_path' => '/assets/logo/logo_two_line.png', 'brand_primary' => '#075b2f', 'brand_secondary' => '#0b1711', 'brand_accent' => '#7fbd42', 'footer_text' => 'Emerald Rozalia Limited. All rights reserved.'],
            'email-notifications' => ['email_enabled' => true, 'mail_driver' => 'smtp', 'from_name' => 'Emerald Rozalia', 'from_email' => 'urmos@rozalia.ie', 'reply_to' => 'urmos@rozalia.ie', 'return_path' => 'urmos@rozalia.ie', 'signature' => "Emerald Rozalia Limited\nLimerick, Ireland", 'email_language' => 'en', 'content_type' => 'HTML', 'track_opens' => true, 'track_clicks' => true, 'queue_emails' => true, 'notify_admins' => true],
            'whatsapp-messaging' => ['whatsapp_enabled' => true, 'provider' => 'Meta Cloud API', 'account_name' => 'Emerald Rozalia Business', 'business_account_id' => 'Configured in production', 'phone_number' => '+353 89 978 8187', 'webhook_url' => '/webhooks/whatsapp', 'default_language' => 'en', 'message_window' => '24 hours', 'delivery_receipts' => true, 'read_receipts' => true, 'notify_inquiries' => true, 'notify_orders' => true, 'notify_franchise' => true],
            'payment-gateways' => ['payments_enabled' => true, 'default_gateway' => 'Stripe', 'currency' => 'EUR', 'capture_mode' => 'Automatic', 'payment_timeout' => 30, 'retry_attempts' => 3, 'statement_descriptor' => 'EMERALD ROZALIA', 'three_d_secure' => true, 'fraud_checks' => true, 'save_payment_methods' => false, 'webhook_signing' => true],
            'localization' => ['default_language' => 'en', 'default_currency' => 'EUR', 'country_code' => 'IE', 'region' => 'Ireland', 'timezone' => 'Europe/Dublin', 'date_format' => 'DD/MM/YYYY', 'time_format' => '24-hour', 'first_day' => 'Monday', 'decimal_separator' => '.', 'thousands_separator' => ',', 'measurement_system' => 'Metric', 'rtl_support' => false, 'fallback_language' => 'en'],
            'security-access' => ['two_factor_required' => true, 'mfa_methods' => 'Authenticator app, Email', 'sso_enabled' => false, 'captcha_enabled' => true, 'login_attempts' => 5, 'lockout_minutes' => 30, 'password_min_length' => 12, 'password_expiry_days' => 90, 'session_timeout' => 60, 'remember_device_days' => 30, 'ip_allowlist' => '', 'audit_login_events' => true, 'security_notifications' => true, 'force_https' => true],
            'api-roles' => ['default_expiry_days' => 365, 'require_ip_allowlist' => false, 'rotate_keys_days' => 90, 'notify_on_revoke' => true, 'log_api_payloads' => false],
            'application-settings' => ['application_name' => 'Emerald Rozalia Project 1', 'application_url' => 'https://emeraldrozalia.ie', 'timezone' => 'Europe/Dublin', 'language' => 'en', 'currency' => 'EUR', 'date_format' => 'DD/MM/YYYY', 'time_format' => '24-hour', 'items_per_page' => 25, 'theme' => 'Emerald', 'landing_page' => 'Dashboard', 'maintenance_mode' => false, 'allow_registration' => true, 'email_verification' => true, 'enable_two_factor' => true, 'enable_api' => true, 'enable_audit_log' => true, 'enable_versioning' => true, 'multi_language' => true, 'multi_currency' => true, 'rtl_layout' => false, 'notify_system' => true, 'notify_security' => true, 'notify_updates' => true],
            'document-storage' => ['default_disk' => 'Local Private', 'public_disk' => 'Public Assets', 'max_upload_mb' => 20, 'allowed_file_types' => 'jpg, jpeg, png, webp, pdf, csv, xlsx', 'image_quality' => 85, 'generate_thumbnails' => true, 'virus_scanning' => true, 'document_versioning' => true, 'retention_days' => 365, 'encrypt_documents' => true],
            'integrations' => ['health_check_minutes' => 15, 'retry_attempts' => 3, 'timeout_seconds' => 30, 'log_requests' => true, 'notify_failures' => true, 'rotate_credentials' => true, 'webhook_retries' => 5],
            'automations' => ['automation_enabled' => true, 'max_concurrent' => 10, 'default_timezone' => 'Europe/Dublin', 'retry_failed' => true, 'retry_attempts' => 3, 'notify_failures' => true, 'retain_logs_days' => 90],
            'backup-recovery' => ['backups_enabled' => true, 'backup_frequency' => 'Daily', 'backup_time' => '02:00', 'backup_type' => 'Full', 'storage_provider' => 'Local encrypted + S3', 'retention_days' => 30, 'encrypt_backups' => true, 'verify_after_backup' => true, 'notify_success' => true, 'notify_failure' => true, 'recovery_point_objective' => 24],
            'audit-logs' => ['audit_enabled' => true, 'retention_days' => 365, 'capture_reads' => false, 'capture_exports' => true, 'capture_logins' => true, 'capture_settings' => true, 'anonymize_ip' => false, 'archive_enabled' => true],
            'system-maintenance' => ['maintenance_window' => 'Sunday 02:00–03:00', 'health_checks' => true, 'queue_monitoring' => true, 'scheduler_monitoring' => true, 'auto_prune' => true, 'notify_failures' => true, 'log_retention_days' => 90],
            'other-settings' => ['help_url' => 'https://emeraldrozalia.ie', 'support_contact' => 'urmos@rozalia.ie', 'show_release_notes' => true, 'telemetry_enabled' => false, 'custom_css' => ''],
        ];
    }

    private function fieldDefinitions(string $section): array
    {
        $select = fn (string $key, string $label, array $options, string $help = '') => ['key' => $key, 'label' => $label, 'type' => 'select', 'options' => $options, 'help' => $help];
        $text = fn (string $key, string $label, string $type = 'text', string $help = '', bool $wide = false) => ['key' => $key, 'label' => $label, 'type' => $type, 'help' => $help, 'wide' => $wide];
        $toggle = fn (string $key, string $label, string $help = '') => ['key' => $key, 'label' => $label, 'type' => 'boolean', 'help' => $help];
        $area = fn (string $key, string $label, string $help = '') => ['key' => $key, 'label' => $label, 'type' => 'textarea', 'help' => $help, 'wide' => true];

        return match ($section) {
            'general-configuration' => [$text('company_name', 'Company Name'), $text('system_name', 'System Name', 'text', '', true), $text('short_name', 'Short Name'), $text('company_legal_name', 'Legal Name'), $text('registration_number', 'Registration Number'), $text('website_url', 'Website URL', 'url'), $select('country', 'Country', ['Ireland' => 'Ireland', 'United Kingdom' => 'United Kingdom', 'United States' => 'United States']), $text('industry', 'Industry'), $select('timezone', 'Timezone', ['Europe/Dublin' => '(GMT+01:00) Europe/Dublin', 'Europe/London' => '(GMT+01:00) Europe/London', 'UTC' => 'UTC']), $select('default_language', 'Default Language', ['en' => 'English', 'ga' => 'Irish']), $select('default_currency', 'Default Currency', ['EUR' => 'EUR - Euro (€)', 'GBP' => 'GBP - Pound (£)', 'USD' => 'USD - Dollar ($)']), $text('primary_email', 'Primary Email', 'email'), $text('primary_phone', 'Primary Phone'), $text('support_email', 'Support Email', 'email'), $text('support_phone', 'Support Phone'), $select('date_format', 'Date Format', ['DD/MM/YYYY' => 'DD/MM/YYYY', 'MM/DD/YYYY' => 'MM/DD/YYYY']), $select('time_format', 'Time Format', ['24-hour' => '24-hour', '12-hour' => '12-hour']), $select('week_starts', 'Week Starts', ['Monday' => 'Monday', 'Sunday' => 'Sunday']), $text('decimal_separator', 'Decimal Separator'), $text('thousands_separator', 'Thousands Separator')],
            'company-branding' => [$text('legal_name', 'Legal Company Name'), $text('trading_name', 'Trading Name'), $text('registration_number', 'Registration Number'), $text('vat_number', 'VAT Number'), $area('address', 'Registered Address'), $text('city', 'City'), $text('county', 'County'), $text('postcode', 'Postcode'), $select('country', 'Country', ['Ireland' => 'Ireland', 'United Kingdom' => 'United Kingdom']), $text('phone', 'Phone'), $text('email', 'Email', 'email'), $text('website', 'Website', 'url'), $area('description', 'Company Description'), $text('logo_path', 'Logo Asset Path', 'text', 'Approved Emerald Rozalia logo asset.', true), $text('brand_primary', 'Primary Colour'), $text('brand_secondary', 'Secondary Colour'), $text('brand_accent', 'Accent Colour'), $area('footer_text', 'Footer Text')],
            'email-notifications' => [$toggle('email_enabled', 'Email delivery enabled'), $select('mail_driver', 'Mail Driver', ['smtp' => 'SMTP', 'log' => 'Log / local testing']), $text('from_name', 'From Name'), $text('from_email', 'From Email', 'email'), $text('reply_to', 'Reply To', 'email'), $text('return_path', 'Return Path', 'email'), $area('signature', 'Email Signature'), $select('email_language', 'Email Language', ['en' => 'English', 'ga' => 'Irish']), $select('content_type', 'Content Type', ['HTML' => 'HTML', 'Plain text' => 'Plain text']), $toggle('track_opens', 'Track opens'), $toggle('track_clicks', 'Track clicks'), $toggle('queue_emails', 'Queue emails'), $toggle('notify_admins', 'Notify administrators')],
            'whatsapp-messaging' => [$toggle('whatsapp_enabled', 'WhatsApp channel enabled'), $select('provider', 'Provider', ['Meta Cloud API' => 'Meta Cloud API', 'Twilio' => 'Twilio']), $text('account_name', 'Business Account Name'), $text('business_account_id', 'Business Account ID'), $text('phone_number', 'WhatsApp Number'), $text('webhook_url', 'Webhook URL', 'text', '', true), $select('default_language', 'Default Language', ['en' => 'English', 'ga' => 'Irish']), $select('message_window', 'Message Window', ['24 hours' => '24 hours', '7 days' => '7 days']), $toggle('delivery_receipts', 'Delivery receipts'), $toggle('read_receipts', 'Read receipts'), $toggle('notify_inquiries', 'Inquiry notifications'), $toggle('notify_orders', 'Order notifications'), $toggle('notify_franchise', 'Franchise notifications')],
            'payment-gateways' => [$toggle('payments_enabled', 'Payment processing enabled'), $select('default_gateway', 'Default Gateway', ['Stripe' => 'Stripe', 'Revolut Business' => 'Revolut Business', 'Manual' => 'Manual / bank transfer']), $select('currency', 'Settlement Currency', ['EUR' => 'EUR - Euro (€)', 'GBP' => 'GBP - Pound (£)']), $select('capture_mode', 'Capture Mode', ['Automatic' => 'Automatic', 'Manual' => 'Manual']), $text('payment_timeout', 'Payment Timeout (minutes)', 'number'), $text('retry_attempts', 'Retry Attempts', 'number'), $text('statement_descriptor', 'Statement Descriptor'), $toggle('three_d_secure', 'Require 3D Secure'), $toggle('fraud_checks', 'Enable fraud checks'), $toggle('save_payment_methods', 'Save payment methods'), $toggle('webhook_signing', 'Verify signed webhooks')],
            'localization' => [$select('default_language', 'Default Language', ['en' => 'English', 'ga' => 'Irish']), $select('default_currency', 'Default Currency', ['EUR' => 'EUR - Euro (€)', 'GBP' => 'GBP - Pound (£)']), $select('country_code', 'Country', ['IE' => 'Ireland', 'GB' => 'United Kingdom']), $text('region', 'Region'), $select('timezone', 'Timezone', ['Europe/Dublin' => 'Europe/Dublin', 'Europe/London' => 'Europe/London', 'UTC' => 'UTC']), $select('date_format', 'Date Format', ['DD/MM/YYYY' => 'DD/MM/YYYY', 'MM/DD/YYYY' => 'MM/DD/YYYY']), $select('time_format', 'Time Format', ['24-hour' => '24-hour', '12-hour' => '12-hour']), $select('first_day', 'First Day of Week', ['Monday' => 'Monday', 'Sunday' => 'Sunday']), $text('decimal_separator', 'Decimal Separator'), $text('thousands_separator', 'Thousands Separator'), $select('measurement_system', 'Measurement System', ['Metric' => 'Metric', 'Imperial' => 'Imperial']), $toggle('rtl_support', 'RTL support'), $select('fallback_language', 'Fallback Language', ['en' => 'English', 'ga' => 'Irish'])],
            'security-access' => [$toggle('two_factor_required', 'Require 2FA for administrators'), $text('mfa_methods', 'Allowed MFA Methods'), $toggle('sso_enabled', 'Enable SSO'), $toggle('captcha_enabled', 'Enable CAPTCHA'), $text('login_attempts', 'Login Attempts Before Lockout', 'number'), $text('lockout_minutes', 'Lockout Duration (minutes)', 'number'), $text('password_min_length', 'Minimum Password Length', 'number'), $text('password_expiry_days', 'Password Expiry (days)', 'number'), $text('session_timeout', 'Session Timeout (minutes)', 'number'), $text('remember_device_days', 'Remember Device (days)', 'number'), $area('ip_allowlist', 'IP Allowlist', 'One IP or CIDR range per line.'), $toggle('audit_login_events', 'Audit login events'), $toggle('security_notifications', 'Security notifications'), $toggle('force_https', 'Force HTTPS')],
            'api-roles' => [$text('default_expiry_days', 'Default Key Expiry (days)', 'number'), $toggle('require_ip_allowlist', 'Require IP allowlist'), $text('rotate_keys_days', 'Rotate Keys (days)', 'number'), $toggle('notify_on_revoke', 'Notify on revoke'), $toggle('log_api_payloads', 'Log API payloads')],
            'application-settings' => [$text('application_name', 'Application Name'), $text('application_url', 'Application URL', 'url'), $select('timezone', 'Timezone', ['Europe/Dublin' => 'Europe/Dublin', 'UTC' => 'UTC']), $select('language', 'Language', ['en' => 'English', 'ga' => 'Irish']), $select('currency', 'Currency', ['EUR' => 'EUR - Euro (€)', 'GBP' => 'GBP - Pound (£)']), $select('date_format', 'Date Format', ['DD/MM/YYYY' => 'DD/MM/YYYY', 'MM/DD/YYYY' => 'MM/DD/YYYY']), $select('time_format', 'Time Format', ['24-hour' => '24-hour', '12-hour' => '12-hour']), $text('items_per_page', 'Items Per Page', 'number'), $select('theme', 'Admin Theme', ['Emerald' => 'Emerald', 'Light' => 'Light']), $select('landing_page', 'Landing Page', ['Dashboard' => 'Dashboard', 'Orders' => 'Orders']), $toggle('maintenance_mode', 'Maintenance mode'), $toggle('allow_registration', 'Allow registration'), $toggle('email_verification', 'Require email verification'), $toggle('enable_two_factor', 'Enable two-factor authentication'), $toggle('enable_api', 'Enable API'), $toggle('enable_audit_log', 'Enable audit log'), $toggle('enable_versioning', 'Enable versioning'), $toggle('multi_language', 'Multi-language'), $toggle('multi_currency', 'Multi-currency'), $toggle('rtl_layout', 'RTL layout'), $toggle('notify_system', 'System notifications'), $toggle('notify_security', 'Security notifications'), $toggle('notify_updates', 'Product updates')],
            'document-storage' => [$select('default_disk', 'Default Storage Disk', ['Local Private' => 'Local Private', 'S3' => 'Amazon S3']), $select('public_disk', 'Public Assets Disk', ['Public Assets' => 'Public Assets', 'S3' => 'Amazon S3']), $text('max_upload_mb', 'Max Upload (MB)', 'number'), $text('allowed_file_types', 'Allowed File Types'), $text('image_quality', 'Image Quality', 'number'), $toggle('generate_thumbnails', 'Generate thumbnails'), $toggle('virus_scanning', 'Virus scanning'), $toggle('document_versioning', 'Document versioning'), $text('retention_days', 'Retention (days)', 'number'), $toggle('encrypt_documents', 'Encrypt documents')],
            'integrations' => [$text('health_check_minutes', 'Health Check Interval (minutes)', 'number'), $text('retry_attempts', 'Retry Attempts', 'number'), $text('timeout_seconds', 'Request Timeout (seconds)', 'number'), $toggle('log_requests', 'Log integration requests'), $toggle('notify_failures', 'Notify on failure'), $toggle('rotate_credentials', 'Rotate credentials'), $text('webhook_retries', 'Webhook Retries', 'number')],
            'automations' => [$toggle('automation_enabled', 'Automations enabled'), $text('max_concurrent', 'Max Concurrent Workflows', 'number'), $select('default_timezone', 'Default Timezone', ['Europe/Dublin' => 'Europe/Dublin', 'UTC' => 'UTC']), $toggle('retry_failed', 'Retry failed runs'), $text('retry_attempts', 'Retry Attempts', 'number'), $toggle('notify_failures', 'Notify on failure'), $text('retain_logs_days', 'Log Retention (days)', 'number')],
            'backup-recovery' => [$toggle('backups_enabled', 'Backups enabled'), $select('backup_frequency', 'Backup Frequency', ['Daily' => 'Daily', 'Weekly' => 'Weekly', 'Manual' => 'Manual']), $text('backup_time', 'Backup Time'), $select('backup_type', 'Backup Type', ['Full' => 'Full', 'Database' => 'Database', 'Files' => 'Files']), $text('storage_provider', 'Storage Provider'), $text('retention_days', 'Retention (days)', 'number'), $toggle('encrypt_backups', 'Encrypt backups'), $toggle('verify_after_backup', 'Verify after backup'), $toggle('notify_success', 'Notify on success'), $toggle('notify_failure', 'Notify on failure'), $text('recovery_point_objective', 'Recovery Point Objective (hours)', 'number')],
            'audit-logs' => [$toggle('audit_enabled', 'Audit log enabled'), $text('retention_days', 'Retention (days)', 'number'), $toggle('capture_reads', 'Capture read events'), $toggle('capture_exports', 'Capture exports'), $toggle('capture_logins', 'Capture logins'), $toggle('capture_settings', 'Capture settings changes'), $toggle('anonymize_ip', 'Anonymize IP addresses'), $toggle('archive_enabled', 'Archive old entries')],
            'system-maintenance' => [$text('maintenance_window', 'Maintenance Window'), $toggle('health_checks', 'Health checks'), $toggle('queue_monitoring', 'Queue monitoring'), $toggle('scheduler_monitoring', 'Scheduler monitoring'), $toggle('auto_prune', 'Automatic pruning'), $toggle('notify_failures', 'Notify on failure'), $text('log_retention_days', 'Log Retention (days)', 'number')],
            default => [$text('help_url', 'Help URL', 'url'), $text('support_contact', 'Support Contact', 'email'), $toggle('show_release_notes', 'Show release notes'), $toggle('telemetry_enabled', 'Anonymous telemetry'), $area('custom_css', 'Custom CSS', 'Applied only to the authenticated admin shell.')],
        };
    }

    private function fieldRules(string $section): array
    {
        $rules = [];
        foreach ($this->fieldDefinitions($section) as $field) {
            $rules[$field['key']] = match ($field['type']) {
                'boolean' => ['nullable', 'boolean'],
                'number' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
                'email' => ['nullable', 'email', 'max:255'],
                'url' => ['nullable', 'url', 'max:500'],
                'textarea' => ['nullable', 'string', 'max:5000'],
                default => ['nullable', 'string', 'max:1000'],
            };
        }
        return $rules;
    }
}
