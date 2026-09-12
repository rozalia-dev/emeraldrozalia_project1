@extends('layouts.admin')

@section('title', $title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reports-analytics-reference.css?v=20260912-1') }}">
@endpush

@php
    $query = [
        'q' => $filters['q'], 'from' => $filters['from_value'], 'to' => $filters['to_value'],
        'tab' => $activeTab, 'order_type' => $filters['order_type'], 'channel' => $filters['channel'],
        'status' => $filters['status'], 'segment' => $filters['segment'], 'customer_type' => $filters['customer_type'],
    ];
    $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== 'all');
    $tabUrl = static fn (string $tab): string => route('admin.reports.'.$report, array_merge($query, ['tab' => $tab]));
    $exportQuery = array_merge($query, ['name' => $title, 'analytics' => $report, 'format' => 'csv']);
    $donut = static function (array $rows): string {
        $cursor = 0.0; $stops = [];
        foreach ($rows as $row) {
            $value = (float) str_replace([',', '€'], '', (string) ($row['value'] ?? 0));
            $share = (float) ($row['share'] ?? 0);
            if ($share <= 0) $share = 0;
            if ($share === 0 && $value > 0) $share = 100;
            $stops[] = ($row['color'] ?? '#087943').' '.$cursor.'% '.($cursor + $share).'%';
            $cursor += $share;
        }
        if ($cursor < 100) $stops[] = '#edf2ef '.$cursor.'% 100%';
        return 'conic-gradient('.implode(',', $stops).')';
    };
    $points = static function (array $rows, string $key = 'value'): string {
        $values = array_map(static fn ($row): float => (float) str_replace([',', '€', '%'], '', (string) ($row[$key] ?? 0)), $rows);
        $max = max(1, ...$values); $count = max(1, count($values) - 1); $coordinates = [];
        foreach ($values as $index => $value) $coordinates[] = round(($index / $count) * 100, 2).','.round(100 - (($value / $max) * 82 + 8), 2);
        return implode(' ', $coordinates);
    };
    $valueNumber = static fn ($value): string => number_format((float) str_replace(',', '', (string) $value));
@endphp

