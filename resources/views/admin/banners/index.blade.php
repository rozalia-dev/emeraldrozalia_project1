@extends('layouts.admin')

@section('title', 'Banners / Sliders')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/banners-reference.css?v=20260912-1') }}">
@endpush

@php
    $selected = $selectedBanner;
    $number = static fn ($value): string => number_format((int) $value);
    $percent = static fn ($value): string => number_format((float) $value, 2).'%' ;
    $statusClass = static fn (string $key): string => 'status--'.preg_replace('/[^a-z0-9_-]+/i', '-', $key);
    $typeClass = static fn (string $key): string => 'type--'.preg_replace('/[^a-z0-9_-]+/i', '-', $key);
    $baseParams = [
        'q' => $search,
        'type' => $typeFilter,
        'status' => $statusFilter,
        'device' => $deviceFilter,
        'position' => $positionFilter,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'per_page' => request('per_page', 10),
    ];
    $url = static function (array $extra = []) use ($baseParams): string {
        return route('admin.banners.index', array_filter(array_merge($baseParams, $extra), static fn ($value): bool => $value !== null && $value !== ''));
    };
    $queryWithoutPage = array_filter(array_merge($baseParams, ['tab' => $tab]), static fn ($value): bool => $value !== null && $value !== '');
    $editorAction = $selectedId ? route('admin.banners.update', $selectedId) : route('admin.banners.store');
    $donutColors = ['slider' => '#1768c5', 'banner' => '#ff7800', 'popup' => '#ab1c61', 'footer' => '#0d7951', 'mobile_app' => '#4e4e54'];
    $donutSegments = [];
    $donutStart = 0;
    $donutTotal = max(1, array_sum($stats['type_counts']));
    foreach ($stats['type_counts'] as $typeKey => $typeCount) {
        $donutEnd = $donutStart + (((int) $typeCount / $donutTotal) * 100);
        $donutSegments[] = $donutColors[$typeKey].' '.$donutStart.'% '.$donutEnd.'%';
        $donutStart = $donutEnd;
    }
    $donutStyle = 'conic-gradient('.implode(', ', $donutSegments).')';
    $selectedDevices = $selected['devices'] ?? ['desktop', 'tablet', 'mobile'];
@endphp

