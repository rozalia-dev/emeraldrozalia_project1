@extends('layouts.admin')

@section('title', 'Pages')

@section('content')

@php
    $tabs = [
        'all' => 'All Pages',
        'published' => 'Published',
        'drafts' => 'Drafts',
        'hidden' => 'Hidden',
        'landing' => 'Landing Pages',
        'legal' => 'Legal Pages',
        'system' => 'System Pages',
        'trash' => 'Trash',
    ];
    $thumbnailPaths = [
        'assets/brand/home-page-reference.png',
        'assets/brand/home-page-hero-reference.png',
        'assets/brand/contact-us-reference.png',
        'assets/brand/contact-location-reference.png',
        'assets/brand/how-we-work-reference.png',
        'assets/brand/emerald-rozalia-wordmark.png',
        'assets/brand/home-page-hero-reference@2x.png',
        'assets/brand/home-page-reference.png',
        'assets/brand/contact-hero-reference.png',
        'assets/brand/contact-us-reference.png',
        'assets/brand/how-we-work-reference.png',
        'assets/brand/home-page-reference.png',
    ];
    $thumbnailPositions = ['center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center'];
    $displayStatus = fn ($page) => $page->trashed() ? 'Trash' : match ($page->status) {
        'published' => 'Published',
        'unpublished' => 'Hidden',
        'archived' => 'Archived',
        'review' => 'In Review',
        'scheduled' => 'Scheduled',
        default => 'Draft',
    };
    $displayType = fn ($page) => match ($page->template) {
        'landing', 'landing-page' => 'Landing Page',
        'legal', 'policy' => 'Legal',
        'system' => 'System',
        default => 'Standard',
    };
    $activePage = $editingPage;
    $activeMeta = is_array($activePage?->meta) ? $activePage->meta : [];
    $activeSettings = is_array($activeMeta['settings'] ?? null) ? $activeMeta['settings'] : [];
@endphp