@section('content')
<div class="reports-analytics-page reports-analytics--{{ $report }}" data-report-analytics-root>
    <div class="ra-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Project 1 Control Panel (cPanel)</a><span>›</span><span>{{ $source }}</span><span>›</span><strong>{{ $title }}</strong>
    </div>

    <header class="ra-page-heading">
        <div class="ra-heading-copy">
            <span class="ra-heading-icon"><x-icon name="{{ $icon }}" size="22" /></span>
            <div><h1>{{ $title }}</h1><p>{{ $subtitle }}</p></div>
        </div>
        <div class="ra-heading-actions">
            <div class="ra-date-card"><x-icon name="calendar" size="19" /><span><strong>{{ $filters['from_label'] }} - {{ $filters['to_label'] }}</strong><small>vs {{ $filters['compare_label'] }}</small></span><x-icon name="chevron-down" size="14" /></div>
            <button class="ra-button ra-button--light" type="button" data-ra-filter-toggle><x-icon name="filter" size="14" /> Filters</button>
            <a class="ra-button ra-button--light" href="{{ route('admin.reports.export', $exportQuery) }}"><x-icon name="download" size="14" /> Export</a>
            <form method="post" action="{{ route('admin.reports.run') }}" class="ra-run-form">@csrf<input type="hidden" name="report" value="{{ $title }}"><input type="hidden" name="module" value="{{ $source }}"><button class="ra-button ra-button--green" type="submit"><x-icon name="play" size="14" /> Run Report</button></form>
        </div>
    </header>

    <div class="ra-data-note {{ $isPreview ? 'is-preview' : 'is-live' }}"><span></span>{{ $dataNote }}</div>

    <div class="ra-layout">
        <div class="ra-content">
            <section class="ra-kpi-grid ra-kpi-grid--{{ count($metrics) }}">
                @foreach($metrics as $metric)
                    <article class="ra-kpi ra-kpi--{{ $metric['tone'] }}">
                        <div class="ra-kpi-top"><span class="ra-kpi-icon"><x-icon name="{{ $metric['icon'] }}" size="18" /></span><span>{{ $metric['label'] }}</span></div>
                        <strong>{{ $metric['value'] }}</strong>
                        <small class="ra-kpi-change {{ $metric['change'] === '—' ? 'is-muted' : '' }}">@if($metric['change'] !== '—')<x-icon name="arrow-up" size="11" />@endif {{ $metric['change'] }} @if($metric['change'] !== '—')<em>vs previous period</em>@endif</small>
                    </article>
                @endforeach
            </section>

            <nav class="ra-tabs" aria-label="{{ $title }} sections">
                @foreach($tabs as $slug => $label)<a class="{{ $activeTab === $slug ? 'active' : '' }}" href="{{ $tabUrl($slug) }}">{{ $label }}</a>@endforeach
            </nav>

            <section class="ra-filter-panel" data-ra-filter-panel @if(request()->boolean('filters')) data-filter-open="1" @endif>
                <form method="get" action="{{ url()->current() }}">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <label>Search<input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search report data..."></label>
                    <label>From<input type="date" name="from" value="{{ $filters['from_value'] }}"></label>
                    <label>To<input type="date" name="to" value="{{ $filters['to_value'] }}"></label>
                    @if($report === 'order')
                        <label>Order type<select name="order_type">@foreach($filterOptions['order_type'] as $key => $label)<option value="{{ $key }}" @selected($filters['order_type'] === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label>Channel<select name="channel">@foreach($filterOptions['channel'] as $key => $label)<option value="{{ $key }}" @selected($filters['channel'] === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label>Status<select name="status">@foreach($filterOptions['status'] as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></label>
                    @elseif($report === 'communication')
                        <label>Channel<select name="channel">@foreach($filterOptions['channel'] as $key => $label)<option value="{{ $key }}" @selected($filters['channel'] === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label>Status<select name="status">@foreach($filterOptions['status'] as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></label>
                    @else
                        <label>Segment<select name="segment">@foreach($filterOptions['segment'] as $key => $label)<option value="{{ $key }}" @selected($filters['segment'] === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label>Customer type<select name="customer_type">@foreach($filterOptions['customer_type'] as $key => $label)<option value="{{ $key }}" @selected($filters['customer_type'] === $key)>{{ $label }}</option>@endforeach</select></label>
                    @endif
                    <div class="ra-filter-actions"><button class="ra-button ra-button--green" type="submit">Apply Filters</button><a class="ra-button ra-button--text" href="{{ url()->current() }}">Reset</a></div>
                </form>
            </section>

            @if($report === 'order')
                <div class="ra-grid ra-grid--three">
                    <article class="ra-card ra-card--span-1"><div class="ra-card-heading"><h2>Orders by Type</h2><a href="{{ $tabUrl('by-order-type') }}">View report <x-icon name="arrow-right" size="13" /></a></div><div class="ra-donut-layout"><div class="ra-donut" style="background:{{ $donut($orderTypes) }}"><strong>{{ $isPreview ? '3,856' : $valueNumber(array_sum(array_column($orderTypes, 'value'))) }}</strong><span>Total Orders</span></div><div class="ra-legend">@foreach($orderTypes as $row)<div><i style="background:{{ $row['color'] }}"></i><span>{{ $row['label'] }}</span><b>{{ $valueNumber($row['value']) }} <small>({{ $row['share'] }}%)</small></b></div>@endforeach</div></div></article>
                    <article class="ra-card ra-card--span-1"><div class="ra-card-heading"><h2>Orders Over Time</h2><select class="ra-compact-select" data-ra-period><option>Daily</option><option>Weekly</option><option>Monthly</option></select></div><div class="ra-chart"><svg viewBox="0 0 600 190" role="img" aria-label="Orders over time line chart"><g class="ra-grid-lines"><path d="M42 25H580M42 72H580M42 119H580M42 166H580" /></g><polyline class="ra-chart-area" points="42,166 {{ $points($timeSeries) }} 580,166"/><polyline class="ra-line ra-line--green" points="42,166 {{ $points($timeSeries) }}"/><polyline class="ra-line ra-line--muted" points="42,150 {{ $points($timeSeries, 'secondary') }}"/>@foreach($timeSeries as $index => $point)<text x="{{ 42 + ($index * 89.6) }}" y="184">{{ $point['label'] }}</text>@endforeach</svg><div class="ra-chart-key"><span><i class="green"></i>Current Period</span><span><i class="muted"></i>Previous Period</span></div></div></article>
                    <article class="ra-card ra-card--span-1"><div class="ra-card-heading"><h2>Orders by Channel</h2><a href="{{ $tabUrl('by-channel') }}">View report <x-icon name="arrow-right" size="13" /></a></div><div class="ra-donut-layout"><div class="ra-donut" style="background:{{ $donut($channels) }}"><strong>{{ $isPreview ? '3,856' : $valueNumber(array_sum(array_column($channels, 'value'))) }}</strong><span>Total Orders</span></div><div class="ra-legend">@foreach($channels as $row)<div><i style="background:{{ $row['color'] }}"></i><span>{{ $row['label'] }}</span><b>{{ $valueNumber($row['value']) }} <small>({{ $row['share'] }}%)</small></b></div>@endforeach</div></div></article>
                </div>
                <div class="ra-grid ra-grid--two ra-grid--summary">
                    <article class="ra-card"><div class="ra-card-heading"><h2>Order Summary by Type</h2><a href="{{ $tabUrl('by-order-type') }}">View full report <x-icon name="arrow-right" size="13" /></a></div><div class="ra-table-wrap"><table class="ra-table"><thead><tr><th>Order Type</th><th>Orders</th><th>Order Value</th><th>Avg. Value</th><th>Status</th></tr></thead><tbody>@foreach($orderSummary as $row)<tr><td><strong>{{ $row['type'] }}</strong></td><td>{{ $valueNumber($row['orders']) }}</td><td>{{ $row['value'] }}</td><td>{{ $row['avg'] }}</td><td><span class="ra-pill ra-pill--green">{{ $row['status'] }}</span></td></tr>@endforeach</tbody></table></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Order Status Overview</h2><a href="{{ $tabUrl('comparative-analysis') }}">View details <x-icon name="arrow-right" size="13" /></a></div><div class="ra-status-layout"><div class="ra-donut ra-donut--small" style="background:{{ $donut($statuses) }}"><strong>{{ $isPreview ? '3,856' : $valueNumber(array_sum(array_column($statuses, 'value'))) }}</strong><span>Total</span></div><div class="ra-legend">@foreach($statuses as $row)<div><i style="background:{{ $row['color'] }}"></i><span>{{ $row['label'] }}</span><b>{{ $valueNumber($row['value']) }} <small>({{ $row['share'] }}%)</small></b></div>@endforeach</div></div></article>
                </div>
                <div class="ra-grid ra-grid--three">
                    <article class="ra-card"><div class="ra-card-heading"><h2>Top Performing Categories</h2><a href="{{ $tabUrl('products') }}">View report <x-icon name="arrow-right" size="13" /></a></div><div class="ra-bars">@foreach($categories as $row)<div><div><span>{{ $row['label'] }}</span><b>{{ $valueNumber($row['value']) }}</b></div><i><em style="width:{{ min(100, max(3, (float) str_replace('%', '', $row['percent']))) }}%;background:{{ $row['color'] }}"></em></i></div>@endforeach</div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Returns &amp; Refunds Overview</h2><a href="{{ $tabUrl('returns-refunds') }}">View report <x-icon name="arrow-right" size="13" /></a></div><div class="ra-summary-list"><div><span>Total Returns</span><strong>{{ $isPreview ? '956' : $valueNumber($refundValue) }}</strong><small class="ra-good">↓ 8.42%</small></div><div><span>Refund Value</span><strong>{{ $refundValue }}</strong></div><div><span>Return Rate</span><strong>2.48%</strong></div><div><span>Avg. Processing Time</span><strong>2.6 Days</strong></div></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Recent Alerts</h2><a href="{{ route('admin.resource', 'alerts-notifications') }}">View all <x-icon name="arrow-right" size="13" /></a></div><div class="ra-alert-list">@foreach($alerts as $alert)<a href="{{ route('admin.resource', 'alerts-notifications') }}"><i class="ra-alert-dot ra-alert-dot--{{ $alert['tone'] }}"></i><span>{{ $alert['label'] }}</span><b>{{ $alert['value'] }}</b><x-icon name="chevron-right" size="13" /></a>@endforeach</div></article>
                </div>
                <div class="ra-grid ra-grid--two">
                    <article class="ra-card"><div class="ra-card-heading"><h2>Orders by Region</h2><a href="{{ $tabUrl('geographic-analysis') }}">View geography <x-icon name="arrow-right" size="13" /></a></div><div class="ra-region-layout"><svg class="ra-map" viewBox="0 0 260 150" aria-label="Orders by region map"><path d="M12 58 35 39l26 4 17-17 28 7 17-11 31 17 21-4 23 19 29 8 9 28-32 13-26-12-29 13-28-16-31 5-25-14-29 4z"/><path d="m84 77 21-12 18 8-10 19-25 3zM167 63l18-8 23 10-18 18-18-7z"/></svg><div class="ra-region-table">@foreach($regions as $row)<div><span>{{ $row['label'] }}</span><strong>{{ $row['value'] }}</strong><small>{{ $row['share'] }}</small></div>@endforeach</div></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Quick Actions</h2></div><div class="ra-quick-actions"><a href="{{ route('admin.reports.export', $exportQuery) }}"><x-icon name="download" size="14" /> Export Order Report (CSV)</a><a href="{{ $tabUrl('returns-refunds') }}"><x-icon name="refresh" size="14" /> Review Returns &amp; Refunds</a><a href="{{ route('admin.reports.scheduler') }}"><x-icon name="calendar" size="14" /> Schedule Order Report</a><a href="{{ route('admin.reports.history') }}"><x-icon name="clock" size="14" /> Open Report History</a></div></article>
                </div>
            @elseif($report === 'communication')
                <div class="ra-grid ra-grid--three">
                    <article class="ra-card"><div class="ra-card-heading"><h2>Conversations by Channel</h2><a href="{{ $tabUrl('channels') }}">View report <x-icon name="arrow-right" size="13" /></a></div><div class="ra-donut-layout"><div class="ra-donut" style="background:{{ $donut($channels) }}"><strong>{{ $isPreview ? '12,842' : $valueNumber(array_sum(array_column($channels, 'value'))) }}</strong><span>Total</span></div><div class="ra-legend">@foreach($channels as $row)<div><i style="background:{{ $row['color'] }}"></i><span>{{ $row['label'] }}</span><b>{{ $valueNumber($row['value']) }} <small>({{ $row['share'] }}%)</small></b></div>@endforeach</div></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Conversations Over Time</h2><select class="ra-compact-select" data-ra-period><option>Daily</option><option>Weekly</option><option>Monthly</option></select></div><div class="ra-chart"><svg viewBox="0 0 600 190" role="img" aria-label="Conversations over time line chart"><g class="ra-grid-lines"><path d="M42 25H580M42 72H580M42 119H580M42 166H580" /></g><polyline class="ra-chart-area" points="42,166 {{ $points($timeSeries) }} 580,166"/><polyline class="ra-line ra-line--green" points="42,166 {{ $points($timeSeries) }}"/>@foreach($timeSeries as $index => $point)<text x="{{ 42 + ($index * 89.6) }}" y="184">{{ $point['label'] }}</text>@endforeach</svg><div class="ra-chart-key"><span><i class="green"></i>Current Period</span><span><i class="muted"></i>Previous Period</span></div></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Conversations by Status</h2><a href="{{ $tabUrl('trends') }}">View details <x-icon name="arrow-right" size="13" /></a></div><div class="ra-donut-layout"><div class="ra-donut" style="background:{{ $donut($statuses) }}"><strong>{{ $isPreview ? '12,842' : $valueNumber(array_sum(array_column($statuses, 'value'))) }}</strong><span>Total</span></div><div class="ra-legend">@foreach($statuses as $row)<div><i style="background:{{ $row['color'] }}"></i><span>{{ $row['label'] }}</span><b>{{ $valueNumber($row['value']) }} <small>({{ $row['share'] }}%)</small></b></div>@endforeach</div></div></article>
                </div>
                <div class="ra-grid ra-grid--two">
                    <article class="ra-card"><div class="ra-card-heading"><h2>SLA Performance</h2><a href="{{ $tabUrl('sla-performance') }}">View SLA report <x-icon name="arrow-right" size="13" /></a></div><div class="ra-gauge"><div class="ra-gauge-ring"><strong>92.68%</strong><span>Compliance</span></div><div class="ra-summary-list"><div><span>Within SLA</span><strong>11,902</strong></div><div><span>Breached</span><strong>940</strong></div><div><span>Target</span><strong>90.00%</strong></div></div></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Conversations by Category</h2><a href="{{ $tabUrl('overview') }}">View details <x-icon name="arrow-right" size="13" /></a></div><div class="ra-bars">@foreach($categories as $row)<div><div><span>{{ $row['label'] }}</span><b>{{ $valueNumber($row['value']) }}</b></div><i><em style="width:{{ min(100, max(3, (float) str_replace('%', '', $row['percent']))) }}%;background:{{ $row['color'] }}"></em></i></div>@endforeach</div></article>
                </div>
                <div class="ra-grid ra-grid--two">
                    <article class="ra-card"><div class="ra-card-heading"><h2>Top Performing Agents</h2><a href="{{ $tabUrl('agents') }}">View all agents <x-icon name="arrow-right" size="13" /></a></div><div class="ra-table-wrap"><table class="ra-table"><thead><tr><th>Agent</th><th>Conversations</th><th>CSAT</th><th>SLA</th></tr></thead><tbody>@foreach($agents as $row)<tr><td><strong>{{ $row['name'] }}</strong></td><td>{{ $valueNumber($row['conversations']) }}</td><td><span class="ra-rating">★ {{ $row['csat'] }}</span></td><td><span class="ra-pill ra-pill--green">{{ $row['sla'] }}</span></td></tr>@endforeach</tbody></table></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Communications Linked To</h2><a href="{{ $tabUrl('orders') }}">View linked records <x-icon name="arrow-right" size="13" /></a></div><div class="ra-table-wrap"><table class="ra-table"><thead><tr><th>Topic</th><th>Contact</th><th>Channel</th><th>Status</th></tr></thead><tbody>@foreach($linkedRows as $row)<tr><td><strong>{{ $row['topic'] }}</strong></td><td>{{ $row['contact'] }}</td><td>{{ $row['channel'] }}</td><td><span class="ra-pill ra-pill--{{ strtolower($row['status']) === 'open' ? 'blue' : (strtolower($row['status']) === 'pending' ? 'orange' : 'green') }}">{{ $row['status'] }}</span></td></tr>@endforeach</tbody></table></div></article>
                </div>
                <div class="ra-grid ra-grid--three">
                    <article class="ra-card ra-card--span-2"><div class="ra-card-heading"><h2>Response &amp; Resolution Time Trends</h2><a href="{{ $tabUrl('trends') }}">View trends <x-icon name="arrow-right" size="13" /></a></div><div class="ra-dual-bars">@foreach($timeSeries as $point)<div><i style="height:{{ min(92, max(12, $point['secondary'] * 3)) }}%"></i><b>{{ $point['secondary'] }}m</b><small>{{ $point['label'] }}</small></div>@endforeach</div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Recent High Volume Topics</h2></div><div class="ra-summary-list">@foreach($topics as $topic)<div><span>{{ $topic['topic'] }}</span><strong>{{ $topic['volume'] }}</strong><small class="ra-good">{{ $topic['change'] }}</small></div>@endforeach</div></article>
                </div>
                <div class="ra-grid ra-grid--two">
                    <article class="ra-card"><div class="ra-card-heading"><h2>Customer Satisfaction Trend</h2><a href="{{ $tabUrl('customers') }}">View customers <x-icon name="arrow-right" size="13" /></a></div><div class="ra-satisfaction"><strong>4.68 <small>/ 5</small></strong><span class="ra-stars">★★★★★</span><em>+4.12% vs previous period</em></div><div class="ra-progress"><span><i style="width:94%"></i>5 Stars <b>72%</b></span><span><i style="width:74%"></i>4 Stars <b>21%</b></span><span><i style="width:31%"></i>3 Stars or less <b>7%</b></span></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Conversations by Time of Day</h2><a href="{{ $tabUrl('channels') }}">View analysis <x-icon name="arrow-right" size="13" /></a></div><div class="ra-heatmap">@for($i = 0; $i < 35; $i++)<i style="--heat:{{ (($i * 17) % 100) / 100 }}"></i>@endfor</div><div class="ra-heatmap-labels"><span>Morning</span><span>Afternoon</span><span>Evening</span><span>Night</span></div></article>
                </div>
            @else
                <div class="ra-grid ra-grid--four">
                    <article class="ra-card ra-card--span-2"><div class="ra-card-heading"><h2>Customer Growth Over Time</h2><select class="ra-compact-select" data-ra-period><option>Daily</option><option>Weekly</option><option>Monthly</option></select></div><div class="ra-chart"><svg viewBox="0 0 600 190" role="img" aria-label="Customer growth over time line chart"><g class="ra-grid-lines"><path d="M42 25H580M42 72H580M42 119H580M42 166H580" /></g><polyline class="ra-chart-area" points="42,166 {{ $points($timeSeries) }} 580,166"/><polyline class="ra-line ra-line--green" points="42,166 {{ $points($timeSeries) }}"/>@foreach($timeSeries as $index => $point)<text x="{{ 42 + ($index * 89.6) }}" y="184">{{ $point['label'] }}</text>@endforeach</svg><div class="ra-chart-key"><span><i class="green"></i>New Customers</span><span><i class="muted"></i>Previous Period</span></div></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Customers by Segment</h2><a href="{{ $tabUrl('customer-profile') }}">View segments <x-icon name="arrow-right" size="13" /></a></div><div class="ra-donut-layout"><div class="ra-donut ra-donut--small" style="background:{{ $donut($segments) }}"><strong>{{ $isPreview ? '18,742' : $valueNumber(array_sum(array_column($segments, 'value'))) }}</strong><span>Customers</span></div><div class="ra-legend">@foreach($segments as $row)<div><i style="background:{{ $row['color'] }}"></i><span>{{ $row['label'] }}</span><b>{{ $valueNumber($row['value']) }} <small>({{ $row['share'] }}%)</small></b></div>@endforeach</div></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Customers by Channel</h2><a href="{{ $tabUrl('acquisition') }}">View acquisition <x-icon name="arrow-right" size="13" /></a></div><div class="ra-donut-layout"><div class="ra-donut ra-donut--small" style="background:{{ $donut($channels) }}"><strong>{{ $isPreview ? '18,742' : $valueNumber(array_sum(array_column($channels, 'value'))) }}</strong><span>Customers</span></div><div class="ra-legend">@foreach($channels as $row)<div><i style="background:{{ $row['color'] }}"></i><span>{{ $row['label'] }}</span><b>{{ $valueNumber($row['value']) }} <small>({{ $row['share'] }}%)</small></b></div>@endforeach</div></div></article>
                </div>
                <div class="ra-grid ra-grid--three">
                    <article class="ra-card"><div class="ra-card-heading"><h2>Revenue by Customer Segment</h2><a href="{{ $tabUrl('lifetime-value') }}">View revenue <x-icon name="arrow-right" size="13" /></a></div><div class="ra-donut-layout"><div class="ra-donut" style="background:{{ $donut($revenueSegments) }}"><strong>€2.84M</strong><span>Total Revenue</span></div><div class="ra-legend">@foreach($revenueSegments as $row)<div><i style="background:{{ $row['color'] }}"></i><span>{{ $row['label'] }}</span><b>{{ $row['value'] }} <small>({{ $row['share'] }}%)</small></b></div>@endforeach</div></div></article>
                    <article class="ra-card ra-card--span-2"><div class="ra-card-heading"><h2>Top Customers by Revenue</h2><a href="{{ $tabUrl('purchase-behavior') }}">View all customers <x-icon name="arrow-right" size="13" /></a></div><div class="ra-table-wrap"><table class="ra-table"><thead><tr><th>Customer</th><th>Revenue</th><th>Orders</th><th>Segment</th></tr></thead><tbody>@foreach($topCustomers as $row)<tr><td><strong>{{ $row['name'] }}</strong><small>{{ $row['email'] ?? $row['channel'] ?? 'Customer profile' }}</small></td><td>{{ $row['revenue'] }}</td><td>{{ $valueNumber($row['orders']) }}</td><td><span class="ra-pill ra-pill--{{ ($row['segment'] ?? '') === 'VIP' ? 'orange' : 'green' }}">{{ $row['segment'] ?? 'Repeat' }}</span></td></tr>@endforeach</tbody></table></div></article>
                </div>
                <div class="ra-grid ra-grid--four ra-value-summary">@foreach($valueSummary as $row)<article class="ra-card"><span>{{ $row['label'] }}</span><strong>{{ $row['value'] }}</strong><small>{{ $row['detail'] }}</small></article>@endforeach</div>
                <div class="ra-grid ra-grid--two">
                    <article class="ra-card"><div class="ra-card-heading"><h2>RFM Analysis Summary</h2><a href="{{ $tabUrl('rfm-analysis') }}">View RFM analysis <x-icon name="arrow-right" size="13" /></a></div><div class="ra-rfm-grid"><div class="ra-rfm-head"><span>Segment</span><span>Customers</span><span>Value</span></div>@foreach($rfmRows as $row)<div class="ra-rfm-row"><span><i style="background:{{ $row['color'] }}"></i>{{ $row['label'] }}</span><strong>{{ $valueNumber($row['count']) }}</strong><em>{{ $loop->index < 2 ? 'High' : 'Medium' }}</em></div>@endforeach</div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Customer Retention Overview</h2><a href="{{ $tabUrl('retention-churn') }}">View retention <x-icon name="arrow-right" size="13" /></a></div><div class="ra-retention-score"><strong>38.62%</strong><span>Retention Rate</span><em>↑ 3.42% vs previous period</em></div><div class="ra-chart ra-chart--short"><svg viewBox="0 0 600 140" role="img" aria-label="Customer retention line chart"><g class="ra-grid-lines"><path d="M42 20H580M42 62H580M42 104H580" /></g><polyline class="ra-line ra-line--green" points="42,105 132,90 222,96 312,70 402,78 492,51 580,40" /></svg></div></article>
                </div>
                <div class="ra-grid ra-grid--two">
                    <article class="ra-card"><div class="ra-card-heading"><h2>Customers by Region</h2><a href="{{ $tabUrl('geographic-analysis') }}">View geography <x-icon name="arrow-right" size="13" /></a></div><div class="ra-region-layout"><svg class="ra-map" viewBox="0 0 260 150" aria-label="Customers by region map"><path d="M12 58 35 39l26 4 17-17 28 7 17-11 31 17 21-4 23 19 29 8 9 28-32 13-26-12-29 13-28-16-31 5-25-14-29 4z"/><path d="m84 77 21-12 18 8-10 19-25 3zM167 63l18-8 23 10-18 18-18-7z"/></svg><div class="ra-region-table">@foreach($regions as $row)<div><span>{{ $row['label'] }}</span><strong>{{ $valueNumber($row['customers']) }}</strong><small>{{ $row['share'] }}</small></div>@endforeach</div></div></article>
                    <article class="ra-card"><div class="ra-card-heading"><h2>Customer Demographics</h2><a href="{{ $tabUrl('customer-profile') }}">View profiles <x-icon name="arrow-right" size="13" /></a></div><div class="ra-demographics"><div class="ra-donut ra-donut--small" style="background:conic-gradient(#2676cc 0 52%,#f08a14 52% 91%,#a33c98 91% 100%)"><strong>18.7K</strong><span>Customers</span></div><div class="ra-age-bars"><span><i style="width:36%"></i>18-24 <b>18%</b></span><span><i style="width:68%"></i>25-34 <b>34%</b></span><span><i style="width:76%"></i>35-44 <b>38%</b></span><span><i style="width:20%"></i>45+ <b>10%</b></span></div></div></article>
                </div>
                <div class="ra-grid ra-grid--one"><article class="ra-card"><div class="ra-card-heading"><h2>Recent Customer Alerts</h2><a href="{{ route('admin.resource', 'alerts-notifications') }}">View all <x-icon name="arrow-right" size="13" /></a></div><div class="ra-alert-list ra-alert-list--inline">@foreach($alerts as $alert)<a href="{{ route('admin.resource', 'alerts-notifications') }}"><i class="ra-alert-dot ra-alert-dot--{{ $alert['tone'] }}"></i><span>{{ $alert['label'] }}</span><b>{{ $alert['value'] }}</b><x-icon name="chevron-right" size="13" /></a>@endforeach</div></article></div>
            @endif
        </div>

        <aside class="ra-aside">
            <section class="ra-side-card ra-side-date"><div class="ra-side-heading"><span><x-icon name="calendar" size="18" /></span><strong>Date Range</strong></div><b>{{ $filters['from_label'] }} - {{ $filters['to_label'] }}</b><small>vs {{ $filters['compare_label'] }}</small></section>
            <section class="ra-side-card ra-side-filter" data-ra-side-filter><div class="ra-side-heading"><h2>Report Filters</h2><button type="button" data-ra-filter-toggle aria-label="Toggle filters"><x-icon name="filter" size="14" /></button></div><form method="get" action="{{ url()->current() }}"><input type="hidden" name="tab" value="{{ $activeTab }}"><label>Search<input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search data..."></label><label>From<input type="date" name="from" value="{{ $filters['from_value'] }}"></label><label>To<input type="date" name="to" value="{{ $filters['to_value'] }}"></label>@if($report === 'order')<label>Order Type<select name="order_type">@foreach($filterOptions['order_type'] as $key => $label)<option value="{{ $key }}" @selected($filters['order_type'] === $key)>{{ $label }}</option>@endforeach</select></label><label>Channel<select name="channel">@foreach($filterOptions['channel'] as $key => $label)<option value="{{ $key }}" @selected($filters['channel'] === $key)>{{ $label }}</option>@endforeach</select></label>@elseif($report === 'communication')<label>Channel<select name="channel">@foreach($filterOptions['channel'] as $key => $label)<option value="{{ $key }}" @selected($filters['channel'] === $key)>{{ $label }}</option>@endforeach</select></label><label>Status<select name="status">@foreach($filterOptions['status'] as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></label>@else<label>Segment<select name="segment">@foreach($filterOptions['segment'] as $key => $label)<option value="{{ $key }}" @selected($filters['segment'] === $key)>{{ $label }}</option>@endforeach</select></label><label>Customer Type<select name="customer_type">@foreach($filterOptions['customer_type'] as $key => $label)<option value="{{ $key }}" @selected($filters['customer_type'] === $key)>{{ $label }}</option>@endforeach</select></label>@endif<button class="ra-button ra-button--green ra-button--full" type="submit">Apply Filters</button><a class="ra-side-reset" href="{{ url()->current() }}"><x-icon name="refresh" size="12" /> Reset filters</a></form></section>
            <section class="ra-side-card"><div class="ra-side-heading"><h2>Quick Actions</h2></div><div class="ra-side-actions"><a href="{{ route('admin.reports.export', $exportQuery) }}"><x-icon name="download" size="14" /> Export {{ $title }}</a><a href="{{ route('admin.reports.scheduler') }}"><x-icon name="calendar" size="14" /> Schedule Report</a><a href="{{ route('admin.reports.history') }}"><x-icon name="clock" size="14" /> Report History</a><a href="{{ route('admin.reports.custom') }}"><x-icon name="settings" size="14" /> Create Custom Report</a></div></section>
            <section class="ra-side-card"><div class="ra-side-heading"><h2>Report Summary</h2><a href="{{ $tabUrl('overview') }}">Reset</a></div><div class="ra-side-summary"><div><span>Report Window</span><b>{{ $filters['from_label'] }} - {{ $filters['to_label'] }}</b></div><div><span>Data Source</span><b>PostgreSQL</b></div><div><span>Last Updated</span><b>Just now</b></div><div><span>View</span><b>{{ $tabs[$activeTab] }}</b></div></div></section>
            <section class="ra-side-card ra-side-help"><x-icon name="help" size="17" /><div><strong>Need help?</strong><p>Use filters to refine this report or export the current view.</p></div></section>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/reports-analytics-reference.js?v=20260912-1') }}"></script>
@endpush