@section('content')
<div class="banner-reference-page" data-banner-root data-open-modal="{{ $openModal ?? '' }}" data-current-tab="{{ $tab }}">
    <div class="banner-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Project 1 Control Panel (cPanel)</a>
        <x-icon name="chevron-right" size="12" />
        <span>Website &amp; Products</span>
        <x-icon name="chevron-right" size="12" />
        <strong>Banners / Sliders</strong>
    </div>

    <div class="banner-heading-row">
        <div class="banner-heading">
            <h1>Banners / Sliders</h1>
            <p>Create, manage and schedule banners and sliders for your website.</p>
        </div>
        <div class="banner-date-card">
            <span class="banner-date-icon"><x-icon name="calendar" size="19" /></span>
            <div>
                <small>Today</small>
                <strong>{{ now()->format('l, j M Y') }}</strong>
                <b>{{ now()->format('g:i A') }}</b>
            </div>
        </div>
    </div>

    <div class="banner-layout">
        <div class="banner-main">
            <section class="banner-kpis" aria-label="Banner summary metrics">
                @foreach($metrics as $metric)
                    <article class="banner-kpi">
                        <span class="banner-kpi-icon banner-kpi-icon--{{ $metric['tone'] }}"><x-icon name="{{ $metric['icon'] }}" size="19" /></span>
                        <div>
                            <small>{{ $metric['label'] }}</small>
                            <strong>{{ $number($metric['value']) }}</strong>
                            <em class="{{ !empty($metric['negative']) ? 'is-negative' : '' }}">↗ {{ $metric['trend'] }} <span>{{ $metric['trend_note'] }}</span></em>
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="banner-toolbar-card" id="banner-table">
                <div class="banner-tabs" role="tablist" aria-label="Banner types">
                    @foreach($tabs as $tabKey => $tabLabel)
                        <a class="{{ $tab === $tabKey ? 'active' : '' }}" href="{{ $url(['tab' => $tabKey]) }}" role="tab" aria-selected="{{ $tab === $tabKey ? 'true' : 'false' }}">{{ $tabLabel }}</a>
                    @endforeach
                </div>
                <div class="banner-actions-row">
                    <form class="banner-filter-form" method="get" action="{{ route('admin.banners.index') }}">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <input type="hidden" name="position" value="{{ $positionFilter }}">
                        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                        <input type="hidden" name="date_to" value="{{ $dateTo }}">
                        <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                        <label class="banner-search-field">
                            <span class="sr-only">Search banners</span>
                            <input type="search" name="q" value="{{ $search }}" placeholder="Search banners...">
                            <x-icon name="search" size="14" />
                        </label>
                        <button type="submit" class="banner-outline-button"><x-icon name="filter" size="14" /> Filters</button>
                        <label>
                            <span class="sr-only">Banner type</span>
                            <select name="type">
                                <option value="">All Types</option>
                                @foreach($types as $typeKey => $typeLabel)<option value="{{ $typeKey }}" @selected($typeFilter === $typeKey)>{{ $typeLabel }}</option>@endforeach
                            </select>
                        </label>
                        <label>
                            <span class="sr-only">Banner status</span>
                            <select name="status">
                                <option value="">All Statuses</option>
                                <option value="active" @selected($statusFilter === 'active')>Active</option>
                                <option value="scheduled" @selected($statusFilter === 'scheduled')>Scheduled</option>
                                <option value="expired" @selected($statusFilter === 'expired')>Expired</option>
                                @foreach($statuses as $statusKey)<option value="{{ $statusKey }}" @selected($statusFilter === $statusKey)>{{ $statusLabels[$statusKey] }}</option>@endforeach
                            </select>
                        </label>
                        <label>
                            <span class="sr-only">Device</span>
                            <select name="device">
                                <option value="">All Devices</option>
                                @foreach($devices as $device)<option value="{{ $device }}" @selected($deviceFilter === $device)>{{ ucfirst($device) }}</option>@endforeach
                            </select>
                        </label>
                        <a class="banner-reset-link" href="{{ route('admin.banners.index') }}"><x-icon name="refresh" size="13" /> Reset</a>
                    </form>
                    <button class="banner-primary-button" type="button" data-banner-modal-open="create"><x-icon name="plus" size="14" /> Add New Banner / Slider <x-icon name="chevron-down" size="12" /></button>
                </div>
            </section>

            <form id="bulk-banner-form" class="banner-bulk-form" method="post" action="{{ route('admin.banners.bulk') }}">
                @csrf
                <div class="banner-bulk-bar">
                    <span><b data-banner-selection-count>0</b> selected</span>
                    <label><span class="sr-only">Bulk action</span><select name="bulk_action"><option value="publish">Publish selected</option><option value="unpublish">Move to draft</option><option value="schedule">Schedule selected</option><option value="archive">Archive selected</option><option value="trash">Move to trash</option><option value="restore">Restore selected</option><option value="set_priority">Set priority</option></select></label>
                    <label><span class="sr-only">Priority</span><input type="number" name="priority" min="1" max="999" value="1" aria-label="Priority"></label>
                    <button type="submit" class="banner-small-button">Apply</button>
                    <button type="button" class="banner-import-label" data-banner-modal-open="import"><x-icon name="upload" size="13" /> Import CSV</button>
                    <a class="banner-small-button banner-small-button--ghost" href="{{ route('admin.banners.export', $queryWithoutPage) }}"><x-icon name="download" size="13" /> Export</a>
                </div>
            </form>

            <div class="banner-table-wrap">
                <table class="banner-table">
                    <thead>
                        <tr>
                            <th class="banner-check-col"><input type="checkbox" form="bulk-banner-form" data-banner-select-all aria-label="Select all banners"></th>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Position</th>
                            <th>Target / Link</th>
                            <th>Status</th>
                            <th>Schedule</th>
                            <th>Clicks</th>
                            <th>Priority</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $rowSelected = $selectedId && (string) $selectedId === (string) $row['id'];
                                $rowEditUrl = $row['id'] ? route('admin.banners.index', ['selected' => $row['id'], 'modal' => 'edit']) : '#';
                                $rowPreviewUrl = $row['id'] ? route('admin.banners.index', ['selected' => $row['id'], 'modal' => 'preview']) : '#banner-preview-modal';
                            @endphp
                            <tr class="{{ $rowSelected ? 'is-selected' : '' }}">
                                <td class="banner-check-col"><input type="checkbox" form="bulk-banner-form" name="ids[]" value="{{ $row['id'] }}" @disabled(!$row['id']) data-banner-row-check aria-label="Select {{ $row['title'] }}"></td>
                                <td>
                                    <a class="banner-thumbnail" href="{{ $rowPreviewUrl }}" @if(!$row['id']) data-banner-modal-open="preview" @endif>
                                        @if($row['image_url'])<img src="{{ $row['image_url'] }}" alt="{{ $row['alt_text'] }}" loading="lazy">@else<span><x-icon name="image" size="20" /></span>@endif
                                    </a>
                                </td>
                                <td class="banner-title-cell"><a href="{{ $rowEditUrl }}"><strong>{{ $row['title'] }}</strong><small>{{ $row['subtitle'] }}</small></a></td>
                                <td><span class="banner-type-pill {{ $typeClass($row['type']) }}">{{ $row['type_label'] }}</span></td>
                                <td>{{ $row['position'] }}</td>
                                <td class="banner-target-cell"><span>{{ $row['target_url'] }}</span><small>{{ ucfirst($row['target_type']) }} Link</small></td>
                                <td><span class="banner-status-pill {{ $statusClass($row['status_key']) }}">{{ $row['status'] }}</span></td>
                                <td class="banner-schedule-cell">{{ $row['schedule'] }}</td>
                                <td>{{ $number($row['clicks']) }}</td>
                                <td><span class="banner-priority">{{ $row['priority'] ?? '—' }}</span></td>
                                <td>
                                    @if($row['id'])
                                        <div class="banner-row-actions">
                                            <a href="{{ $rowEditUrl }}" aria-label="Edit {{ $row['title'] }}"><x-icon name="pencil" size="13" /></a>
                                            <form method="post" action="{{ route('admin.banners.duplicate', $row['id']) }}">@csrf<button type="submit" aria-label="Duplicate {{ $row['title'] }}"><x-icon name="copy" size="13" /></button></form>
                                            <details>
                                                <summary aria-label="More actions"><x-icon name="dots" size="14" /></summary>
                                                <div class="banner-row-menu">
                                                    <a href="{{ $rowPreviewUrl }}"><x-icon name="eye" size="12" /> Preview</a>
                                                    @if($row['status_key'] === 'trashed')
                                                        <form method="post" action="{{ route('admin.banners.action', [$row['id'], 'restore']) }}">@csrf<button type="submit"><x-icon name="refresh" size="12" /> Restore</button></form>
                                                    @elseif($row['status_key'] === 'active' || $row['status_key'] === 'scheduled')
                                                        <form method="post" action="{{ route('admin.banners.action', [$row['id'], 'unpublish']) }}">@csrf<button type="submit"><x-icon name="pause" size="12" /> Move to draft</button></form>
                                                    @else
                                                        <form method="post" action="{{ route('admin.banners.action', [$row['id'], 'publish']) }}">@csrf<button type="submit"><x-icon name="check" size="12" /> Publish</button></form>
                                                    @endif
                                                    <form method="post" action="{{ route('admin.banners.action', [$row['id'], 'archive']) }}">@csrf<button type="submit"><x-icon name="folder" size="12" /> Archive</button></form>
                                                    <form method="post" action="{{ route('admin.banners.action', [$row['id'], 'trash']) }}">@csrf<button type="submit" data-confirm="Move this banner to trash?"><x-icon name="trash" size="12" /> Move to trash</button></form>
                                                </div>
                                            </details>
                                        </div>
                                    @else
                                        <span class="banner-preview-only">Preview</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="banner-empty-state"><x-icon name="image" size="22" /><strong>No banners match these filters</strong><span>Try another filter or create a new banner / slider.</span><button type="button" class="banner-small-button" data-banner-modal-open="create">Add New Banner / Slider</button></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="banner-pagination-row">
                <span>Showing {{ $rows ? (($banners->firstItem() ?? 1).' to '.($banners->lastItem() ?? count($rows))) : 0 }} of {{ $preview ? $stats['total'] : $banners->total() }} banners / sliders</span>
                <div>{{ $banners->onEachSide(1)->links('pagination::simple-tailwind') }}</div>
                <label><span>Rows</span><select data-banner-per-page><option value="10" @selected($banners->perPage() === 10)>10 / page</option><option value="25" @selected($banners->perPage() === 25)>25 / page</option><option value="50" @selected($banners->perPage() === 50)>50 / page</option></select></label>
            </div>

            @if($selected)
                @if($selectedId)<form class="banner-settings-grid" id="banner-settings" method="post" action="{{ route('admin.banners.settings', $selectedId) }}">@csrf @method('PATCH')@else<div class="banner-settings-grid" id="banner-settings">@endif
                    <section class="banner-settings-card banner-display-card">
                        <div class="banner-card-heading"><h2>Banner Display Settings</h2><x-icon name="settings" size="15" /></div>
                        <div class="banner-field-grid">
                            <label><span>Default Animation</span><select name="animation" @disabled(!$selectedId)><option value="fade" @selected(($selected['animation'] ?? 'fade') === 'fade')>Fade</option><option value="slide" @selected(($selected['animation'] ?? '') === 'slide')>Slide</option><option value="zoom" @selected(($selected['animation'] ?? '') === 'zoom')>Zoom</option></select></label>
                            <label><span>Autoplay Speed (Seconds)</span><input type="number" name="autoplay_speed" min="1" max="3600" value="{{ $selected['autoplay_speed'] ?? 5 }}" @disabled(!$selectedId)></label>
                        </div>
                        <div class="banner-switch-list">
                            @foreach([['autoplay','Autoplay (for Sliders)'],['show_arrows','Show Arrows'],['show_dots','Show Dots'],['pause_on_hover','Pause on Hover']] as [$settingKey,$settingLabel])
                                <label><span>{{ $settingLabel }}</span><input type="checkbox" name="{{ $settingKey }}" value="1" @checked($selected[$settingKey] ?? false) @disabled(!$selectedId)><i></i></label>
                            @endforeach
                        </div>
                    </section>
                    <section class="banner-settings-card banner-device-card">
                        <div class="banner-card-heading"><h2>Device Visibility</h2><x-icon name="eye" size="15" /></div>
                        <div class="banner-switch-list banner-device-switches">
                            @foreach($devices as $device)
                                <label><span>{{ ucfirst($device) }}</span><input type="checkbox" name="devices[]" value="{{ $device }}" @checked(in_array($device, $selectedDevices, true)) @disabled(!$selectedId)><i></i></label>
                            @endforeach
                        </div>
                        <label class="banner-full-field"><span>Specific Pages (Optional)</span><input type="text" name="specific_pages" value="{{ $selected['specific_pages_csv'] ?? '' }}" placeholder="Select pages..." @disabled(!$selectedId)></label>
                        <small class="banner-help-text">Leave empty to show on all pages.</small>
                    </section>
                    <section class="banner-settings-card banner-seo-card">
                        <div class="banner-card-heading"><h2>SEO &amp; Accessibility <small>(For Selected Banner)</small></h2><x-icon name="shield" size="15" /></div>
                        <div class="banner-field-grid banner-field-grid--single">
                            <label><span>Alt Text</span><input value="{{ $selected['alt_text'] ?? '' }}" readonly></label>
                            <label><span>Title (Tooltip)</span><input value="{{ $selected['title_text'] ?? '' }}" readonly></label>
                            <label><span>ARIA Label (Accessibility)</span><input value="{{ $selected['aria_label'] ?? '' }}" readonly></label>
                        </div>
                        <div class="banner-selected-preview">
                            <div><span class="banner-selected-preview-label">Selected Banner Preview</span><a href="{{ $selected['image_url'] ?? '#' }}" target="_blank" rel="noreferrer">@if($selected['image_url'])<img src="{{ $selected['image_url'] }}" alt="{{ $selected['alt_text'] ?? '' }}">@else<span class="banner-large-placeholder"><x-icon name="image" size="24" /></span>@endif</a></div>
                            <button type="button" class="banner-small-button" data-banner-modal-open="edit"><x-icon name="image" size="12" /> Change Banner</button>
                        </div>
                    </section>
                    @if($selectedId)<div class="banner-settings-submit"><button type="submit" class="banner-primary-button"><x-icon name="check" size="13" /> Save Display Settings</button></div>@endif
                @if($selectedId)</form>@else</div>@endif
            @endif

            <section class="banner-lower-meta" id="banner-lower-meta">
                <div><span>Selected UUID</span><strong>{{ $selectedUuid ?: 'Preview data — create a banner to generate a UUID' }}</strong></div>
                <div><span>Last saved</span><strong>{{ $selected['updated_at'] ?? 'Reference preview' }}</strong></div>
                <button type="button" class="banner-outline-button" data-banner-modal-open="audit"><x-icon name="file-text" size="13" /> View Audit &amp; Revisions</button>
            </section>
        </div>

        <aside class="banner-rail">
            <section class="banner-rail-card banner-summary-card">
                <div class="banner-rail-title"><h2>BANNER SUMMARY</h2><x-icon name="grid" size="15" /></div>
                <dl class="banner-summary-list">
                    <div><dt>Total Banners / Sliders</dt><dd>{{ $number($stats['total']) }}</dd></div>
                    <div><dt>Active</dt><dd>{{ $number($stats['active']) }}</dd></div>
                    <div><dt>Scheduled</dt><dd>{{ $number($stats['scheduled']) }}</dd></div>
                    <div><dt>Expired</dt><dd>{{ $number($stats['expired']) }}</dd></div>
                    <div><dt>Total Clicks (All Time)</dt><dd>{{ $number($stats['clicks']) }}</dd></div>
                    <div><dt>Impressions (All Time)</dt><dd>{{ $number($stats['impressions']) }}</dd></div>
                    <div><dt>CTR (Click Through Rate)</dt><dd>{{ $percent($stats['ctr']) }}</dd></div>
                </dl>
            </section>
            <section class="banner-rail-card banner-types-card">
                <div class="banner-rail-title"><h2>BANNER TYPES</h2></div>
                <div class="banner-donut-row">
                    <div class="banner-donut" style="--banner-donut: {{ $donutStyle }}"><span>{{ $number($stats['total']) }}<small>Total</small></span></div>
                    <ul>
                        @foreach($stats['type_counts'] as $typeKey => $typeCount)
                            <li><i style="--type-color: {{ $donutColors[$typeKey] }}"></i><span>{{ $types[$typeKey] }}</span><b>{{ $number($typeCount) }}</b><small>({{ $stats['total'] ? number_format(($typeCount / $stats['total']) * 100, 1) : '0.0' }}%)</small></li>
                        @endforeach
                    </ul>
                </div>
            </section>
            <section class="banner-rail-card">
                <div class="banner-rail-title"><h2>QUICK ACTIONS</h2><x-icon name="plus" size="15" /></div>
                <div class="banner-quick-actions">
                    <button type="button" data-banner-modal-open="create"><x-icon name="plus" size="13" /> Add New Slider</button>
                    <button type="button" data-banner-modal-open="create"><x-icon name="image" size="13" /> Add New Banner</button>
                    <button type="button" data-banner-modal-open="create"><x-icon name="message" size="13" /> Add Popup Banner</button>
                    <a href="#banner-table"><x-icon name="upload" size="13" /> Bulk Update</a>
                    <a href="#banner-table"><x-icon name="arrow-up" size="13" /> Reorder Priority</a>
                    <a href="#banner-settings"><x-icon name="file-text" size="13" /> Banner Guidelines</a>
                    <a href="#banner-settings"><x-icon name="eye" size="13" /> View Placement Guide</a>
                </div>
            </section>
            <section class="banner-rail-card banner-uuid-card">
                <div class="banner-rail-title"><h2>UUID TRACEABILITY</h2><x-icon name="shield" size="15" /></div>
                <p>Every banner / slider is assigned a unique UUID for full traceability.</p>
                <strong>Last Created UUID</strong>
                <button type="button" class="banner-uuid-value" data-copy="{{ $stats['latest_uuid'] ?: '' }}">{{ $stats['latest_uuid'] ?: 'No UUIDs yet' }} <x-icon name="copy" size="12" /></button>
                @if($selectedId)<a class="banner-audit-link" href="{{ route('admin.banners.audit', $selectedId) }}">View Banner Audit Log <x-icon name="chevron-right" size="13" /></a>@endif
            </section>
            <section class="banner-rail-card banner-notifications-card">
                <div class="banner-rail-title"><h2>RECENT ACTIVITY</h2><a href="#banner-lower-meta">View All</a></div>
                <div class="banner-notifications-list">
                    @foreach($notifications as $notification)
                        <div><i class="notification-dot notification-dot--{{ $notification['tone'] }}"></i><span>{{ $notification['text'] }}</span><small>{{ $notification['time'] }}</small></div>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>

    <section class="banner-feature-strip" aria-label="Banner management capabilities">
        @foreach([
            ['icon'=>'eye','title'=>'EYE-CATCHING VISIBILITY','text'=>'Engage customers with impactful banners and dynamic sliders.'],
            ['icon'=>'calendar','title'=>'SMART SCHEDULING','text'=>'Schedule banners for the right time, season or campaign.'],
            ['icon'=>'shield','title'=>'HIGH PERFORMANCE','text'=>'Track clicks, impressions and CTR to optimize performance.'],
            ['icon'=>'globe','title'=>'CROSS-DEVICE READY','text'=>'Ensure a seamless experience across all devices.'],
            ['icon'=>'settings','title'=>'EASY MANAGEMENT','text'=>'Create, update and organize banners with ease and efficiency.'],
            ['icon'=>'file-text','title'=>'TRACEABLE & SECURE','text'=>'UUID tracking and audit logs ensure full control and accountability.'],
        ] as $feature)
            <div><span class="banner-feature-icon"><x-icon name="{{ $feature['icon'] }}" size="19" /></span><p><strong>{{ $feature['title'] }}</strong><span>{{ $feature['text'] }}</span></p></div>
        @endforeach
    </section>
