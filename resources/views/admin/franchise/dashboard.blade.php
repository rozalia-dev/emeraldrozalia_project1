@extends('layouts.admin')

@section('title', 'Franchise Dashboard')

@push('styles')
<link rel="stylesheet" href="/css/franchise-management.css?v=20260910-1">
<link rel="stylesheet" href="/css/franchise-batch13.css?v=20260913-batch13">
@endpush

@section('content')
<script>document.body.classList.add('franchise-management-page')</script>
<div class="fm-page fm-dashboard-page">
    <section class="fm-frame">
        <header class="fm-header">
            <div>
                <div class="fm-kicker">Project 1 Control Panel (cPanel) <span>›</span> Franchise Management</div>
                <h1>Franchise Dashboard</h1>
                <p>Live pipeline, Store Setup readiness, retail locations and operational follow-through.</p>
            </div>
            <div class="fm-header-right">
                <div class="fm-breadcrumb">Home <span>›</span> Franchise Management <span>›</span> <b>Dashboard</b></div>
                <div class="fm-date-card"><x-icon name="calendar" size="22" /><span><small>Today</small><strong>{{ now()->format('l, d F Y') }}</strong><b>{{ now()->format('h:i A') }}</b></span></div>
            </div>
        </header>

        <div class="fm-metrics fm-dashboard-metrics">
            @foreach($metrics as $metric)
                <a class="fm-metric fm-metric-link" href="{{ $metric['href'] }}" aria-label="Open {{ strtolower($metric['label']) }}: {{ number_format($metric['value']) }}">
                    <span class="fm-metric-icon tone-{{ $metric['tone'] }}"><x-icon name="{{ $metric['icon'] }}" size="24" /></span>
                    <div><small>{{ strtoupper($metric['label']) }}</small><strong>{{ number_format($metric['value']) }}</strong><em>{{ $metric['sub'] }}</em></div>
                </a>
            @endforeach
        </div>

        <section class="fm-dashboard-card fm-operational-card">
            <header class="fm-dashboard-card-heading">
                <div><small>OPERATIONS AT A GLANCE</small><h2>Cross-domain follow-through</h2><p>Live signals from territories, agreements, training, renewals, communications and franchise orders.</p></div>
            </header>
            <div class="fm-operational-grid">
                @foreach($operationalMetrics as $metric)
                    <a class="fm-operational-card-link fm-metric-link" href="{{ $metric['href'] }}" aria-label="Open {{ strtolower($metric['label']) }}: {{ number_format($metric['value']) }}">
                        <span class="fm-metric-icon tone-{{ $metric['tone'] }}"><x-icon name="{{ $metric['icon'] }}" size="20" /></span>
                        <span><small>{{ strtoupper($metric['label']) }}</small><strong>{{ number_format($metric['value']) }}</strong><em>{{ $metric['sub'] }}</em></span>
                        <x-icon name="arrow-right" size="13" />
                    </a>
                @endforeach
            </div>
        </section>

        <div class="fm-dashboard-grid">
            <main class="fm-dashboard-main">
                <section class="fm-dashboard-card">
                    <header class="fm-dashboard-card-heading">
                        <div><small>APPLICATION PIPELINE</small><h2>Franchise lifecycle</h2><p>Every count opens the matching Applications &amp; Leads filter.</p></div>
                        <a class="fm-secondary" href="{{ route('admin.franchise.page', ['section' => 'franchise-applications']) }}">Open applications <x-icon name="arrow-right" size="13" /></a>
                    </header>
                    <div class="fm-pipeline-grid">
                        @foreach($pipeline as $stage)
                            <a class="fm-pipeline-stage fm-pipeline-stage--{{ str($stage['status'])->slug() }}" href="{{ $stage['href'] }}" aria-label="Open {{ $stage['label'] }}: {{ number_format($stage['count']) }}">
                                <span>{{ $stage['label'] }}</span><strong>{{ number_format($stage['count']) }}</strong><i><x-icon name="arrow-right" size="13" /></i>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="fm-dashboard-card">
                    <header class="fm-dashboard-card-heading">
                        <div><small>STORE SETUP CONTROL</small><h2>Readiness milestones</h2><p>Owners, due dates and checklist progress from the same Store Setup source.</p></div>
                        <a class="fm-primary" href="{{ route('admin.franchise.store-setup') }}"><x-icon name="plus" size="13" /> Add Store Setup</a>
                    </header>
                    <div class="fm-dashboard-table-wrap">
                        <table class="fm-dashboard-table">
                            <thead><tr><th>STORE / APPLICATION</th><th>STATUS</th><th>PROGRESS</th><th>DUE</th><th class="fm-dashboard-action-head">ACTION</th></tr></thead>
                            <tbody>
                            @forelse($setupItems as $item)
                                <tr>
                                    <td><strong>{{ $item['store_name'] }}</strong><small>{{ $item['application'] }} · {{ $item['territory'] }}</small></td>
                                    <td><span class="fm-status fm-status-{{ str($item['status'])->slug() }}">{{ $item['status_label'] }}</span></td>
                                    <td><div class="fm-progress"><span><i style="width: {{ $item['progress'] }}%"></i></span><b>{{ $item['progress_label'] }}</b></div></td>
                                    <td class="{{ $item['overdue'] ? 'fm-overdue' : '' }}">{{ $item['due_on'] }}</td>
                                    <td class="fm-dashboard-actions">
                                        <a href="{{ route('admin.franchise.store-setup.edit', ['milestone' => $item['uuid']]) }}">Open</a>
                                        <details class="fm-row-menu">
                                            <summary title="Store Setup actions" aria-label="Store Setup actions"><x-icon name="dots" size="15" /></summary>
                                            <div>
                                                @foreach($item['actions'] as $action => $label)
                                                    <form method="post" action="{{ route('admin.franchise.store-setup.action', ['milestone' => $item['uuid'], 'action' => $action]) }}" onsubmit="return confirm('{{ $label }} this milestone?')">@csrf<button type="submit">{{ $label }}</button></form>
                                                @endforeach
                                                <form method="post" action="{{ route('admin.franchise.store-setup.trash', ['milestone' => $item['uuid']]) }}" onsubmit="return confirm('Move this Store Setup milestone to trash?')">@csrf @method('DELETE')<button type="submit" class="is-danger">Move to trash</button></form>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="fm-empty">No Store Setup milestones exist yet. Use <a href="{{ route('admin.franchise.store-setup') }}">Add Store Setup</a> to create the first checklist.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="fm-dashboard-card">
                    <header class="fm-dashboard-card-heading">
                        <div><small>RETAIL NETWORK</small><h2>Active stores</h2><p>Operating locations link directly to their management view.</p></div>
                        <a class="fm-secondary" href="{{ route('admin.franchise.page', ['section' => 'franchise-retail-stores', 'tab' => 'active']) }}">View all stores <x-icon name="arrow-right" size="13" /></a>
                    </header>
                    <div class="fm-dashboard-store-grid">
                        @forelse($activeStores as $store)
                            <a class="fm-dashboard-store-card" href="{{ $store['href'] }}">
                                <span class="fm-store-code">{{ $store['code'] }}</span>
                                <strong>{{ $store['name'] }}</strong>
                                <small>{{ $store['territory'] }} · Opened {{ $store['opened_on'] }}</small>
                                <b>{{ $store['monthly_sales'] }} <em>monthly sales</em></b>
                            </a>
                        @empty
                            <p class="fm-empty fm-dashboard-empty">No active stores are recorded. Open <a href="{{ route('admin.franchise.page', ['section' => 'franchise-retail-stores']) }}">Franchise Retail Stores</a> to add or review a location.</p>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="fm-side-rail">
                <section class="fm-side-card fm-purpose">
                    <h2><x-icon name="help" size="17" /> PAGE PURPOSE</h2>
                    <p>One operational view from franchise lead through onboarding, Store Setup, retail operations and renewal. Counts are live queries and every card is a drill-down.</p>
                </section>
                <section class="fm-side-card">
                    <h2><x-icon name="briefcase" size="17" /> KEY FEATURES</h2>
                    <ul class="fm-dashboard-links">
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'franchise-applications']) }}">Applications &amp; Leads <x-icon name="arrow-right" size="12" /></a></li>
                        <li><a href="{{ route('admin.franchise.store-setup') }}">Store Setup <x-icon name="arrow-right" size="12" /></a></li>
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'franchise-territories']) }}">Territories <x-icon name="arrow-right" size="12" /></a></li>
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'franchise-agreements']) }}">Agreements <x-icon name="arrow-right" size="12" /></a></li>
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'renewals']) }}">Renewals <x-icon name="arrow-right" size="12" /></a></li>
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'franchise-reports']) }}">Franchise Reports <x-icon name="arrow-right" size="12" /></a></li>
                    </ul>
                </section>
                <section class="fm-side-card">
                    <h2><x-icon name="users" size="17" /> RECENT APPLICATIONS</h2>
                    <ul class="fm-recent-list">
                        @forelse($recentApplications as $application)
                            <li><a href="{{ $application['href'] }}"><strong>{{ $application['name'] }}</strong><span>{{ $application['territory'] }} · {{ $application['created_at'] }}</span><b class="fm-status fm-status-{{ str($application['status'])->slug() }}">{{ $application['status_label'] }}</b></a></li>
                        @empty
                            <li class="fm-side-empty">No applications recorded yet.</li>
                        @endforelse
                    </ul>
                </section>
                <section class="fm-side-card">
                    <h2>WORKFLOW STATUS</h2>
                    <ul class="fm-status-list">
                        <li><i class="status-dot status-dot-new"></i><b>Lead</b><span>New enquiry</span></li>
                        <li><i class="status-dot status-dot-under-review"></i><b>Review</b><span>Due diligence</span></li>
                        <li><i class="status-dot status-dot-approved"></i><b>Approved</b><span>Ready to onboard</span></li>
                        <li><i class="status-dot status-dot-onboarding"></i><b>Setup</b><span>Store readiness</span></li>
                        <li><i class="status-dot status-dot-converted"></i><b>Active</b><span>Operating partner</span></li>
                    </ul>
                </section>
            </aside>
        </div>
    </section>
</div>
@endsection
