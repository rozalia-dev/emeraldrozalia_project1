@extends('layouts.admin')

@section('title', $config['title'])

@push('styles')
<link rel="stylesheet" href="/css/franchise-management.css?v=20260910-1">
<link rel="stylesheet" href="/css/franchise-batch13.css?v=20260913-batch13">
@endpush

@push('scripts')
<script src="/js/franchise-management.js?v=20260910-1"></script>
@endpush

@section('content')
<script>document.body.classList.add('franchise-management-page')</script>
<div class="fm-page" data-franchise-page>
    <section class="fm-frame">
        <header class="fm-header">
            <div>
                <div class="fm-kicker">Project 1 Control Panel (cPanel) <span>›</span> Franchise Management</div>
                <h1>{{ $config['title'] }}</h1>
                <p>{{ $config['subtitle'] }}</p>
            </div>
            <div class="fm-header-right">
                <div class="fm-breadcrumb">Home <span>›</span> Franchise Management <span>›</span> <b>{{ $config['title'] }}</b></div>
                <div class="fm-date-card"><x-icon name="calendar" size="22" /><span><small>Today</small><strong>{{ now()->format('l, d F Y') }}</strong><b>{{ now()->format('h:i A') }}</b></span></div>
            </div>
        </header>

        <div class="fm-metrics">
            @foreach($metrics as $metric)
                <article class="fm-metric">
                    <span class="fm-metric-icon tone-{{ $metric['tone'] }}"><x-icon name="{{ $metric['icon'] }}" size="24" /></span>
                    <div><small>{{ strtoupper($metric['label']) }}</small><strong>{{ $metric['value'] }}</strong><em>{{ $metric['sub'] }}</em></div>
                </article>
            @endforeach
        </div>

        <div class="fm-content-grid">
            <main class="fm-main-panel">
                <section class="fm-table-card">
                    <div class="fm-tabs-row">
                        <nav class="fm-tabs" aria-label="{{ $config['title'] }} views">
                            @foreach($config['tabs'] as $tabKey => $tabLabel)
                                @php $tabQuery = array_filter(array_merge(request()->except('page'), ['tab' => $tabKey])); @endphp
                                <a class="{{ $tab === $tabKey ? 'active' : '' }}" href="{{ route('admin.franchise.page', ['section' => $section]) }}?{{ http_build_query($tabQuery) }}">{{ $tabLabel }}</a>
                            @endforeach
                        </nav>
                        <div class="fm-toolbar">
                            <form method="get" action="{{ route('admin.franchise.page', ['section' => $section]) }}" class="fm-search-form">
                                <input type="hidden" name="tab" value="{{ $tab }}">
                                <label class="fm-search"><input type="search" name="q" value="{{ $search }}" placeholder="Search {{ strtolower($config['singular']) }}..."><x-icon name="search" size="15" /></label>
                                <select name="status" aria-label="Filter by status" onchange="this.form.submit()">
                                    <option value="">All statuses</option>
                                    @foreach($config['statuses'] as $statusKey => $statusLabel)
                                        <option value="{{ $statusKey }}" @selected($status === $statusKey)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="fm-filter"><x-icon name="filter" size="14" /> Filters</button>
                            </form>
                            <a class="fm-export" href="{{ route('admin.franchise.export', ['section' => $section] + request()->query()) }}"><x-icon name="download" size="14" /> Export</a>
                            @if($config['editable'])
                                <button type="button" class="fm-primary" data-fm-create><x-icon name="plus" size="14" /> Add {{ $config['singular'] }}</button>
                            @endif
                        </div>
                    </div>

                    <div class="fm-table-wrap">
                        <table class="fm-table">
                            <thead><tr>
                                @foreach($config['columns'] as $column)<th>{{ strtoupper($column['label']) }}</th>@endforeach
                                @if($config['editable'])<th class="fm-action-head">ACTION</th>@endif
                            </tr></thead>
                            <tbody>
                            @forelse($records as $row)
                                <tr>
                                    @foreach($config['columns'] as $column)
                                        @php $key = $column['key']; @endphp
                                        <td class="fm-cell-{{ $key }}">
                                            @if($key === 'status')
                                                <span class="fm-status fm-status-{{ str($row['status'])->slug() }}">{{ $row['status_label'] }}</span>
                                            @elseif($key === 'primary')
                                                <strong>{{ $row['primary'] }}</strong>
                                                @if($config['source'] === 'application' && filled($row['secondary']))<small>{{ $row['secondary'] }}</small>@endif
                                            @elseif($key === 'reference')
                                                <code>{{ $row[$key] }}</code>
                                            @elseif($key === 'growth')
                                                <span class="fm-growth">{{ $row[$key] }}</span>
                                            @else
                                                {{ $row[$key] ?? '—' }}
                                            @endif
                                        </td>
                                    @endforeach
                                    @if($config['editable'])
                                    <td class="fm-actions">
                                        <button type="button" title="View" data-fm-view="{{ base64_encode(json_encode($row['edit'], JSON_UNESCAPED_UNICODE)) }}"><x-icon name="eye" size="15" /></button>
                                        <button type="button" title="Edit" data-fm-edit="{{ base64_encode(json_encode($row['edit'], JSON_UNESCAPED_UNICODE)) }}" data-id="{{ $row['id'] }}"><x-icon name="pencil" size="15" /></button>
                                        @if($section === 'franchise-applications' && !empty($row['actions']))
                                            <details class="fm-row-menu">
                                                <summary title="Application actions" aria-label="Application actions"><x-icon name="dots" size="15" /></summary>
                                                <div>
                                                    @foreach($row['actions'] as $action => $label)
                                                        <form method="post" action="{{ route('admin.franchise.application.action', ['application' => $row['uuid'], 'action' => $action]) }}" onsubmit="return confirm('{{ $label }} this application?')">@csrf<button type="submit">{{ $label }}</button></form>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                        <form method="post" action="{{ route('admin.franchise.destroy', [$section, $row['id']]) }}" onsubmit="return confirm('Delete this {{ strtolower($config['singular']) }}?')">@csrf @method('DELETE')<button type="submit" title="Delete"><x-icon name="trash" size="15" /></button></form>
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($config['columns']) + ($config['editable'] ? 1 : 0) }}" class="fm-empty">No records match this view. Use <b>Add {{ $config['singular'] }}</b> to create the first record.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <footer class="fm-table-footer">
                        <span>Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} results</span>
                        @if($records->lastPage() > 1)
                        <nav class="fm-pagination" aria-label="Pagination">
                            <a class="{{ $records->onFirstPage() ? 'disabled' : '' }}" href="{{ $records->previousPageUrl() ?: '#' }}">‹</a>
                            @for($page = max(1, $records->currentPage()-2); $page <= min($records->lastPage(), $records->currentPage()+3); $page++)
                                <a class="{{ $records->currentPage() === $page ? 'active' : '' }}" href="{{ $records->url($page) }}">{{ $page }}</a>
                            @endfor
                            <a class="{{ $records->hasMorePages() ? '' : 'disabled' }}" href="{{ $records->nextPageUrl() ?: '#' }}">›</a>
                        </nav>
                        @endif
                    </footer>
                </section>
            </main>

            <aside class="fm-side-rail">
                <section class="fm-side-card fm-purpose">
                    <h2><x-icon name="help" size="17" /> PAGE PURPOSE</h2>
                    <p>{{ $config['purpose'] }}</p>
                </section>
                <section class="fm-side-card">
                    <h2><x-icon name="briefcase" size="17" /> KEY FEATURES</h2>
                    <ul class="fm-check-list">@foreach($config['features'] as $feature)<li><x-icon name="check" size="13" />{{ $feature }}</li>@endforeach</ul>
                </section>
                <section class="fm-side-card">
                    <h2>STATUS DEFINITIONS</h2>
                    <ul class="fm-status-list">@foreach(array_slice($config['statuses'], 0, 7, true) as $statusKey => $statusLabel)<li><i class="status-dot status-dot-{{ str($statusKey)->slug() }}"></i><b>{{ $statusLabel }}</b><span>{{ str($statusLabel)->lower() }} record</span></li>@endforeach</ul>
                </section>
                <section class="fm-side-card">
                    <h2>INTEGRATION &amp; LINKS</h2>
                    <p class="fm-side-note">{{ $config['title'] }} integrates with:</p>
                    <ul class="fm-links">@foreach($config['integrations'] as $integration)<li>{{ $integration }}</li>@endforeach</ul>
                </section>
            </aside>
        </div>
    </section>

    <section class="fm-benefits">
        @foreach($config['benefits'] as $index => $benefit)
            <article><span><x-icon name="{{ ['refresh','globe','briefcase','check'][$index % 4] }}" size="24" /></span><div><strong>{{ strtoupper($benefit[0]) }}</strong><p>{{ $benefit[1] }}</p></div></article>
        @endforeach
    </section>

    @if($config['editable'])
    <dialog class="fm-dialog" data-fm-dialog>
        <form method="post" action="{{ route('admin.franchise.store', $section) }}" data-fm-form data-store-url="{{ route('admin.franchise.store', $section) }}" data-update-template="{{ route('admin.franchise.update', [$section, '__id__']) }}">
            @csrf
            <input type="hidden" name="_method" value="PATCH" data-method-override disabled>
            <header><div><small>FRANCHISE MANAGEMENT</small><h2 data-fm-dialog-title>Add {{ $config['singular'] }}</h2></div><button type="button" data-fm-close aria-label="Close">×</button></header>
            <div class="fm-form-grid">
                @if($config['source'] === 'application')
                    <label>Applicant / Company<input name="applicant_name" required maxlength="180"></label>
                    <label>Email<input name="email" type="email" required maxlength="180"></label>
                    <label>Phone<input name="phone" maxlength="60"></label>
                    <label>Territory<input name="territory" required maxlength="180"></label>
                    <label>Preferred Location<input name="preferred_location" maxlength="180"></label>
                    <label>Investment Range<input name="investment_range" maxlength="180"></label>
                    <label>Source<input name="source" maxlength="100" placeholder="Website, Referral, Exhibition..."></label>
                    <label>Assigned To<select name="assigned_to"><option value="">Unassigned</option>@foreach($admins as $admin)<option value="{{ $admin->id }}">{{ $admin->name }}</option>@endforeach</select></label>
                @elseif($config['source'] === 'store')
                    <label>Store Code<input name="code" required maxlength="100"></label>
                    <label>Store Name<input name="name" required maxlength="180"></label>
                    <label>Franchisee<input name="franchisee_name" maxlength="180"></label>
                    <label>Territory<input name="territory" required maxlength="180"></label>
                    <label>Store Manager<input name="manager_name" maxlength="180"></label>
                    <label>Manager Email<input name="manager_email" type="email" maxlength="180"></label>
                    <label>Opened Date<input name="opened_at" type="date"></label>
                    <label>Monthly Sales (€)<input name="monthly_sales" type="number" min="0" step="0.01"></label>
                @else
                    <label>Title / Name<input name="title" required maxlength="180"></label>
                    <label>Reference<input name="reference" maxlength="100"></label>
                    <label>Secondary / Contact<input name="secondary" maxlength="180"></label>
                    <label>Territory / Audience<input name="territory" maxlength="180"></label>
                    <label>Type<input name="record_type" maxlength="120"></label>
                    <label>Source / Campaign<input name="source" maxlength="120"></label>
                    <label>Assigned / Owner<input name="assigned_to_name" maxlength="180"></label>
                    <label>Amount / Value (€)<input name="amount" type="number" min="0" step="0.01"></label>
                    <label>Start / Record Date<input name="record_date" type="date"></label>
                    <label>End / Expiry Date<input name="end_date" type="date"></label>
                    <label>KPI / Display Value<input name="value" maxlength="120" placeholder="e.g. 87%, 6 stores, 24 days"></label>
                    <label>Growth / Completion<input name="growth" maxlength="120" placeholder="e.g. +12.4%, 78%"></label>
                    <label class="fm-form-span">Notes<textarea name="notes" rows="3" maxlength="3000"></textarea></label>
                @endif
                <label>Status<select name="status" required>@foreach($config['statuses'] as $statusKey => $statusLabel)<option value="{{ $statusKey }}">{{ $statusLabel }}</option>@endforeach</select></label>
            </div>
            <footer><button type="button" class="fm-secondary" data-fm-close>Cancel</button><button type="submit" class="fm-primary" data-fm-save>Save {{ $config['singular'] }}</button></footer>
        </form>
    </dialog>
    @endif
</div>
@endsection