</div>

<div class="banner-modal" data-banner-modal="editor" hidden>
    <div class="banner-modal-dialog banner-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="banner-editor-title">
        <div class="banner-modal-head"><div><span class="banner-eyebrow">Website &amp; Products</span><h2 id="banner-editor-title" data-banner-editor-title>{{ $selectedId ? 'Edit Banner / Slider' : 'Create Banner / Slider' }}</h2></div><button type="button" data-banner-modal-close aria-label="Close"><x-icon name="close" size="18" /></button></div>
        <form method="post" action="{{ $editorAction }}" enctype="multipart/form-data" data-banner-editor-form data-create-action="{{ route('admin.banners.store') }}" data-update-action="{{ $selectedId ? route('admin.banners.update', $selectedId) : '' }}">
            @csrf
            @if($selectedId)<input type="hidden" name="_method" value="PUT" data-banner-method>@endif
            <div class="banner-editor-grid">
                <label class="banner-editor-wide"><span>Banner Title *</span><input name="title" required maxlength="180" value="{{ $selected['title'] ?? '' }}" data-banner-field="title"></label>
                <label class="banner-editor-wide"><span>Subtitle / Description</span><input name="subtitle" maxlength="255" value="{{ $selected['subtitle'] ?? '' }}" data-banner-field="subtitle"></label>
                <label><span>Banner Type *</span><select name="type" required data-banner-field="type">@foreach($types as $typeKey => $typeLabel)<option value="{{ $typeKey }}" @selected(($selected['type'] ?? 'slider') === $typeKey)>{{ $typeLabel }}</option>@endforeach</select></label>
                <label><span>Position *</span><select name="position" required data-banner-field="position">@foreach($positions as $position)<option value="{{ $position }}" @selected(($selected['position'] ?? '') === $position)>{{ $position }}</option>@endforeach</select></label>
                <label><span>Target / Link</span><input name="target_url" maxlength="500" value="{{ $selected['target_url'] ?? '/' }}" placeholder="/collections/new-arrivals" data-banner-field="target_url"></label>
                <label><span>Link Type</span><select name="target_type" data-banner-field="target_type"><option value="internal" @selected(($selected['target_type'] ?? 'internal') === 'internal')>Internal Link</option><option value="external" @selected(($selected['target_type'] ?? '') === 'external')>External Link</option></select></label>
                <label><span>Status *</span><select name="status" required data-banner-field="status">@foreach($statuses as $statusKey)<option value="{{ $statusKey }}" @selected(($selected['status_value'] ?? 'draft') === $statusKey)>{{ $statusLabels[$statusKey] }}</option>@endforeach</select></label>
                <label><span>Priority</span><input type="number" name="priority" min="1" max="999" value="{{ $selected['priority'] ?? 1 }}" data-banner-field="priority"></label>
                <label><span>Start Date &amp; Time</span><input type="datetime-local" name="starts_at" value="{{ $selected['starts_at_value'] ?? '' }}" data-banner-field="starts_at"></label>
                <label><span>End Date &amp; Time</span><input type="datetime-local" name="ends_at" value="{{ $selected['ends_at_value'] ?? '' }}" data-banner-field="ends_at"></label>
                <label class="banner-editor-wide"><span>Image Path (existing asset or public URL)</span><input name="image_path" maxlength="500" value="{{ $selected['image_path'] ?? '' }}" placeholder="banners/spring-summer.webp" data-banner-field="image_path"></label>
                <label class="banner-editor-wide banner-upload-field"><span>Upload New Image</span><input type="file" name="image" accept="image/*" data-banner-file><small data-banner-file-name>No new image selected</small></label>
                <label><span>Alt Text</span><input name="alt_text" maxlength="255" value="{{ $selected['alt_text'] ?? '' }}" data-banner-field="alt_text"></label>
                <label><span>Title Tooltip</span><input name="title_text" maxlength="180" value="{{ $selected['title_text'] ?? '' }}" data-banner-field="title_text"></label>
                <label class="banner-editor-wide"><span>ARIA Label</span><input name="aria_label" maxlength="180" value="{{ $selected['aria_label'] ?? '' }}" data-banner-field="aria_label"></label>
                <label><span>Animation</span><select name="animation" data-banner-field="animation"><option value="fade" @selected(($selected['animation'] ?? 'fade') === 'fade')>Fade</option><option value="slide" @selected(($selected['animation'] ?? '') === 'slide')>Slide</option><option value="zoom" @selected(($selected['animation'] ?? '') === 'zoom')>Zoom</option></select></label>
                <label><span>Autoplay Speed (Seconds)</span><input type="number" name="autoplay_speed" min="1" max="3600" value="{{ $selected['autoplay_speed'] ?? 5 }}" data-banner-field="autoplay_speed"></label>
                <div class="banner-editor-checks banner-editor-wide">
                    @foreach([['autoplay','Autoplay'],['show_arrows','Show arrows'],['show_dots','Show dots'],['pause_on_hover','Pause on hover']] as [$settingKey,$settingLabel])
                        <label><input type="checkbox" name="{{ $settingKey }}" value="1" @checked($selected[$settingKey] ?? true) data-banner-field="{{ $settingKey }}"><span>{{ $settingLabel }}</span></label>
                    @endforeach
                </div>
                <div class="banner-editor-checks banner-editor-wide">
                    <span class="banner-editor-checks-title">Device visibility</span>
                    @foreach($devices as $device)<label><input type="checkbox" name="devices[]" value="{{ $device }}" @checked(in_array($device, $selectedDevices, true)) data-banner-device="{{ $device }}"><span>{{ ucfirst($device) }}</span></label>@endforeach
                </div>
                <label class="banner-editor-wide"><span>Specific Pages (comma separated, optional)</span><input name="specific_pages" value="{{ $selected['specific_pages_csv'] ?? '' }}" placeholder="Home, New Arrivals" data-banner-field="specific_pages"></label>
            </div>
            <div class="banner-modal-actions"><button type="button" class="banner-outline-button" data-banner-modal-close>Cancel</button><button type="submit" class="banner-primary-button"><x-icon name="check" size="14" /> Save Banner / Slider</button></div>
        </form>
    </div>