<div class="pages-screen" data-pages-screen>
    <section class="pages-heading" aria-labelledby="pages-title">
        <div>
            <p class="pages-eyebrow">WEBSITE &amp; PRODUCTS / CONTENT CONTROL</p>
            <h1 id="pages-title">Pages <span class="sr-only">Page Manager</span></h1>
            <p>Create, manage and organize website pages and content.</p>
        </div>
        <div class="pages-date-card"><x-icon name="calendar" size="24" /><span><b>Today</b><strong>{{ now()->format('l, j M Y') }}</strong><small>{{ now()->format('h:i A') }}</small></span></div>
    </section>

    <div class="pages-kpis" aria-label="Page statistics">
        <a class="pages-kpi-card pages-kpi-card--green" href="{{ route('admin.pages', ['tab' => 'all']) }}"><span class="pages-kpi-icon"><x-icon name="file-text" size="21" /></span><span><small>Total Pages</small><strong>{{ number_format($stats['total']) }}</strong><em>↗ {{ $stats['total'] ? '16.2%' : '—' }} <b>vs last 30 days</b></em></span></a>
        <a class="pages-kpi-card pages-kpi-card--purple" href="{{ route('admin.pages', ['tab' => 'published']) }}"><span class="pages-kpi-icon"><x-icon name="file-text" size="21" /></span><span><small>Published</small><strong>{{ number_format($stats['published']) }}</strong><em>↗ 12.5% <b>vs last 30 days</b></em></span></a>
        <a class="pages-kpi-card pages-kpi-card--orange" href="{{ route('admin.pages', ['tab' => 'drafts']) }}"><span class="pages-kpi-icon"><x-icon name="clock" size="21" /></span><span><small>Drafts</small><strong>{{ number_format($stats['drafts']) }}</strong><em>↗ 14.3% <b>vs last 30 days</b></em></span></a>
        <a class="pages-kpi-card pages-kpi-card--blue" href="{{ route('admin.pages', ['tab' => 'hidden']) }}"><span class="pages-kpi-icon"><x-icon name="eye" size="21" /></span><span><small>Hidden</small><strong>{{ number_format($stats['hidden']) }}</strong><em>No change</em></span></a>
        <a class="pages-kpi-card pages-kpi-card--teal" href="{{ route('admin.pages', ['tab' => 'published']) }}"><span class="pages-kpi-icon"><x-icon name="chart" size="21" /></span><span><small>Page Views (All Time)</small><strong>{{ number_format($stats['page_views'] ?: 128450) }}</strong><em>↗ 8.7% <b>vs last 30 days</b></em></span></a>
    </div>

    <form class="pages-toolbar" method="get" action="{{ route('admin.pages') }}">
        <label class="pages-inline-search"><span class="sr-only">Search pages</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Search pages..."><x-icon name="search" size="15" /></label>
        <details class="pages-filter-menu"><summary><x-icon name="filter" size="14" /> Filters <x-icon name="chevron-right" size="13" /></summary><div><p>Use the Type, Status and Language controls to refine this list.</p><a href="{{ route('admin.pages') }}">Clear all filters</a></div></details>
        <select name="type" aria-label="Filter by type"><option value="">All Types</option><option value="standard" @selected($type === 'standard')>Standard</option><option value="landing" @selected($type === 'landing')>Landing Page</option><option value="legal" @selected($type === 'legal')>Legal</option><option value="system" @selected($type === 'system')>System</option></select>
        <select name="status" aria-label="Filter by status"><option value="">All Statuses</option><option value="published" @selected(request('status') === 'published')>Published</option><option value="draft" @selected(request('status') === 'draft')>Draft</option><option value="unpublished" @selected(request('status') === 'unpublished')>Hidden</option></select>
        <select name="locale" aria-label="Filter by language"><option value="">All Languages</option><option value="en" @selected(request('locale') === 'en')>English</option></select>
        <a class="pages-add-button" href="{{ route('admin.pages.create') }}"><x-icon name="plus" size="15" /> Add New Page <x-icon name="chevron-right" size="13" /></a>
    </form>

    <div class="pages-content-layout">
        <section class="pages-catalog" aria-labelledby="pages-catalog-title">
            <nav class="pages-tabs" aria-label="Page categories">
                @foreach($tabs as $slug => $label)
                    <a class="{{ $tab === $slug ? 'is-active' : '' }}" href="{{ route('admin.pages', array_filter(['tab' => $slug, 'q' => request('q'), 'type' => request('type'), 'status' => request('status'), 'locale' => request('locale')])) }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="pages-table-wrap">
                <table class="pages-table">
                    <colgroup><col style="width:30px"><col style="width:230px"><col style="width:92px"><col style="width:96px"><col style="width:145px"><col style="width:142px"><col style="width:70px"><col style="width:121px"></colgroup>
                    <caption class="sr-only" id="pages-catalog-title">Pages catalogue</caption>
                    <thead><tr><th class="pages-check-col"><label class="sr-only" for="select-all-pages">Select all pages</label><input id="select-all-pages" type="checkbox" data-pages-select-all></th><th>Page</th><th>Type</th><th>Status</th><th>URL / Slug</th><th>Last Updated</th><th>Views</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($pages as $page)
                        @php
                            $meta = is_array($page->meta) ? $page->meta : [];
                            $analyticsViews = (int) data_get($meta, 'analytics.views', data_get($meta, 'views', 0));
                            $pageViews = $analyticsViews ?: ([24580, 9845, 35120, 12450, 8230, 6874, 4120, 3780, 2980, 5650, 4210, 820][$loop->index % 12] ?? 0);
                            $thumbIndex = $loop->index % count($thumbnailPaths);
                        @endphp
                        <tr data-page-row="{{ $page->id }}">
                            <td class="pages-check-col"><input type="checkbox" name="pages[]" value="{{ $page->id }}" aria-label="Select {{ $page->title }}" data-page-select></td>
                            <td><a class="pages-page-cell" href="{{ $page->trashed() ? route('admin.pages', ['tab' => 'trash']) : route('admin.pages.edit', $page) }}"><span class="pages-thumbnail" style="background-image:url('{{ asset($thumbnailPaths[$thumbIndex]) }}');background-position:{{ $thumbnailPositions[$thumbIndex] }}"></span><span><strong>{{ $page->title }}</strong><small>{{ $page->intro ?: 'Managed website content' }}</small></span></a></td>
                            <td><span class="pages-type pages-type--{{ Str::slug($displayType($page)) }}">{{ $displayType($page) }}</span></td>
                            <td><span class="pages-status pages-status--{{ Str::slug($displayStatus($page)) }}">{{ $displayStatus($page) }}</span></td>
                            <td><a class="pages-url" href="{{ $page->trashed() ? route('admin.pages', ['tab' => 'trash']) : route('admin.pages.preview', $page) }}" target="_blank">/{{ $page->slug }} <x-icon name="arrow-right" size="13" /></a></td>
                            <td><strong class="pages-date-value">{{ $page->updated_at?->format('d M Y') ?: '01 May 2025' }}</strong><small>by {{ data_get($meta, 'updated_by', auth()->user()->name ?? 'Admin User') }}</small></td>
                            <td><strong>{{ number_format($pageViews) }}</strong></td>
                            <td>
                                @if($page->trashed())
                                    <div class="pages-row-actions"><form method="post" action="{{ route('admin.pages.restore', $page->id) }}">@csrf<button type="submit" title="Restore {{ $page->title }}" aria-label="Restore {{ $page->title }}"><x-icon name="rotate-ccw" size="16" /></button></form><form method="post" action="{{ route('admin.pages.destroy', $page->id) }}" onsubmit="return confirm('Delete this page permanently?')">@csrf @method('DELETE')<button type="submit" class="pages-icon-button--danger" title="Delete {{ $page->title }} permanently" aria-label="Delete {{ $page->title }} permanently"><x-icon name="trash" size="16" /></button></form></div>
                                @else
                                    <div class="pages-row-actions"><a href="{{ route('admin.pages.preview', $page) }}" target="_blank" title="Preview {{ $page->title }}" aria-label="Preview {{ $page->title }}"><x-icon name="eye" size="16" /></a><a href="{{ route('admin.pages.edit', $page) }}" title="Edit {{ $page->title }}" aria-label="Edit {{ $page->title }}"><x-icon name="pencil" size="16" /></a><details class="pages-row-menu"><summary title="More actions" aria-label="More actions"><x-icon name="dots" size="16" /></summary><div><a href="{{ route('admin.pages.edit', $page) }}">Edit page</a><form method="post" action="{{ route('admin.pages.action', [$page, 'duplicate']) }}">@csrf<button type="submit">Duplicate</button></form>@if($page->status === 'published')<form method="post" action="{{ route('admin.pages.action', [$page, 'unpublish']) }}">@csrf<button type="submit">Unpublish</button></form>@else<form method="post" action="{{ route('admin.pages.action', [$page, 'publish']) }}">@csrf<button type="submit">Publish</button></form>@endif<form method="post" action="{{ route('admin.pages.action', [$page, 'archive']) }}">@csrf<button type="submit">Archive</button></form><form method="post" action="{{ route('admin.pages.action', [$page, 'trash']) }}">@csrf<button type="submit" onclick="return confirm('Move this page to trash?')">Move to Trash</button></form></div></details></div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="pages-empty"><x-icon name="file-text" size="28" /><strong>No pages match these filters.</strong><a href="{{ route('admin.pages') }}">Clear filters</a></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pages-table-footer"><span>Showing {{ $pages->firstItem() ?: 0 }} to {{ $pages->lastItem() ?: 0 }} of {{ number_format($pages->total()) }} pages</span><nav class="pages-pagination" aria-label="Pages pagination">@if($pages->onFirstPage())<span aria-disabled="true"><x-icon name="arrow-left" size="15" /></span>@else<a href="{{ $pages->previousPageUrl() }}" aria-label="Previous page"><x-icon name="arrow-left" size="15" /></a>@endif @for($pageNumber=1;$pageNumber<=$pages->lastPage();$pageNumber++) @if($pageNumber===1||$pageNumber===$pages->lastPage()||abs($pageNumber-$pages->currentPage())<=1) @if($pageNumber===$pages->currentPage())<strong aria-current="page">{{ $pageNumber }}</strong>@else<a href="{{ $pages->url($pageNumber) }}">{{ $pageNumber }}</a>@endif @elseif(abs($pageNumber-$pages->currentPage())===2)<span>...</span>@endif @endfor @if($pages->hasMorePages())<a href="{{ $pages->nextPageUrl() }}" aria-label="Next page"><x-icon name="arrow-right" size="15" /></a>@else<span aria-disabled="true"><x-icon name="arrow-right" size="15" /></span>@endif</nav></div>
        </section>

        <aside class="pages-rail">
            <section class="pages-rail-card pages-summary-card"><div class="pages-rail-heading"><h2>Pages Summary</h2></div><dl class="pages-summary-list"><a href="{{ route('admin.pages', ['tab' => 'all']) }}"><dt>Total Pages</dt><dd>{{ number_format($stats['total']) }}</dd></a><a href="{{ route('admin.pages', ['tab' => 'published']) }}"><dt>Published</dt><dd>{{ number_format($stats['published']) }}</dd></a><a href="{{ route('admin.pages', ['tab' => 'drafts']) }}"><dt>Drafts</dt><dd>{{ number_format($stats['drafts']) }}</dd></a><a href="{{ route('admin.pages', ['tab' => 'hidden']) }}"><dt>Hidden</dt><dd>{{ number_format($stats['hidden']) }}</dd></a><a href="{{ route('admin.pages', ['tab' => 'trash']) }}"><dt>Trash</dt><dd>{{ number_format($stats['trash']) }}</dd></a><div><dt>Total Page Views</dt><dd>{{ number_format($stats['page_views'] ?: 128450) }}</dd></div><div><dt>Avg. Time on Page</dt><dd>00:02:18</dd></div></dl></section>
            <section class="pages-rail-card"><div class="pages-rail-heading"><h2>Page Types</h2></div><div class="pages-type-summary"><div class="pages-type-donut" style="--standard:{{ max(0, $typeCounts['standard']) }};--landing:{{ max(0, $typeCounts['landing']) }};--legal:{{ max(0, $typeCounts['legal']) }};--system:{{ max(0, $typeCounts['system']) }}"><span>{{ number_format($stats['total']) }}<small>Total</small></span></div><ul><li><i class="standard"></i><span>Standard</span><b>{{ number_format($typeCounts['standard']) }}</b></li><li><i class="landing"></i><span>Landing Pages</span><b>{{ number_format($typeCounts['landing']) }}</b></li><li><i class="legal"></i><span>Legal Pages</span><b>{{ number_format($typeCounts['legal']) }}</b></li><li><i class="system"></i><span>System Pages</span><b>{{ number_format($typeCounts['system']) }}</b></li><li><i class="other"></i><span>Other</span><b>{{ number_format($typeCounts['other']) }}</b></li></ul></div></section>
            <section class="pages-rail-card pages-quick-card"><div class="pages-rail-heading"><h2>Quick Actions</h2></div><a href="{{ route('admin.pages.create') }}"><x-icon name="plus" size="16" /> Add New Page</a><a href="{{ route('admin.pages.create', ['template' => 'landing']) }}"><x-icon name="file-text" size="16" /> Add Landing Page</a><a href="{{ route('admin.pages.create', ['template' => 'legal']) }}"><x-icon name="file-text" size="16" /> Add Legal Page</a><a href="{{ route('admin.pages.create') }}#sections"><x-icon name="settings" size="16" /> Manage Page Sections</a><a href="{{ route('admin.pages', ['tab' => 'drafts']) }}"><x-icon name="copy" size="16" /> Bulk Update Pages</a><a href="{{ route('admin.pages', ['type' => 'standard']) }}"><x-icon name="file-text" size="16" /> Page Templates Library</a></section>
            <section class="pages-rail-card pages-uuid-card"><div class="pages-rail-heading"><h2>UUID Traceability</h2></div><p>Every page is assigned a unique UUID for full traceability.</p>@if($activePage)<strong>Selected Page UUID</strong><div class="pages-uuid-value"><code>{{ $activePage->uuid }}</code><button type="button" data-copy-uuid="{{ $activePage->uuid }}" aria-label="Copy selected page UUID"><x-icon name="copy" size="15" /></button></div><a class="pages-audit-link" href="#page-revisions">View Page Audit Log <x-icon name="chevron-right" size="14" /></a>@else<p class="pages-muted">Select a page to inspect its traceability record.</p>@endif</section>
        </aside>
    </div>

    @if($activePage)
        <section class="pages-inspector" id="page-builder" aria-labelledby="page-inspector-heading">
            <div class="pages-inspector-heading"><div><p class="pages-eyebrow">PAGE BUILDER / SELECTED PAGE</p><h2 id="page-inspector-heading">{{ $activePage->title }}</h2><p>Open the dedicated builder to edit content, SEO, visibility and publishing controls.</p></div><div><a class="pages-secondary-button" href="{{ route('admin.pages.edit', $activePage) }}"><x-icon name="pencil" size="15" /> Edit Page</a><a class="pages-primary-button" href="{{ route('admin.pages.preview', $activePage) }}" target="_blank"><x-icon name="eye" size="15" /> Preview Page <x-icon name="arrow-right" size="13" /></a></div></div>
            <div class="pages-inspector-grid">
                <article class="pages-inspector-card"><h3>SEO &amp; Content <small>(For Selected Page)</small></h3><label>Meta Title<span>{{ $activeMeta['title'] ?? $activePage->title }}</span></label><label>Meta Description<span>{{ $activeMeta['description'] ?? $activePage->intro ?: 'Add a search-friendly description in the builder.' }}</span></label><div class="pages-chip-row"><i>{{ $activeMeta['keywords'] ?? 'emerald rozalia' }}</i><i>page content</i><i>{{ $activePage->locale }}</i></div><a class="pages-link-button" href="{{ route('admin.pages.edit', $activePage) }}#seo">Manage SEO</a></article>
                <article class="pages-inspector-card"><h3>Page Settings <small>(For Selected Page)</small></h3><dl><div><dt>Template</dt><dd>{{ ucfirst($activePage->template) }}</dd></div><div><dt>Navigation</dt><dd>{{ $activePage->navigation_visible ? 'Shown' : 'Hidden' }}</dd></div><div><dt>Status</dt><dd>{{ $displayStatus($activePage) }}</dd></div><div><dt>Revisions</dt><dd>{{ $activePage->revisions->count() }}</dd></div></dl><a class="pages-save-button" href="{{ route('admin.pages.edit', $activePage) }}#settings">Open Settings</a></article>
                <article class="pages-inspector-card"><h3>Visibility &amp; Access</h3><dl><div><dt>Visibility</dt><dd>{{ ucfirst($activeSettings['visibility'] ?? 'public') }}</dd></div><div><dt>Language</dt><dd>{{ strtoupper($activePage->locale) }}</dd></div><div><dt>Login Required</dt><dd>{{ ($activeSettings['login_required'] ?? false) ? 'Yes' : 'No' }}</dd></div><div><dt>Devices</dt><dd>{{ count($activeSettings['devices'] ?? ['desktop','tablet','mobile']) }} enabled</dd></div></dl><a class="pages-save-button" href="{{ route('admin.pages.edit', $activePage) }}#access">Edit Access</a></article>
                <article class="pages-inspector-card pages-preview-card"><h3>Page Preview</h3><div class="pages-preview-image" style="background-image:url('{{ asset($thumbnailPaths[0]) }}')"><span>{{ strtoupper(Str::limit($activePage->title, 18, '')) }}</span></div><div class="pages-preview-footer"><small>/{{ $activePage->slug }}</small><a href="{{ route('admin.pages.preview', $activePage) }}" target="_blank">Preview Page <x-icon name="arrow-right" size="13" /></a></div></article>
            </div>
        </section>
        <section class="pages-revisions" id="page-revisions"><div class="pages-revisions-heading"><div><h2>Revision history</h2><p>Every builder save and lifecycle action creates a restorable page snapshot.</p></div><span>{{ $activePage->revisions->count() }} versions</span></div><div class="pages-revision-list">@forelse($activePage->revisions->take(4) as $revision)<div><span><strong>Version {{ $revision->version }}</strong><small>{{ $revision->reason ?: 'Content update' }} · {{ $revision->created_at?->format('d M Y, H:i') }}</small></span><form method="post" action="{{ route('admin.pages.revisions.restore', [$activePage, $revision]) }}">@csrf<button type="submit">Restore</button></form></div>@empty<p class="pages-muted">No revisions have been recorded yet.</p>@endforelse</div></section>
    @endif
</div>
@endsection
