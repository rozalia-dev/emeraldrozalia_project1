@extends('layouts.admin')

@section('title', 'Territories')

@push('styles')
<link rel="stylesheet" href="/css/franchise-management.css?v=20260910-1">
<link rel="stylesheet" href="/css/franchise-territories.css?v=20260910-1">
@endpush

@push('scripts')
<script src="/js/franchise-management.js?v=20260910-1"></script>
@endpush

@section('content')
<script>document.body.classList.add('franchise-management-page','franchise-territories-page')</script>
<div class="fm-page ft-page" data-franchise-page>
    <section class="fm-frame ft-frame">
        <header class="fm-header ft-header">
            <div>
                <h1>Territories</h1>
                <p>Manage territories, coverage areas and assignment.</p>
            </div>
            <div class="fm-breadcrumb">Home <span>›</span> Franchise Management <span>›</span> <b>Territories</b></div>
        </header>

        <div class="fm-metrics ft-metrics">
            @foreach($metrics as $metric)
                <article class="fm-metric">
                    <span class="fm-metric-icon tone-{{ $metric['tone'] }}"><x-icon name="{{ $metric['icon'] }}" size="24" /></span>
                    <div><small>{{ strtoupper($metric['label']) }}</small><strong>{{ $metric['value'] }}</strong><em>{{ $metric['sub'] }}</em></div>
                </article>
            @endforeach
        </div>

        <div class="ft-layout">
            <main class="ft-main">
                <section class="fm-table-card ft-table-card">
                    <div class="fm-tabs-row ft-tabs-row">
                        <nav class="fm-tabs" aria-label="Territory views">
                            @foreach(['all'=>'All Territories','assigned'=>'Assigned','unassigned'=>'Unassigned','country'=>'By Country','region'=>'By Region'] as $tabKey => $tabLabel)
                                @php $query = array_filter(array_merge(request()->except('page'), ['tab' => $tabKey])); @endphp
                                <a class="{{ $tab === $tabKey ? 'active' : '' }}" href="{{ route('admin.franchise.territories') }}?{{ http_build_query($query) }}">{{ $tabLabel }}</a>
                            @endforeach
                        </nav>
                        <div class="fm-toolbar ft-toolbar">
                            <form method="get" action="{{ route('admin.franchise.territories') }}" class="fm-search-form">
                                <input type="hidden" name="tab" value="{{ $tab }}">
                                <label class="fm-search"><input type="search" name="q" value="{{ $search }}" placeholder="Search territory, region, country..."><x-icon name="search" size="15" /></label>
                                <select name="status" aria-label="Filter territory status" onchange="this.form.submit()">
                                    <option value="">All statuses</option>
                                    @foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach
                                </select>
                                <button type="submit" class="fm-filter"><x-icon name="filter" size="14" /> Filters</button>
                            </form>
                            <a class="fm-export ft-export" href="{{ route('admin.franchise.territories.export', request()->query()) }}"><x-icon name="download" size="14" /> Export</a>
                            <button type="button" class="fm-primary" data-fm-create><x-icon name="plus" size="14" /> Add Territory</button>
                        </div>
                    </div>

                    <div class="ft-table-map">
                        <div class="ft-table-side">
                            <div class="fm-table-wrap">
                                <table class="fm-table ft-table">
                                    <thead><tr><th>TERRITORY ID</th><th>TERRITORY NAME</th><th>COUNTRY / REGION</th><th>STATUS</th><th>ASSIGNED TO</th><th>STORES</th><th>FRANCHISEES</th><th>ACTION</th></tr></thead>
                                    <tbody>
                                    @forelse($records as $row)
                                        <tr>
                                            <td><code>{{ $row['reference'] }}</code></td>
                                            <td><strong>{{ $row['name'] }}</strong></td>
                                            <td><span class="ft-country"><i>{{ strtoupper($row['country']) === 'IRELAND' ? '🇮🇪' : (str_contains(strtolower($row['country']), 'united kingdom') ? '🇬🇧' : '🌍') }}</i><span><b>{{ $row['country'] }}</b>@if($row['region'])<small>{{ $row['region'] }}</small>@endif</span></span></td>
                                            <td><span class="fm-status fm-status-{{ str($row['status'])->slug() }}">{{ $row['status_label'] }}</span></td>
                                            <td><strong>{{ $row['assigned_to'] }}</strong>@if($row['assigned_code'])<small>{{ $row['assigned_code'] }}</small>@endif</td>
                                            <td>{{ number_format($row['stores']) }}</td>
                                            <td>{{ number_format($row['franchisees']) }}</td>
                                            <td class="fm-actions">
                                                <button type="button" title="View" data-fm-view="{{ base64_encode(json_encode($row['edit'], JSON_UNESCAPED_UNICODE)) }}"><x-icon name="eye" size="15" /></button>
                                                <button type="button" title="Edit" data-fm-edit="{{ base64_encode(json_encode($row['edit'], JSON_UNESCAPED_UNICODE)) }}" data-id="{{ $row['id'] }}"><x-icon name="pencil" size="15" /></button>
                                                <form method="post" action="{{ route('admin.franchise.territories.destroy', $row['id']) }}" onsubmit="return confirm('Delete this territory?')">@csrf @method('DELETE')<button type="submit" title="Delete"><x-icon name="trash" size="15" /></button></form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="fm-empty">No territories match this view. Use <b>Add Territory</b> to create the first territory.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <footer class="fm-table-footer">
                                <span>Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} results</span>
                                @if($records->lastPage() > 1)
                                    <nav class="fm-pagination" aria-label="Pagination">
                                        <a class="{{ $records->onFirstPage() ? 'disabled' : '' }}" href="{{ $records->previousPageUrl() ?: '#' }}">‹</a>
                                        @for($page=max(1,$records->currentPage()-2);$page<=min($records->lastPage(),$records->currentPage()+3);$page++)<a class="{{ $records->currentPage()===$page?'active':'' }}" href="{{ $records->url($page) }}">{{ $page }}</a>@endfor
                                        <a class="{{ $records->hasMorePages() ? '' : 'disabled' }}" href="{{ $records->nextPageUrl() ?: '#' }}">›</a>
                                    </nav>
                                @endif
                            </footer>
                        </div>

                        <aside class="ft-map-card">
                            <h2>TERRITORY COVERAGE MAP</h2>
                            <div class="ft-map-wrap" aria-label="Ireland territory coverage visualization">
                                <svg viewBox="0 0 260 330" role="img" aria-label="Stylized Ireland territory map">
                                    <defs><linearGradient id="islandFill" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2f8547"/><stop offset="1" stop-color="#9acb86"/></linearGradient></defs>
                                    <path class="ft-island" d="M135 16c-13 8-23 22-39 24-17 2-30 6-39 19-7 10-5 22-16 29-14 9-25 18-24 35 1 14 12 20 7 34-5 16-20 20-18 39 2 16 16 20 13 35-3 14-14 25-7 40 8 18 30 14 40 24 10 9 14 29 31 31 13 0 17-15 29-18 13-4 27 8 41 2 14-6 10-25 20-35 12-12 32-10 35-30 3-17-13-27-12-42 1-16 20-28 13-46-5-17-24-20-30-34-7-16 5-32-1-46-7-16-28-11-43-22-13-10-16-31-34-37z" fill="url(#islandFill)" stroke="#e5eee3" stroke-width="4"/>
                                    <path d="M91 43l12 45-33 25 31 27-48 29 50 29-35 36 42 16-7 42M138 35l-12 44 34 23-35 33 46 34-42 31 35 34-30 46M44 92l51 3 49 26 43-11M28 151l49 14 55-10 53 22M25 219l55-6 53 18 48-10M54 275l51-17 45 19" fill="none" stroke="#e8f2e7" stroke-width="2" opacity=".95"/>
                                    <path d="M177 48c19 11 32 28 35 51l-12 24-9-24-23-14zM191 129l25 18-4 38-20 5-13-31zM177 211l26 18-10 35-22 15-14-32z" fill="#d9dcdd" stroke="#f2f3f3" stroke-width="2"/>
                                </svg>
                            </div>
                            <div class="ft-map-legend"><span><i class="assigned"></i>Assigned Territories ({{ $assigned }})</span><span><i class="unassigned"></i>Unassigned Territories ({{ $unassigned }})</span><span><i class="unavailable"></i>Not Available ({{ $notAvailable }})</span></div>
                            <div class="ft-map-summary"><h3>MAP SUMMARY</h3>@forelse($countrySummary as $item)<div><span>{{ $item['country'] }}</span><b>{{ number_format($item['coverage'],1) }}%</b></div>@empty<div><span>Ireland</span><b>{{ number_format($coverage,1) }}%</b></div>@endforelse<div class="total"><span>Total Coverage</span><b>{{ number_format($coverage,1) }}%</b></div></div>
                        </aside>
                    </div>
                </section>
            </main>

            <aside class="fm-side-rail ft-side-rail">
                <section class="fm-side-card fm-purpose"><h2><x-icon name="help" size="17" /> PAGE PURPOSE</h2><p>Define and manage all operational territories. Assign territories to franchisees and monitor coverage across regions.</p></section>
                <section class="fm-side-card"><h2><x-icon name="briefcase" size="17" /> KEY FEATURES</h2><ul class="fm-check-list"><li><x-icon name="check" size="13" />Create and manage territories</li><li><x-icon name="check" size="13" />Country, region and area hierarchy</li><li><x-icon name="check" size="13" />Assign territories to franchisees</li><li><x-icon name="check" size="13" />Track coverage and performance</li><li><x-icon name="check" size="13" />Visual territory mapping</li><li><x-icon name="check" size="13" />Territory status and history</li><li><x-icon name="check" size="13" />Export territory reports</li></ul></section>
                <section class="fm-side-card"><h2>TERRITORY STATUS</h2><ul class="fm-status-list"><li><i class="status-dot status-dot-active"></i><b>Assigned</b><span>Territory is assigned to a franchisee</span></li><li><i class="status-dot status-dot-under-review"></i><b>Unassigned</b><span>Territory is available for assignment</span></li><li><i class="status-dot"></i><b>Not Available</b><span>Outside operational scope</span></li></ul></section>
                <section class="fm-side-card"><h2><x-icon name="globe" size="17" /> INTEGRATION</h2><p class="fm-side-note">Territories integrate with:</p><ul class="fm-links"><li>Franchisees</li><li>Retail Stores</li><li>Performance &amp; Targets</li><li>Reports</li></ul></section>
            </aside>
        </div>
    </section>

    <section class="fm-benefits ft-benefits">
        <article><span><x-icon name="refresh" size="24" /></span><div><strong>UNIFIED UUID / TRACEABILITY</strong><p>Every territory record is UUID tracked for full traceability.</p></div></article>
        <article><span><x-icon name="globe" size="24" /></span><div><strong>UNIFIED STOCK MASTER</strong><p>Real-time stock visibility from the external Unified Stock Master.</p></div></article>
        <article><span><x-icon name="briefcase" size="24" /></span><div><strong>UNIFIED FINANCE</strong><p>All financial transactions sync with the external Unified Finance system.</p></div></article>
        <article><span><x-icon name="check" size="24" /></span><div><strong>ONE PROJECT 1 CPANEL</strong><p>One system. One source of truth. No separate Franchise Portal.</p></div></article>
    </section>

    <dialog class="fm-dialog" data-fm-dialog>
        <form method="post" action="{{ route('admin.franchise.territories.store') }}" data-fm-form data-store-url="{{ route('admin.franchise.territories.store') }}" data-update-template="{{ route('admin.franchise.territories.update', '__id__') }}">
            @csrf
            <input type="hidden" name="_method" value="PATCH" data-method-override disabled>
            <header><div><small>FRANCHISE MANAGEMENT</small><h2 data-fm-dialog-title>Add Territory</h2></div><button type="button" data-fm-close aria-label="Close">×</button></header>
            <div class="fm-form-grid">
                <label>Territory ID<input name="reference" required maxlength="100" placeholder="TER-IE-01"></label>
                <label>Territory Name<input name="title" required maxlength="180" placeholder="Limerick City & County"></label>
                <label>Country<input name="country" required maxlength="100" value="Ireland"></label>
                <label>Region<input name="region" maxlength="120" placeholder="Munster"></label>
                <label>Status<select name="status" required>@foreach($statuses as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                <label>Assigned Franchisee<input name="assigned_to_name" maxlength="180" placeholder="Emerald Caps Ltd."></label>
                <label>Franchisee ID<input name="assigned_code" maxlength="100" placeholder="FRAN-IE-001"></label>
                <label>Stores<input name="stores_count" type="number" min="0" value="0" required></label>
                <label>Franchisees<input name="franchisees_count" type="number" min="0" value="0" required></label>
                <label>Coverage %<input name="coverage" type="number" min="0" max="100" step="0.1" value="0" required></label>
                <label class="fm-form-span">Notes<textarea name="notes" rows="3" maxlength="3000"></textarea></label>
            </div>
            <footer><button type="button" class="fm-secondary" data-fm-close>Cancel</button><button type="submit" class="fm-primary" data-fm-save>Save Territory</button></footer>
        </form>
    </dialog>
</div>
@endsection
