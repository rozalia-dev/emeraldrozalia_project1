@extends('layouts.admin')

@section('title', $isOverview ? 'Settings' : $category['title'])

@push('styles')
    <link rel="stylesheet" href="/css/settings-reference.css?v=20260910-1">
@endpush

@php
    $settingsUrl = fn (?string $slug = null, array $query = []) => $slug ? route('admin.settings.page', ['section' => $slug] + $query) : route('admin.settings.overview', $query);
    $value = fn (string $key, mixed $fallback = '') => old($key, data_get($values, $key, $fallback));
    $prettyBytes = function ($bytes): string {
        $bytes = (float) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 1).' KB';
        return number_format($bytes).' B';
    };
    $connectionFor = fn (string $service) => $connections->firstWhere('service', $service);
    $statusLabel = fn ($status) => str_replace('-', ' ', ucfirst((string) $status));
@endphp

@section('content')
<div class="settings-page" data-settings-root>
    <div class="settings-breadcrumb"><a href="{{route('admin.dashboard')}}">Project 1 Control Panel</a><span>›</span><a href="{{$settingsUrl()}}">Settings</a>@if(!$isOverview)<span>›</span><strong>{{$category['title']}}</strong>@endif</div>

    <header class="settings-heading">
        <div class="settings-heading-copy">
            <span class="settings-heading-icon @if(!$isOverview)settings-tone-{{$category['tone']}}@endif"><x-icon name="{{$isOverview ? 'settings' : $category['icon']}}" size="24" /></span>
            <div><h1>{{$isOverview ? 'Settings' : $category['title']}}</h1><p>{{$isOverview ? 'Manage all system configuration and preferences' : $category['subtitle']}}</p></div>
        </div>
        <div class="settings-heading-actions">
            @if($isOverview)
                <form method="POST" action="{{route('admin.settings.action','import-settings')}}" enctype="multipart/form-data" data-settings-import-form>
                    @csrf
                    <label class="settings-button settings-button--soft"><x-icon name="upload" size="14" /> Import Settings<input type="file" name="file" accept="application/json,.json" data-settings-import></label>
                </form>
                <form method="POST" action="{{route('admin.settings.action','export-settings')}}">@csrf<button class="settings-button settings-button--soft" type="submit"><x-icon name="download" size="14" /> Export Settings</button></form>
                <a class="settings-button settings-button--primary" href="{{$settingsUrl('general-configuration')}}"><x-icon name="check" size="14" /> Save Changes</a>
            @else
                <a class="settings-button settings-button--soft" href="{{$settingsUrl()}}"><x-icon name="arrow-left" size="14" /> All Settings</a>
                <button class="settings-button settings-button--primary" type="submit" form="settings-form"><x-icon name="check" size="14" /> Save Changes</button>
            @endif
        </div>
    </header>

    @include('admin.settings.partials.metrics')

    @if($isOverview)
        <div class="settings-overview-toolbar">
            <form method="GET" action="{{$settingsUrl()}}" class="settings-filter-form" data-settings-search-form>
                <label class="settings-search"><x-icon name="search" size="15" /><input type="search" name="q" value="{{$search}}" placeholder="Search settings..." data-settings-search><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></label>
                <label class="settings-select"><span>Status</span><select name="status" onchange="this.form.submit()"><option value="">All statuses</option><option value="configured" @selected($status === 'configured')>Configured</option><option value="attention" @selected($status === 'attention')>Needs attention</option><option value="not-configured" @selected($status === 'not-configured')>Not configured</option></select></label>
            </form>
            <span class="settings-toolbar-note"><x-icon name="check" size="14" /> One Project 1 cPanel · PostgreSQL backed</span>
        </div>
        <div class="settings-overview-grid">
            <section class="settings-card settings-category-card">
                <div class="settings-card-heading"><div><h2>Settings Categories</h2><p>Configure every Project 1 module from one place.</p></div><span class="settings-heading-count">{{count($categories)}} areas</span></div>
                <div class="settings-category-grid" data-settings-category-list>
                    @forelse($categories as $item)
                        <a class="settings-category settings-category--{{$item['tone']}}" href="{{$settingsUrl($item['slug'])}}" data-settings-category data-settings-text="{{strtolower($item['title'].' '.$item['subtitle'])}}">
                            <span class="settings-category-icon"><x-icon name="{{$item['icon']}}" size="18" /></span>
                            <span class="settings-category-copy"><strong>{{$item['title']}}</strong><small>{{$item['subtitle']}}</small><b>{{$item['configured']}} / {{$item['count']}} configured</b></span>
                            <span class="settings-category-arrow"><x-icon name="chevron-right" size="15" /></span>
                        </a>
                    @empty
                        <div class="settings-empty">No settings categories match this filter.</div>
                    @endforelse
                </div>
            </section>
            <aside class="settings-overview-rail">
                <section class="settings-card settings-health-card">
                    <div class="settings-card-heading"><div><h2>System Health</h2><p>Configuration readiness</p></div><span class="settings-status-pill settings-status-pill--healthy"><i></i> Healthy</span></div>
                    <div class="settings-health-score"><div class="settings-donut settings-donut--health"><span><strong>82%</strong><small>Ready</small></span></div><div class="settings-health-lines"><span><i class="settings-dot settings-dot--green"></i>Configured <b>152</b></span><span><i class="settings-dot settings-dot--orange"></i>Attention <b>12</b></span><span><i class="settings-dot settings-dot--red"></i>Missing <b>22</b></span></div></div>
                    <div class="settings-mini-progress"><span><b>Configuration readiness</b><strong>82%</strong></span><i><em style="width:82%"></em></i></div>
                </section>
                <section class="settings-card">
                    <div class="settings-card-heading"><div><h2>Recent Activity</h2><p>Latest settings changes</p></div><x-icon name="clock" size="17" /></div>
                    <div class="settings-activity-list">
                        @forelse($recentActivity as $entry)<div class="settings-activity"><i class="settings-activity-mark settings-activity-mark--{{$entry['tone']}}"></i><span><strong>{{$entry['action']}}</strong><small>{{$entry['user']}} · {{$entry['date']}}</small></span></div>@empty<div class="settings-empty settings-empty--small">No settings changes yet.</div>@endforelse
                    </div>
                    <a class="settings-card-link" href="{{$settingsUrl('audit-logs')}}">View audit log <x-icon name="arrow-right" size="13" /></a>
                </section>
                <section class="settings-card">
                    <div class="settings-card-heading"><div><h2>Quick Actions</h2><p>Common administration tasks</p></div><x-icon name="refresh" size="17" /></div>
                    <div class="settings-quick-actions">
                        <form method="POST" action="{{route('admin.settings.action','clear-cache')}}">@csrf<button type="submit"><x-icon name="refresh" size="14" /> Clear application cache</button></form>
                        <form method="POST" action="{{route('admin.settings.action','run-backup')}}">@csrf<button type="submit"><x-icon name="download" size="14" /> Create settings backup</button></form>
                        <a href="{{$settingsUrl('system-maintenance')}}"><x-icon name="check" size="14" /> Run health checks</a>
                    </div>
                </section>
            </aside>
        </div>
        <div class="settings-bottom-grid">
            <section class="settings-card settings-summary-card"><div class="settings-card-heading"><div><h2>Configuration Summary</h2><p>Coverage across all settings areas</p></div><span class="settings-heading-count">186 total</span></div><div class="settings-summary-bars">@foreach(array_slice(array_values($catalog),0,8) as $item)<div><span><b>{{$item['title']}}</b><small>{{$item['configured']}} / {{$item['count']}}</small></span><i><em class="settings-bar--{{$item['tone']}}" style="width:{{round(($item['configured'] / max(1,$item['count'])) * 100)}}%"></em></i></div>@endforeach</div></section>
            <section class="settings-card"><div class="settings-card-heading"><div><h2>Settings Health Trend</h2><p>Last 30 days</p></div><span class="settings-trend-up">↗ 8.4%</span></div><div class="settings-sparkline"><svg viewBox="0 0 500 130" role="img" aria-label="Settings health trend"><path class="settings-sparkline-fill" d="M0 110 C45 105 55 88 95 94 S130 79 165 84 S200 64 235 76 S270 47 305 59 S340 40 370 47 S414 26 452 34 S470 17 500 13 V130 H0Z"/><path class="settings-sparkline-line" d="M0 110 C45 105 55 88 95 94 S130 79 165 84 S200 64 235 76 S270 47 305 59 S340 40 370 47 S414 26 452 34 S470 17 500 13"/></svg><div><span>01 May</span><span>Today</span></div></div></section>
        </div>
    @else
        <nav class="settings-tabs" aria-label="Settings sections">
            @foreach($tabs as $tabKey => $tabLabel)
                <a class="{{$activeTab === $tabKey ? 'active' : ''}}" href="{{$settingsUrl($section, ['tab' => $tabKey])}}">{{$tabLabel}}</a>
            @endforeach
        </nav>
        <div class="settings-detail-layout">
            <section class="settings-detail-main">
                <form id="settings-form" method="POST" action="{{route('admin.settings.save', $section)}}" data-settings-form>
                    @csrf
                    <input type="hidden" name="tab" value="{{$activeTab}}">
                    <div class="settings-card settings-form-card">
                        <div class="settings-card-heading"><div><h2>{{$tabs[$activeTab] ?? $category['title']}}</h2><p>Changes are saved to the tenant configuration record and included in the audit trail.</p></div><span class="settings-saved-state"><i></i> Server persisted</span></div>
                        <div class="settings-form-grid">
                            @foreach($fields as $field)
                                @php $fieldValue = $value($field['key']); @endphp
                                @if($field['type'] === 'boolean')
                                    <label class="settings-toggle-field"><span><strong>{{$field['label']}}</strong>@if($field['help'])<small>{{$field['help']}}</small>@endif</span><input type="hidden" name="{{$field['key']}}" value="0"><input type="checkbox" name="{{$field['key']}}" value="1" @checked((bool) $fieldValue)><i aria-hidden="true"></i></label>
                                @else
                                    <label class="settings-field @if($field['wide'] ?? false)settings-field--wide @endif"><span>{{$field['label']}}</span>
                                        @if($field['type'] === 'select')
                                            <select name="{{$field['key']}}">@foreach($field['options'] as $optionValue => $optionLabel)<option value="{{$optionValue}}" @selected((string) $fieldValue === (string) $optionValue)>{{$optionLabel}}</option>@endforeach</select>
                                        @elseif($field['type'] === 'textarea')
                                            <textarea name="{{$field['key']}}" rows="3">{{is_scalar($fieldValue) ? $fieldValue : ''}}</textarea>
                                        @else
                                            <input type="{{$field['type']}}" name="{{$field['key']}}" value="{{is_scalar($fieldValue) ? $fieldValue : ''}}" @if($field['type'] === 'number') min="0" step="1" @endif>
                                        @endif
                                        @if($field['help'])<small>{{$field['help']}}</small>@endif
                                    </label>
                                @endif
                            @endforeach
                        </div>
                        <div class="settings-form-footer"><span><x-icon name="check" size="14" /> UUID-keyed record · changes are auditable</span><button class="settings-button settings-button--primary" type="submit"><x-icon name="check" size="14" /> Save {{$category['title']}}</button></div>
                    </div>
                </form>

                @if($section === 'api-roles')
                    <section class="settings-card settings-table-card"><div class="settings-card-heading"><div><h2>API Roles &amp; Access</h2><p>Issue scoped integration access without exposing secrets in the UI.</p></div><span class="settings-heading-count">{{count($apiRoles)}} roles</span></div>
                        <div class="settings-inline-form"><form method="POST" action="{{route('admin.settings.api-roles.store')}}">@csrf<div><input required name="name" placeholder="Role name"><input name="description" placeholder="Purpose / description"><input name="scopes" placeholder="Scopes, comma separated"></div><div><select name="environment"><option value="production">Production</option><option value="staging">Staging</option><option value="development">Development</option></select><label class="settings-inline-check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" checked> Active</label><button class="settings-button settings-button--primary" type="submit"><x-icon name="plus" size="14" /> Create Role</button></div></form></div>
                        <div class="settings-table-scroll"><table class="settings-table"><thead><tr><th>API role</th><th>Environment</th><th>Scopes</th><th>Calls</th><th>Status</th><th></th></tr></thead><tbody>@foreach($apiRoles as $role)<tr><td><strong>{{$role->title}}</strong><small>{{data_get($role->data,'description','Scoped API access')}}</small><code>{{data_get($role,'public_uuid',data_get($role,'reference','UUID pending'))}}</code></td><td>{{ucfirst(data_get($role->data,'environment','production'))}}</td><td><span class="settings-scope-list">@foreach(array_slice((array) data_get($role->data,'scopes',[]),0,2) as $scope)<em>{{$scope}}</em>@endforeach</span></td><td>{{number_format((int) data_get($role->data,'calls',0))}}</td><td><span class="settings-status-pill settings-status-pill--{{ $role->status === 'active' ? 'healthy' : 'danger' }}"><i></i> {{$statusLabel($role->status)}}</span></td><td><div class="settings-row-actions">@if($role->id)<form method="POST" action="{{route('admin.settings.api-roles.toggle',$role)}}">@csrf<button type="submit" title="Toggle role"><x-icon name="refresh" size="14" /></button></form><form method="POST" action="{{route('admin.settings.api-roles.clone',$role)}}">@csrf<button type="submit" title="Clone role"><x-icon name="copy" size="14" /></button></form>@else<span class="settings-muted">Preview</span>@endif</div></td></tr>@endforeach</tbody></table></div>
                    </section>
                @elseif($section === 'automations')
                    <section class="settings-card settings-table-card"><div class="settings-card-heading"><div><h2>Workflow Library</h2><p>Event-driven actions are recorded in the automation rules table.</p></div><span class="settings-heading-count">{{count($automations)}} workflows</span></div>
                        <div class="settings-inline-form"><form method="POST" action="{{route('admin.settings.automations.store')}}">@csrf<div><input required name="name" placeholder="Workflow name"><input required name="event" placeholder="Event e.g. order.created"><input name="actions" placeholder="Actions, comma separated"></div><div><label class="settings-inline-check"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" checked> Enabled</label><button class="settings-button settings-button--primary" type="submit"><x-icon name="plus" size="14" /> Add Workflow</button></div></form></div>
                        <div class="settings-table-scroll"><table class="settings-table"><thead><tr><th>Workflow</th><th>Trigger</th><th>Actions</th><th>Last updated</th><th>Status</th><th></th></tr></thead><tbody>@foreach($automations as $automation)<tr><td><strong>{{$automation->name}}</strong><small>UUID {{data_get($automation,'uuid','preview')}}</small></td><td><code>{{$automation->event}}</code></td><td>{{implode(', ', (array) $automation->actions) ?: 'No actions yet'}}</td><td>{{optional($automation->updated_at)->format('d M Y, H:i') ?: 'Today'}}</td><td><span class="settings-status-pill settings-status-pill--{{$automation->enabled ? 'healthy' : 'neutral'}}"><i></i> {{$automation->enabled ? 'Enabled' : 'Paused'}}</span></td><td>@if($automation->id)<form method="POST" action="{{route('admin.settings.automations.toggle',$automation)}}">@csrf<button class="settings-row-button" type="submit">{{$automation->enabled ? 'Pause' : 'Enable'}}</button></form>@endif</td></tr>@endforeach</tbody></table></div>
                    </section>
                @elseif($section === 'backup-recovery')
                    <section class="settings-card settings-table-card"><div class="settings-card-heading"><div><h2>Recent Backups</h2><p>Backup manifests are stored privately and every run is auditable.</p></div><form method="POST" action="{{route('admin.settings.backups.store')}}">@csrf<button class="settings-button settings-button--primary" type="submit"><x-icon name="download" size="14" /> Run Backup Now</button></form></div><div class="settings-table-scroll"><table class="settings-table"><thead><tr><th>Backup</th><th>Started</th><th>Location</th><th>Size</th><th>Status</th></tr></thead><tbody>@foreach($backups as $backup)<tr><td><strong>{{$backup->type}} backup</strong><small>UUID {{$backup->uuid ?? 'preview'}}</small></td><td>{{optional($backup->started_at)->format('d M Y, H:i') ?: 'Today'}}</td><td>{{\Illuminate\Support\Str::limit($backup->location ?? 'Pending', 34)}}</td><td>{{$prettyBytes($backup->size_bytes ?? 0)}}</td><td><span class="settings-status-pill settings-status-pill--{{$backup->status === 'completed' ? 'healthy' : 'danger'}}"><i></i> {{$statusLabel($backup->status)}}</span></td></tr>@endforeach</tbody></table></div></section>
                @elseif($section === 'integrations')
                    <section class="settings-card settings-table-card"><div class="settings-card-heading"><div><h2>Connection Health</h2><p>Existing integration records are shown with their current health status.</p></div><a class="settings-card-link" href="{{route('admin.integration-status')}}">Open integration status <x-icon name="arrow-right" size="13" /></a></div><div class="settings-connection-grid">@forelse($connections as $connection)<article><span class="settings-connection-icon"><x-icon name="refresh" size="16" /></span><div><strong>{{\Illuminate\Support\Str::headline($connection->service)}}</strong><small>{{$connection->provider ?: 'Provider not configured'}}</small></div><span class="settings-status-pill settings-status-pill--{{$connection->health === 'healthy' ? 'healthy' : ($connection->health === 'degraded' ? 'warning' : 'neutral')}}"><i></i> {{$statusLabel($connection->health)}}</span></article>@empty<div class="settings-empty">No integration connections have been registered.</div>@endforelse</div></section>
                @elseif($section === 'localization')
                    <section class="settings-card settings-table-card"><div class="settings-card-heading"><div><h2>Active Languages &amp; Currencies</h2><p>These records are shared by the storefront and Project 1 cPanel.</p></div><span class="settings-heading-count">{{count($languages)}} languages · {{count($currencies)}} currencies</span></div><div class="settings-split-tables"><div><h3>Languages</h3><table class="settings-table"><tbody>@forelse($languages as $language)<tr><td><strong>{{$language->native_name}}</strong><small>{{$language->locale}}</small></td><td><span class="settings-status-pill settings-status-pill--{{$language->active ? 'healthy' : 'neutral'}}"><i></i> {{$language->active ? 'Active' : 'Inactive'}}</span></td></tr>@empty<tr><td>No languages configured yet.</td></tr>@endforelse</tbody></table></div><div><h3>Currencies</h3><table class="settings-table"><tbody>@forelse($currencies as $currency)<tr><td><strong>{{$currency->symbol}} {{$currency->code}}</strong><small>{{$currency->name}}</small></td><td><span class="settings-status-pill settings-status-pill--{{$currency->active ? 'healthy' : 'neutral'}}"><i></i> {{$currency->active ? 'Active' : 'Inactive'}}</span></td></tr>@empty<tr><td>No currencies configured yet.</td></tr>@endforelse</tbody></table></div></div></section>
                @elseif($section === 'security-access')
                    <section class="settings-card settings-security-callout"><div class="settings-security-icon"><x-icon name="check" size="20" /></div><div><h2>Security posture</h2><p>Two-factor authentication, HTTPS enforcement and login auditing are enabled by the approved defaults. Use the save action above to persist changes.</p></div><form method="POST" action="{{route('admin.settings.action','rotate-api-keys')}}">@csrf<button class="settings-button settings-button--soft" type="submit">Rotate API keys</button></form></section>
                @elseif($section === 'application-settings')
                    <section class="settings-card settings-maintenance-card"><div><span class="settings-section-eyebrow">Maintenance Mode</span><h2>{{$value('maintenance_mode') ? 'Maintenance is active' : 'System is live'}}</h2><p>Use this control to make the storefront unavailable while the cPanel remains accessible to administrators.</p></div><form method="POST" action="{{route('admin.settings.action','maintenance-toggle')}}">@csrf<button class="settings-button {{$value('maintenance_mode') ? 'settings-button--danger' : 'settings-button--primary'}}" type="submit">{{$value('maintenance_mode') ? 'Disable maintenance' : 'Enable maintenance'}}</button></form></section>
                @elseif($section === 'email-notifications')
                    <section class="settings-card settings-channel-card"><div><span class="settings-section-eyebrow">Email Channel</span><h2>SMTP connection</h2><p>{{$connectionFor('email')?->provider ?: 'SMTP'}} · {{$connectionFor('email')?->health ? $statusLabel($connectionFor('email')->health) : 'Not tested'}}</p></div><form method="POST" action="{{route('admin.settings.action','test-email')}}">@csrf<button class="settings-button settings-button--soft" type="submit"><x-icon name="mail" size="14" /> Send test</button></form></section>
                @elseif($section === 'whatsapp-messaging')
                    <section class="settings-card settings-channel-card"><div><span class="settings-section-eyebrow">Messaging Channel</span><h2>WhatsApp Cloud API</h2><p>{{$connectionFor('whatsapp')?->provider ?: 'Meta Cloud API'}} · {{$connectionFor('whatsapp')?->health ? $statusLabel($connectionFor('whatsapp')->health) : 'Not tested'}}</p></div><form method="POST" action="{{route('admin.settings.action','test-whatsapp')}}">@csrf<button class="settings-button settings-button--soft" type="submit"><x-icon name="message" size="14" /> Test channel</button></form></section>
                @elseif($section === 'payment-gateways')
                    <section class="settings-card settings-channel-card"><div><span class="settings-section-eyebrow">Payment Channel</span><h2>{{$value('default_gateway','Stripe')}}</h2><p>{{$connectionFor('payment')?->provider ?: 'Stripe / Revolut'}} · {{$connectionFor('payment')?->health ? $statusLabel($connectionFor('payment')->health) : 'Not tested'}}</p></div><form method="POST" action="{{route('admin.settings.action','test-payment')}}">@csrf<button class="settings-button settings-button--soft" type="submit"><x-icon name="credit-card" size="14" /> Test gateway</button></form></section>
                @elseif($section === 'audit-logs')
                    <section class="settings-card settings-table-card"><div class="settings-card-heading"><div><h2>Recent Audit Events</h2><p>Every settings mutation is stored with before and after JSON snapshots.</p></div><span class="settings-heading-count">{{count($recentActivity)}} recent</span></div><div class="settings-activity-list settings-activity-list--wide">@forelse($recentActivity as $entry)<div class="settings-activity"><i class="settings-activity-mark settings-activity-mark--{{$entry['tone']}}"></i><span><strong>{{$entry['action']}}</strong><small>{{$entry['description']}} · {{$entry['user']}} · {{$entry['date']}}</small></span></div>@empty<div class="settings-empty">No audit events for this section yet.</div>@endforelse</div></section>
                @else
                    <section class="settings-card settings-information-card"><div class="settings-card-heading"><div><h2>Operational Summary</h2><p>This settings area is connected to the shared Project 1 control panel.</p></div><span class="settings-status-pill settings-status-pill--healthy"><i></i> Ready</span></div><div class="settings-info-columns"><div><strong>Configuration ownership</strong><p>System administrators manage this area. Values are scoped to the active company context where applicable.</p></div><div><strong>Change history</strong><p>Save actions create an immutable audit event with the authenticated user, request ID and changed values.</p></div><div><strong>Support</strong><p>{{$updatedBy}} · {{config('app.brand_contact.email') ?: 'urmos@rozalia.ie'}}</p></div></div></section>
                @endif
            </section>
            <aside class="settings-detail-rail">
                <section class="settings-card settings-side-card"><div class="settings-card-heading"><div><h2>Section Health</h2><p>{{$category['title']}} readiness</p></div><span class="settings-status-pill settings-status-pill--{{$category['attention'] ? 'warning' : 'healthy'}}"><i></i> {{$category['attention'] ? 'Review' : 'Healthy'}}</span></div><div class="settings-side-score"><div class="settings-donut settings-donut--{{$category['tone']}}"><span><strong>{{round(($category['configured'] / max(1, $category['count'])) * 100)}}%</strong><small>configured</small></span></div><div><b>{{$category['configured']}} of {{$category['count']}}</b><small>settings configured</small><i><em style="width:{{round(($category['configured'] / max(1, $category['count'])) * 100)}}%"></em></i></div></div></section>
                <section class="settings-card settings-side-card"><div class="settings-card-heading"><div><h2>Quick Actions</h2><p>Useful tools for this area</p></div><x-icon name="dots" size="17" /></div><div class="settings-quick-actions">@if($section === 'backup-recovery')<form method="POST" action="{{route('admin.settings.action','run-backup')}}">@csrf<button type="submit"><x-icon name="download" size="14" /> Run backup now</button></form>@endif@if($section === 'application-settings')<form method="POST" action="{{route('admin.settings.action','clear-cache')}}">@csrf<button type="submit"><x-icon name="refresh" size="14" /> Clear cache</button></form>@endif<a href="{{$settingsUrl('audit-logs')}}"><x-icon name="file-text" size="14" /> View audit trail</a><form method="POST" action="{{route('admin.settings.action','reset-section')}}">@csrf<input type="hidden" name="section" value="{{$section}}"><button type="submit" data-settings-confirm="Reset this section to its approved defaults?"><x-icon name="rotate-ccw" size="14" /> Reset defaults</button></form></div></section>
                <section class="settings-card settings-contact-card"><div class="settings-card-heading"><div><h2>Need help?</h2><p>Emerald Rozalia support</p></div><x-icon name="help" size="17" /></div><p>Contact the Project 1 administrator before changing production credentials or payment secrets.</p><div><span><x-icon name="mail" size="13" /> {{config('app.brand_contact.email') ?: 'urmos@rozalia.ie'}}</span><span><x-icon name="globe" size="13" /> {{config('app.brand_contact.website') ?: 'emeraldrozalia.ie'}}</span><span><x-icon name="globe" size="13" /> {{config('app.brand_contact.location') ?: 'Limerick, Ireland'}}</span></div></section>
            </aside>
        </div>
    @endif
</div>
@endsection

@push('scripts')
    <script src="/js/settings-reference.js?v=20260910-1" defer></script>
@endpush