</div>

<div class="banner-modal" id="banner-preview-modal" data-banner-modal="preview" hidden>
    <div class="banner-modal-dialog banner-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="banner-preview-title">
        <div class="banner-modal-head"><div><span class="banner-eyebrow">Selected campaign</span><h2 id="banner-preview-title">{{ $selected['title'] ?? 'Banner Preview' }}</h2></div><button type="button" data-banner-modal-close aria-label="Close"><x-icon name="close" size="18" /></button></div>
        @if($selected && !empty($selected['image_url']))<img class="banner-modal-preview-image" src="{{ $selected['image_url'] }}" alt="{{ $selected['alt_text'] ?? '' }}">@else<div class="banner-modal-preview-placeholder"><x-icon name="image" size="36" /><span>Preview image will appear here</span></div>@endif
        <div class="banner-preview-meta"><span class="banner-type-pill {{ $selected ? $typeClass($selected['type']) : '' }}">{{ $selected['type_label'] ?? 'Slider' }}</span><span class="banner-status-pill {{ $selected ? $statusClass($selected['status_key']) : '' }}">{{ $selected['status'] ?? 'Draft' }}</span><span>{{ $selected['position'] ?? 'Home - Main Slider' }}</span><a href="{{ $selected['target_url'] ?? '#' }}" target="_blank" rel="noreferrer">{{ $selected['target_url'] ?? '/' }} <x-icon name="arrow-right" size="12" /></a></div>
        <div class="banner-modal-actions"><button type="button" class="banner-outline-button" data-banner-modal-close>Close</button><button type="button" class="banner-primary-button" data-banner-modal-open="edit"><x-icon name="pencil" size="13" /> Edit Selected Banner</button></div>
    </div>
