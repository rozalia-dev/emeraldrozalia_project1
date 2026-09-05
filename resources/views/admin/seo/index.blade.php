@extends('layouts.admin')

@section('title', 'SEO & Content')

@section('content')
@php
    $score = (int) $summary['health_score'];
    $scoreLabel = $score >= 80 ? 'Good' : ($score >= 50 ? 'Needs improvement' : 'Needs attention');
    $metaTotal = max(1, (int) $metaStatus['total']);
    $metaOptimized = round(($metaStatus['optimized'] / $metaTotal) * 100, 2);
    $metaMissing = round(($metaStatus['missing'] / $metaTotal) * 100, 2);
    $metaDuplicate = round(($metaStatus['duplicate'] / $metaTotal) * 100, 2);
    $metaLong = round(($metaStatus['too_long'] / $metaTotal) * 100, 2);
    $indexableSources = max(0, (int) $summary['total_pages'] - (int) $summary['noindex_pages']);
    $keywordText = collect($keywords)->pluck('keyword')->implode("\n");
@endphp

<div class="seo-page" data-seo-dashboard>
    <div class="seo-page-head">
        <div>
            <div class="seo-breadcrumb"><span>WEBSITE &amp; PRODUCTS</span><b>•</b><span>SEO &amp; CONTENT</span></div>
            <h1>SEO &amp; Content</h1>
            <p>Improve how Emerald Rozalia pages are understood, indexed and discovered.</p>
        </div>
        <div class="seo-date-card">
            <span class="seo-date-icon">▣</span>
            <div><b>Today</b><strong>{{ now()->format('l, d M Y') }}</strong><em>{{ now()->format('h:i A') }}</em></div>
        </div>
    </div>

    <div class="seo-kpis">
        <article class="seo-kpi">
            <span class="seo-kpi-icon seo-kpi-green">⌁</span>
            <div><small>SEO Health Score</small><strong>{{ $summary['health_score'] }}<i>/100</i></strong><em>{{ $summary['pages_with_issues'] }} pages need attention</em></div>
        </article>
        <article class="seo-kpi">
            <span class="seo-kpi-icon seo-kpi-purple">▧</span>
            <div><small>Indexed Pages</small><strong>{{ number_format($summary['indexed_pages']) }}</strong><em>{{ number_format($summary['total_pages']) }} tracked pages</em></div>
        </article>
        <article class="seo-kpi">
            <span class="seo-kpi-icon seo-kpi-orange">⌕</span>
            <div><small>Organic Traffic</small><strong>{{ number_format($summary['organic_traffic']) }}</strong><em>{{ $summary['organic_traffic'] ? 'Connected data' : 'Search Console not connected' }}</em></div>
        </article>
        <article class="seo-kpi">
            <span class="seo-kpi-icon seo-kpi-blue">◎</span>
            <div><small>Tracked Keywords</small><strong>{{ number_format($summary['top_keywords']) }}</strong><em>{{ $summary['top_keywords'] ? 'Target list configured' : 'Add target keywords' }}</em></div>
        </article>
        <article class="seo-kpi">
            <span class="seo-kpi-icon seo-kpi-teal">↗</span>
            <div><small>Backlinks</small><strong>{{ number_format($summary['backlinks']) }}</strong><em>{{ $summary['backlinks'] ? 'Connected data' : 'Awaiting integration' }}</em></div>
        </article>
    </div>

    <nav class="seo-tabs" aria-label="SEO sections">
        @foreach([
            'overview' => 'SEO Overview',
            'meta' => 'Meta Management',
            'keywords' => 'Keywords',
            'content' => 'Content Manager',
            'redirects' => 'Redirects ('.$summary['redirects'].')',
            'sitemap' => 'Sitemap',
            'robots' => 'Robots & Indexing',
            'schema' => 'Schema (Structured Data)',
        ] as $key => $label)
            <a class="{{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.seo.dashboard', ['tab' => $key]) }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($tab === 'overview')
        <div class="seo-layout">
            <div class="seo-main-column">
                <section class="seo-card seo-overview-card">
                    <div class="seo-card-heading"><div><h2>SEO Health Overview</h2><p>Local content checks from your published pages, products and categories.</p></div><form method="post" action="{{ route('admin.seo.audit') }}">@csrf<button class="seo-button seo-button-outline" type="submit">⌁ &nbsp; Run SEO Audit</button></form></div>
                    <div class="seo-overview-grid">
                        <div class="seo-score-panel">
                            <div class="seo-score-ring" style="--seo-score: {{ $score }}"><strong>{{ $score }}</strong><span>{{ $scoreLabel }}</span></div>
                            <div class="seo-score-legend">
                                <span><i class="seo-dot seo-dot-good"></i> Good <b>{{ $issues->where('severity', 'low')->count() }}</b></span>
                                <span><i class="seo-dot seo-dot-medium"></i> Needs improvement <b>{{ $issues->where('severity', 'medium')->count() }}</b></span>
                                <span><i class="seo-dot seo-dot-high"></i> Needs attention <b>{{ $issues->where('severity', 'high')->count() }}</b></span>
                            </div>
                        </div>
                        <div class="seo-breakdown">
                            <h3>Health Breakdown</h3>
                            @foreach($healthBreakdown as $check)
                                <div class="seo-progress-row"><span>{{ $check['label'] }}</span><div class="seo-progress"><i style="width: {{ $check['score'] ?? 0 }}%"></i></div><b>{{ $check['score'] === null ? 'Not measured' : $check['score'].' / 100' }}</b></div>
                            @endforeach
                        </div>
                        <div class="seo-crawl-summary">
                            <h3>Crawl Summary</h3>
                            <dl>
                                <div><dt>Pages crawled</dt><dd>{{ number_format($summary['pages_crawled']) }}</dd></div>
                                <div><dt>Pages indexed</dt><dd>{{ number_format($summary['indexed_pages']) }}</dd></div>
                                <div><dt>Pages with issues</dt><dd class="is-danger">{{ number_format($summary['pages_with_issues']) }}</dd></div>
                                <div><dt>Redirects (301)</dt><dd>{{ number_format($summary['redirects']) }}</dd></div>
                                <div><dt>Broken links</dt><dd>{{ number_format($summary['broken_links']) }}</dd></div>
                                <div><dt>Blocked by robots.txt</dt><dd>{{ number_format($summary['noindex_pages']) }}</dd></div>
                            </dl>
                            <a class="seo-inline-action" href="{{ route('admin.seo.dashboard', ['tab' => 'content']) }}">View all issues <span>→</span></a>
                        </div>
                    </div>
                </section>

                <section class="seo-card">
                    <div class="seo-card-heading"><div><h2>Pages Needing Attention</h2><p>Fix metadata issues directly, then run the audit again to confirm the result.</p></div><a class="seo-inline-action" href="{{ route('admin.seo.dashboard', ['tab' => 'content']) }}">View all issues <span>→</span></a></div>
                    <div class="seo-table-wrap">
                        <table class="seo-table">
                            <thead><tr><th>Page</th><th>Issue</th><th>Severity</th><th>Status</th><th>Last updated</th><th>Actions</th></tr></thead>
                            <tbody>
                            @forelse($issues->take(5) as $issue)
                                <tr>
                                    <td><strong>{{ $issue['path'] }}</strong><small>{{ str($issue['source_type'])->headline() }}</small></td>
                                    <td>{{ $issue['title'] }}<small>{{ $issue['details'] }}</small></td>
                                    <td><span class="seo-severity seo-severity-{{ $issue['severity'] }}">{{ str($issue['severity'])->headline() }}</span></td>
                                    <td><span class="seo-status">{{ str($issue['status'])->headline() }}</span></td>
                                    <td>{{ $issue['last_updated']?->format('d M Y') ?? '—' }}</td>
                                    <td class="seo-row-actions">
                                        @if($issue['issue_type'] !== 'broken-internal-link')<form method="post" action="{{ route('admin.seo.issue.fix', [$issue['source_type'], $issue['source_id'], $issue['issue_type']]) }}">@csrf<button type="submit">Fix</button></form>@endif
                                        @if($issue['persistent'] && isset($issue['uuid']))<form method="post" action="{{ route('admin.seo.issue.resolve', $issue['uuid']) }}">@csrf<button type="submit" title="Mark as resolved">{{ $issue['issue_type'] === 'broken-internal-link' ? 'Review' : '✓' }}</button></form>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="seo-empty" colspan="6">No open SEO issues. Run an audit after publishing content to keep this view current.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="seo-card seo-suggestions-card">
                    <div class="seo-card-heading"><div><h2>Content Suggestions</h2><p>Actionable improvements based on the current content inventory.</p></div><a class="seo-inline-action" href="{{ route('admin.seo.dashboard', ['tab' => 'content']) }}">View all suggestions <span>→</span></a></div>
                    <div class="seo-suggestions">
                        <a href="{{ route('admin.seo.dashboard', ['tab' => 'meta']) }}"><span>▣</span><div><b>Add metadata to tracked pages</b><small>{{ $metaStatus['missing'] }} pages have a missing title or description.</small></div><strong>→</strong></a>
                        <a href="{{ route('admin.seo.dashboard', ['tab' => 'content']) }}"><span>↗</span><div><b>Resolve heading issues</b><small>{{ $issues->where('issue_type', 'missing-h1')->count() }} pages need a clear H1 heading.</small></div><strong>→</strong></a>
                        <a href="{{ route('admin.seo.dashboard', ['tab' => 'sitemap']) }}"><span>⌁</span><div><b>Refresh the XML sitemap</b><small>Keep indexable pages available to search engines.</small></div><strong>→</strong></a>
                        <a href="{{ route('admin.seo.dashboard', ['tab' => 'schema']) }}"><span>◇</span><div><b>Review structured data</b><small>Maintain the Organization schema for Emerald Rozalia Limited.</small></div><strong>→</strong></a>
                    </div>
                </section>

                <div class="seo-bottom-grid">
                    <section class="seo-card seo-meta-status-card">
                        <div class="seo-card-heading"><h2>Meta Title &amp; Description Status</h2><a class="seo-inline-action" href="{{ route('admin.seo.dashboard', ['tab' => 'meta']) }}">Manage meta <span>→</span></a></div>
                        <div class="seo-donut-row"><div class="seo-donut" style="--seo-optimized: {{ $metaOptimized }}%; --seo-missing: {{ $metaMissing }}%; --seo-duplicate: {{ $metaDuplicate }}%; --seo-long: {{ $metaLong }}%"><strong>{{ $metaStatus['total'] }}</strong><span>Total pages</span></div><ul><li><i class="seo-dot seo-dot-good"></i> Optimized <b>{{ $metaStatus['optimized'] }}</b></li><li><i class="seo-dot seo-dot-missing"></i> Missing <b>{{ $metaStatus['missing'] }}</b></li><li><i class="seo-dot seo-dot-medium"></i> Duplicate <b>{{ $metaStatus['duplicate'] }}</b></li><li><i class="seo-dot seo-dot-purple"></i> Too long <b>{{ $metaStatus['too_long'] }}</b></li></ul></div>
                    </section>
                    <section class="seo-card">
                        <div class="seo-card-heading"><h2>Top Content</h2><a class="seo-inline-action" href="{{ route('admin.seo.dashboard', ['tab' => 'meta']) }}">View all content <span>→</span></a></div>
                        <div class="seo-mini-table"><div class="seo-mini-head"><span>Page</span><span>Indexed</span></div>@foreach($metaRows->take(5) as $row)<div><span><b>{{ $row['title'] }}</b><small>{{ $row['path'] }}</small></span><strong>{{ $row['indexed'] ? 'Yes' : 'No' }}</strong></div>@endforeach</div>
                    </section>
                    <section class="seo-card">
                        <div class="seo-card-heading"><h2>Schema Usage</h2><a class="seo-inline-action" href="{{ route('admin.seo.dashboard', ['tab' => 'schema']) }}">Manage schema <span>→</span></a></div>
                        <div class="seo-schema-list"><div><span>◇</span><b>Organization</b><strong>1</strong></div><div><span>▧</span><b>Product</b><strong>{{ $indexableSources }}</strong></div><div><span>⌂</span><b>Breadcrumb</b><strong>{{ $summary['indexed_pages'] }}</strong></div></div>
                    </section>
                    <section class="seo-card">
                        <div class="seo-card-heading"><h2>Sitemap &amp; Robots</h2><a class="seo-inline-action" href="{{ route('admin.seo.dashboard', ['tab' => 'sitemap']) }}">Open tools <span>→</span></a></div>
                        <div class="seo-file-list"><div><span>⇩</span><b>XML Sitemap</b><small>{{ $summary['sitemap_generated_at'] ? 'Last generated '.$summary['sitemap_generated_at'] : 'Not generated yet' }}</small><a href="{{ route('seo.sitemap') }}" target="_blank" rel="noreferrer">View</a></div><div><span>▤</span><b>Robots.txt</b><small>Public indexing rules</small><a href="{{ route('seo.robots') }}" target="_blank" rel="noreferrer">View</a></div></div>
                    </section>
                </div>
            </div>

            <aside class="seo-side-column">
                <section class="seo-card seo-summary-card"><h2>SEO Summary</h2><dl><div><dt>SEO Health Score</dt><dd>{{ $summary['health_score'] }} / 100</dd></div><div><dt>Total Pages</dt><dd>{{ $summary['total_pages'] }}</dd></div><div><dt>Indexed Pages</dt><dd>{{ $summary['indexed_pages'] }}</dd></div><div><dt>Noindex Pages</dt><dd>{{ $summary['noindex_pages'] }}</dd></div><div><dt>Target Keywords</dt><dd>{{ $summary['top_keywords'] }}</dd></div><div><dt>Pages with Issues</dt><dd class="is-danger">{{ $summary['pages_with_issues'] }}</dd></div><div><dt>Last Crawl</dt><dd>{{ $summary['last_crawl']?->format('d M Y') ?? 'Not run' }}</dd></div></dl><a class="seo-side-link" href="{{ route('admin.seo.dashboard', ['tab' => 'content']) }}">View full SEO report <span>→</span></a></section>
                <section class="seo-card seo-keywords-card"><div class="seo-card-heading"><h2>Top Keywords</h2><a class="seo-inline-action" href="{{ route('admin.seo.dashboard', ['tab' => 'keywords']) }}">Manage <span>→</span></a></div><div class="seo-keyword-head"><span>Keyword</span><span>Position</span><span>Change</span></div>@forelse(collect($keywords)->take(5) as $keyword)<div class="seo-keyword-row"><span>{{ $keyword['keyword'] }}</span><b>{{ $keyword['position'] ?? '—' }}</b><strong class="{{ (($keyword['change'] ?? 0) > 0) ? 'is-up' : '' }}">{{ isset($keyword['change']) && $keyword['change'] !== null ? ($keyword['change'] > 0 ? '↑ '.$keyword['change'] : $keyword['change']) : '—' }}</strong></div>@empty<p class="seo-empty">No target keywords yet.</p>@endforelse</section>
                <section class="seo-card seo-quick-card"><h2>Quick Actions</h2><a href="{{ route('admin.seo.dashboard', ['tab' => 'meta']) }}">⇩ <span>Add Meta to Pages</span><b>→</b></a><a href="{{ route('admin.seo.dashboard', ['tab' => 'meta']) }}">▤ <span>Bulk Meta Update</span><b>→</b></a><form method="post" action="{{ route('admin.seo.sitemap.generate') }}">@csrf<button type="submit">⌁ <span>Generate XML Sitemap</span><b>→</b></button></form><form method="post" action="{{ route('admin.seo.links') }}">@csrf<button type="submit">⌕ <span>Check Broken Links</span><b>→</b></button></form><form method="post" action="{{ route('admin.seo.audit') }}">@csrf<button type="submit">◎ <span>SEO Audit</span><b>→</b></button></form><a href="{{ route('admin.seo.dashboard', ['tab' => 'content']) }}">◇ <span>Content Suggestions</span><b>→</b></a><a href="{{ route('admin.seo.dashboard', ['tab' => 'schema']) }}">◈ <span>Schema Generator</span><b>→</b></a></section>
                <section class="seo-card seo-uuid-card"><h2>UUID Traceability</h2><p>SEO changes and audit results are recorded with UUID-backed history.</p><div class="seo-uuid">{{ $audits->first()?->uuid ?? 'Run an audit to create the first trace' }} <button type="button" data-seo-copy="{{ $audits->first()?->uuid ?? '' }}" title="Copy UUID">▣</button></div><a class="seo-side-link" href="{{ route('admin.resource', 'audit-logs') }}">View SEO audit log <span>→</span></a></section>
            </aside>
        </div>
    @elseif($tab === 'meta')
        <section class="seo-card seo-tab-card">
            <div class="seo-card-heading"><div><h2>Meta Management</h2><p>Edit titles, descriptions, focus keywords and index visibility for every tracked website surface.</p></div><form method="post" action="{{ route('admin.seo.audit') }}">@csrf<button class="seo-button" type="submit">Run SEO Audit</button></form></div>
            <div class="seo-table-wrap"><table class="seo-table seo-meta-table"><thead><tr><th>Page</th><th>Meta title</th><th>Meta description</th><th>Focus keyword</th><th>Indexing</th><th>Save</th></tr></thead><tbody>@forelse($metaRows as $row)<tr><td><strong>{{ $row['title'] }}</strong><small>{{ $row['path'] }} · {{ $row['kind'] }}</small></td><td colspan="4"><form class="seo-meta-form" method="post" action="{{ route('admin.seo.meta.update', [$row['source_type'], $row['source_id']]) }}">@csrf @method('PATCH')<label><span>Title <output data-seo-count-for="meta-title-{{ $row['source_type'] }}-{{ $row['source_id'] }}">{{ strlen((string) $row['meta_title']) }}</output>/60</span><input id="meta-title-{{ $row['source_type'] }}-{{ $row['source_id'] }}" data-seo-field="title" name="meta_title" value="{{ $row['meta_title'] }}" maxlength="180"></label><label><span>Description <output data-seo-count-for="meta-description-{{ $row['source_type'] }}-{{ $row['source_id'] }}">{{ strlen((string) $row['meta_description']) }}</output>/160</span><textarea id="meta-description-{{ $row['source_type'] }}-{{ $row['source_id'] }}" data-seo-field="description" name="meta_description" maxlength="500">{{ $row['meta_description'] }}</textarea></label><label><span>Focus keyword</span><input name="focus_keyword" value="{{ $row['focus_keyword'] }}" maxlength="120"></label><label class="seo-checkbox"><input type="checkbox" name="noindex" value="1" @checked($row['noindex'])> Noindex</label><button class="seo-button seo-button-small" type="submit">Save</button></form></td></tr>@empty<tr><td colspan="6" class="seo-empty">No pages are available for metadata management.</td></tr>@endforelse</tbody></table></div>
        </section>
    @elseif($tab === 'keywords')
        <div class="seo-tab-grid"><section class="seo-card seo-tab-card"><div class="seo-card-heading"><div><h2>Target Keywords</h2><p>Keep one target phrase per line. Live positions are populated only after a search-data integration is connected.</p></div></div><form method="post" action="{{ route('admin.seo.keywords.update') }}" class="seo-settings-form">@csrf<label for="seo-keywords">Keyword list</label><textarea id="seo-keywords" name="keywords" rows="12" placeholder="emerald rozalia hats&#10;luxury caps ireland">{{ $keywordText }}</textarea><button class="seo-button" type="submit">Save Keywords</button></form></section><section class="seo-card seo-tab-card"><div class="seo-card-heading"><h2>Tracked Terms</h2><span class="seo-muted">{{ count($keywords) }} configured</span></div><div class="seo-keyword-table"><div class="seo-keyword-head"><span>Keyword</span><span>Position</span><span>Change</span></div>@forelse($keywords as $keyword)<div class="seo-keyword-row"><span>{{ $keyword['keyword'] }}<small>{{ $keyword['target'] ?? 'No target URL' }}</small></span><b>{{ $keyword['position'] ?? '—' }}</b><strong>{{ ($keyword['change'] ?? null) !== null ? $keyword['change'] : '—' }}</strong></div>@empty<p class="seo-empty">Add your first target keyword.</p>@endforelse</div></section></div>
    @elseif($tab === 'content')
        <div class="seo-tab-grid"><section class="seo-card seo-tab-card"><div class="seo-card-heading"><div><h2>Content Manager</h2><p>Every open issue is linked to a tracked page and can be fixed or resolved with an audit trail.</p></div><form method="post" action="{{ route('admin.seo.audit') }}">@csrf<button class="seo-button" type="submit">Run SEO Audit</button></form></div><div class="seo-table-wrap"><table class="seo-table"><thead><tr><th>Page</th><th>Issue</th><th>Severity</th><th>Details</th><th>Action</th></tr></thead><tbody>@forelse($issues as $issue)<tr><td><strong>{{ $issue['path'] }}</strong><small>{{ str($issue['source_type'])->headline() }}</small></td><td>{{ $issue['title'] }}</td><td><span class="seo-severity seo-severity-{{ $issue['severity'] }}">{{ str($issue['severity'])->headline() }}</span></td><td>{{ $issue['details'] }}</td><td class="seo-row-actions">@if($issue['issue_type'] !== 'broken-internal-link')<form method="post" action="{{ route('admin.seo.issue.fix', [$issue['source_type'], $issue['source_id'], $issue['issue_type']]) }}">@csrf<button type="submit">Fix</button></form>@endif @if($issue['persistent'] && isset($issue['uuid']))<form method="post" action="{{ route('admin.seo.issue.resolve', $issue['uuid']) }}">@csrf<button type="submit">{{ $issue['issue_type'] === 'broken-internal-link' ? 'Review' : 'Resolve' }}</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="seo-empty">No open issues. Your tracked content is clear.</td></tr>@endforelse</tbody></table></div></section><section class="seo-card seo-tab-card"><div class="seo-card-heading"><h2>Suggestions</h2></div><div class="seo-suggestion-list"><p><b>{{ $metaStatus['missing'] }}</b> pages need a title or description.</p><p><b>{{ $issues->where('issue_type', 'missing-h1')->count() }}</b> pages need an H1 heading.</p><p><b>{{ $metaStatus['duplicate'] }}</b> duplicate title records detected.</p><p><b>{{ $summary['redirects'] }}</b> active 301 redirects are recorded.</p></div></section></div>
    @elseif($tab === 'redirects')
        <section class="seo-card seo-tab-card"><div class="seo-card-heading"><div><h2>Redirect Manager</h2><p>Create controlled redirects for renamed or retired website paths.</p></div></div><form method="post" action="{{ route('admin.seo.redirects.store') }}" class="seo-inline-form">@csrf<input name="from_path" placeholder="/old-path" required><input name="to_path" placeholder="/new-path or https://..." required><select name="status_code"><option value="301">301 permanent</option><option value="302">302 temporary</option><option value="307">307 temporary</option><option value="308">308 permanent</option></select><label class="seo-checkbox"><input type="checkbox" name="active" value="1" checked> Active</label><button class="seo-button" type="submit">Add Redirect</button></form><div class="seo-table-wrap"><table class="seo-table"><thead><tr><th>From</th><th>To</th><th>Status</th><th>State</th><th>Action</th></tr></thead><tbody>@forelse($redirects as $redirect)<tr><td><strong>{{ $redirect->from_path }}</strong><small>{{ $redirect->uuid }}</small></td><td>{{ $redirect->to_path }}</td><td>{{ $redirect->status_code }}</td><td><span class="seo-status">{{ $redirect->active ? 'Active' : 'Paused' }}</span></td><td><form method="post" action="{{ route('admin.seo.redirects.destroy', $redirect) }}">@csrf @method('DELETE')<button class="seo-text-button is-danger" type="submit">Delete</button></form></td></tr>@empty<tr><td colspan="5" class="seo-empty">No redirects recorded yet.</td></tr>@endforelse</tbody></table></div>{{ $redirects->links() }}</section>
    @elseif($tab === 'sitemap')
        <div class="seo-tab-grid"><section class="seo-card seo-tab-card"><div class="seo-card-heading"><div><h2>XML Sitemap</h2><p>Generate a fresh sitemap from current indexable pages, products and categories.</p></div><form method="post" action="{{ route('admin.seo.sitemap.generate') }}">@csrf<button class="seo-button" type="submit">Generate Sitemap</button></form></div><div class="seo-tool-status"><span class="seo-tool-icon">⇩</span><div><b>{{ $summary['indexed_pages'] }} indexable URLs</b><small>{{ $summary['sitemap_generated_at'] ? 'Last generated '.$summary['sitemap_generated_at'] : 'The sitemap has not been generated in this environment.' }}</small></div><a href="{{ route('seo.sitemap') }}" target="_blank" rel="noreferrer">View XML →</a></div></section><section class="seo-card seo-tab-card"><div class="seo-card-heading"><h2>Submission URL</h2></div><code>{{ route('seo.sitemap') }}</code><p class="seo-muted">Add this URL to your search engine webmaster tools after confirming the public domain.</p></section></div>
    @elseif($tab === 'robots')
        <section class="seo-card seo-tab-card"><div class="seo-card-heading"><div><h2>Robots &amp; Indexing</h2><p>Control crawler access without exposing admin, account or checkout routes.</p></div><a class="seo-inline-action" href="{{ route('seo.robots') }}" target="_blank" rel="noreferrer">View public robots.txt <span>↗</span></a></div><form method="post" action="{{ route('admin.seo.robots.update') }}" class="seo-settings-form">@csrf<label for="robots-txt">robots.txt contents</label><textarea id="robots-txt" name="robots_txt" rows="16" spellcheck="false">{{ $robots }}</textarea><button class="seo-button" type="submit">Save Robots Rules</button></form></section>
    @elseif($tab === 'schema')
        <section class="seo-card seo-tab-card"><div class="seo-card-heading"><div><h2>Schema Generator</h2><p>Maintain the Organization structured data used for Emerald Rozalia Limited.</p></div></div><form method="post" action="{{ route('admin.seo.schema.update') }}" class="seo-settings-form">@csrf<label for="schema-json">JSON-LD schema</label><textarea id="schema-json" name="schema_json" rows="22" spellcheck="false">{{ $schemaJson }}</textarea><button class="seo-button" type="submit">Save Structured Data</button></form><div class="seo-schema-note"><b>Validation boundary</b><span>Only valid JSON is saved. Publishing remains server-controlled and every change is written to the audit log.</span></div></section>
    @endif
</div>
@endsection