</div>

<div class="banner-modal" data-banner-modal="audit" hidden>
    <div class="banner-modal-dialog banner-audit-dialog" role="dialog" aria-modal="true" aria-labelledby="banner-audit-title">
        <div class="banner-modal-head"><div><span class="banner-eyebrow">Traceability</span><h2 id="banner-audit-title">Banner Audit &amp; Revisions</h2></div><button type="button" data-banner-modal-close aria-label="Close"><x-icon name="close" size="18" /></button></div>
        @if($selectedId)
            <div class="banner-audit-identity"><strong>{{ $selected['title'] }}</strong><span>{{ $selectedUuid }}</span></div>
            <h3>Revision history</h3>
            <div class="banner-revision-list">
                @forelse($revisions as $revision)
                    <div><span class="banner-revision-number">v{{ $revision->version }}</span><span><strong>{{ $revision->reason ?: 'Saved revision' }}</strong><small>{{ optional($revision->created_at)->format('d M Y g:i A') }} · {{ $revision->user?->name ?? 'System' }}</small></span><form method="post" action="{{ route('admin.banners.restore-revision', [$selectedId, $revision->id]) }}">@csrf<button type="submit">Restore</button></form></div>
                @empty
                    <p class="banner-muted">No revisions have been saved yet.</p>
                @endforelse
            </div>
            <h3>Recent audit events</h3>
            <div class="banner-audit-list">
                @forelse($auditRows as $audit)
                    <div><x-icon name="clock" size="13" /><span><strong>{{ \Illuminate\Support\Str::headline(str_replace(['.', '_'], ' ', $audit->action)) }}</strong><small>{{ optional($audit->created_at)->format('d M Y g:i A') }} · {{ $audit->user?->name ?? 'System' }}</small></span></div>
                @empty
                    <p class="banner-muted">Audit events will appear after the first saved action.</p>
                @endforelse
            </div>
        @else
            <div class="banner-empty-modal"><x-icon name="shield" size="28" /><strong>Reference preview mode</strong><span>Create a banner to enable revisions and audit traceability.</span></div>
        @endif
        <div class="banner-modal-actions"><button type="button" class="banner-outline-button" data-banner-modal-close>Close</button></div>
    </div>
</div>

<div class="banner-modal" data-banner-modal="import" hidden>
    <div class="banner-modal-dialog banner-import-dialog" role="dialog" aria-modal="true" aria-labelledby="banner-import-title">
        <div class="banner-modal-head"><div><span class="banner-eyebrow">Bulk management</span><h2 id="banner-import-title">Import Banners</h2></div><button type="button" data-banner-modal-close aria-label="Close"><x-icon name="close" size="18" /></button></div>
        <form method="post" action="{{ route('admin.banners.import') }}" enctype="multipart/form-data">
            @csrf
            <p class="banner-import-copy">Upload a CSV with <code>title,type,status</code>. Optional columns include subtitle, position, target_url, target_type, starts_at, ends_at, priority, image_path and alt_text.</p>
            <label class="banner-dropzone"><x-icon name="upload" size="25" /><strong>Choose CSV file</strong><span>CSV or TXT, up to 10 MB</span><input type="file" name="file" accept=".csv,.txt" required></label>
            <div class="banner-modal-actions"><button type="button" class="banner-outline-button" data-banner-modal-close>Cancel</button><button type="submit" class="banner-primary-button"><x-icon name="upload" size="14" /> Import</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/banners-reference.js?v=20260912-1') }}"></script>
@endpush
