@extends('layouts.admin')

@section('title', 'Sales Reports')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales-reports-reference.css?v=20260910-1') }}">
@endpush

@php
    $money = static fn ($value): string => '€'.number_format((float) $value, 2);
    $integer = static fn ($value): string => number_format((int) $value);
    $share = static fn ($value): string => number_format((float) $value, 2).'%';
    $query = request()->query();
    $queryWithoutTab = request()->except('tab');
    $donut = static function (array $rows): string {
        $offset = 0;
        $stops = [];
        foreach ($rows as $row) {
            $end = min(100, $offset + (float) ($row['share'] ?? 0));
            $stops[] = ($row['color'] ?? '#2676cc').' '.$offset.'% '.$end.'%';
            $offset = $end;
        }
        if ($offset < 100) $stops[] = '#e7efeb '.$offset.'% 100%';
        return implode(', ', $stops);
    };
    $points = static function (array $values): string {
        $max = max(1, ...$values);
        return collect($values)->values()->map(function ($value, $index) use ($max): string {
            $x = 16 + ($index * 48);
            $y = 106 - (((float) $value / $max) * 80);
            return round($x, 2).','.round($y, 2);
        })->implode(' ');
    };
    $categoryDonut = $donut($categoryRows);
    $channelDonut = $donut($channelRows);
    $paymentDonut = $donut($paymentRows);
    $insights = $insights ?? ['discounts' => 0, 'discountedOrders' => 0, 'discountRate' => '—', 'averageDiscount' => '—', 'returnRate' => '—', 'refundedOrders' => 0, 'refundImpact' => '—'];
@endphp

@section('content')
<div class="sales-report-page" data-sales-report-root data-empty="{{ $isEmpty ? '1' : '0' }}">
    <div class="sales-report-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Project 1 Control Panel (cPanel)</a>
        <x-icon name="chevron-right" size="12" />
        <a href="{{ route('admin.sales-reports.dashboard') }}">Online Sales</a>
        <x-icon name="chevron-right" size="12" />
        <strong>Sales Reports</strong>
    </div>

    <div class="sales-report-heading-row">
        <div class="sales-report-heading">
            <h1>Sales Reports</h1>
            <p>Comprehensive sales analytics across all order categories, channels, products, customers, franchises and stores.</p>
        </div>
        <div class="sales-report-date-card">
            <div class="sales-report-date-icon"><x-icon name="calendar" size="18" /></div>
            <div>
                <span>Date Range</span>
                <strong>{{ $filters['from_label'] }} - {{ $filters['to_label'] }}</strong>
                <small>Comparison data is shown when available</small>
            </div>
            <x-icon name="chevron-down" size="14" />
        </div>
    </div>

    <div class="sales-report-data-note" role="status">{{ $dataNote }}</div>

    <div class="sales-report-layout">
        <main class="sales-report-main">
            <section class="sales-report-kpis" aria-label="Sales report metrics">
                @foreach($metrics as $metric)
                    <article class="sales-report-kpi">
                        <div class="sales-report-kpi-icon sales-report-kpi-icon--{{ $metric['tone'] }}"><x-icon name="{{ $metric['icon'] }}" size="19" /></div>
                        <div class="sales-report-kpi-copy">
                            <span>{{ $metric['label'] }}</span>
                            <strong>{{ $metric['kind'] === 'money' ? $money($metric['value']) : $integer($metric['value']) }}</strong>
                            <small>@if($metric['change'] !== '—')<i>↑ {{ $metric['change'] }}</i>@endif {{ $metric['caption'] }}</small>
                        </div>
                    </article>
                @endforeach
            </section>

            <nav class="sales-report-tabs" aria-label="Sales report views">
                @foreach($tabs as $slug => $label)
                    <a class="{{ $activeTab === $slug ? 'active' : '' }}" href="{{ route('admin.sales-reports.dashboard', array_merge($queryWithoutTab, ['tab' => $slug])) }}">{{ $label }}</a>
                @endforeach
            </nav>

            <form class="sales-report-filter-row" method="get" action="{{ route('admin.sales-reports.dashboard') }}">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <label>
                    <span class="sr-only">Order category</span>
                    <select name="category">
                        <option value="all">All Order Categories (6)</option>
                        @foreach($options['categories'] as $key => $label)<option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label><span class="sr-only">Channel</span><select name="channel"><option value="all">All Channels</option>@foreach($options['channels'] as $option)<option value="{{ $option }}" @selected($filters['channel'] === $option)>{{ $option }}</option>@endforeach</select></label>
                <label><span class="sr-only">Franchise</span><select name="franchise"><option value="all">All Franchises</option>@foreach($options['franchises'] as $option)<option value="{{ $option }}" @selected($filters['franchise'] === $option)>{{ $option }}</option>@endforeach</select></label>
                <label><span class="sr-only">Retail store</span><select name="store"><option value="all">All Retail Stores</option>@foreach($options['stores'] as $option)<option value="{{ $option }}" @selected($filters['store'] === $option)>{{ $option }}</option>@endforeach</select></label>
                <label><span class="sr-only">Country</span><select name="country"><option value="all">All Countries</option>@foreach($options['countries'] as $option)<option value="{{ $option }}" @selected($filters['country'] === $option)>{{ $option }}</option>@endforeach</select></label>
                <label><span class="sr-only">Customer group</span><select name="customer_group"><option value="all">All Customer Groups</option>@foreach($options['customer_groups'] as $option)<option value="{{ $option }}" @selected($filters['customer_group'] === $option)>{{ $option }}</option>@endforeach</select></label>
                <label class="sales-report-date-input"><span class="sr-only">From date</span><input type="date" name="from" value="{{ $filters['from_value'] }}"></label>
                <span class="sales-report-date-separator">-</span>
                <label class="sales-report-date-input"><span class="sr-only">To date</span><input type="date" name="to" value="{{ $filters['to_value'] }}"></label>
                <button class="sales-report-compare" type="submit" name="compare" value="1"><x-icon name="copy" size="13" /> Compare</button>
                <a class="sales-report-reset" href="{{ route('admin.sales-reports.dashboard') }}"><x-icon name="refresh" size="13" /> Reset</a>
            </form>

            @if($isEmpty)
                <div class="sales-report-empty-state" role="status"><strong>No matching orders</strong><span>Adjust the report filters or add orders to populate this dashboard.</span></div>
            @endif

            <div class="sales-report-card-grid">
                <article class="sales-report-card sales-report-card--trend">
                    <div class="sales-report-card-head"><div><h2>Sales Over Time</h2><span class="sales-report-card-subtitle">Current Period <i class="sales-report-dot sales-report-dot--green"></i> Previous Period <i class="sales-report-dot sales-report-dot--gray"></i></span></div><select aria-label="Sales trend interval"><option>Daily</option><option>Weekly</option><option>Monthly</option></select></div>
                    <div class="sales-report-chart-legend"><span><i class="sales-report-dot sales-report-dot--green"></i> Current Period</span><span><i class="sales-report-dot sales-report-dot--gray"></i> Previous Period</span></div>
                    <div class="sales-report-line-chart">
                        <div class="sales-report-y-labels"><span>€100K</span><span>€80K</span><span>€60K</span><span>€40K</span><span>€20K</span><span>€0</span></div>
                        <svg viewBox="0 0 304 120" role="img" aria-label="Sales over time line chart" preserveAspectRatio="none">
                            <path class="sales-report-chart-gridline" d="M16 6H304M16 26H304M16 46H304M16 66H304M16 86H304M16 106H304" />
                            <polyline class="sales-report-chart-line sales-report-chart-line--previous" points="{{ $points($trend['previous']) }}" />
                            <polyline class="sales-report-chart-line sales-report-chart-line--current" points="{{ $points($trend['current']) }}" />
                            @foreach($trend['current'] as $index => $value)<circle class="sales-report-chart-point" cx="{{ 16 + ($index * 48) }}" cy="{{ 106 - (((float) $value / max(1, max($trend['current']))) * 80) }}" r="2.5" />@endforeach
                        </svg>
                        <div class="sales-report-x-labels">@foreach($trend['labels'] as $label)<span>{{ $label }}</span>@endforeach</div>
                    </div>
                    <div class="sales-report-trend-summary"><div><span>Total (Current)</span><strong>{{ $money($trend['currentTotal']) }}</strong></div><div><span>Total (Previous)</span><strong>{{ $money($trend['previousTotal']) }}</strong></div><div><span>Change</span><strong class="{{ $trend['change'] === '—' ? '' : 'sales-report-positive' }}">{{ $trend['change'] === '—' ? '—' : '↑ '.$trend['change'] }}</strong></div></div>
                </article>

                <article class="sales-report-card sales-report-card--category">
                    <div class="sales-report-card-head"><div><h2>Sales by Order Category</h2><span class="sales-report-card-subtitle">Order category mix</span></div></div>
                    <div class="sales-report-donut-layout"><div class="sales-report-donut" style="--donut: {{ $categoryDonut }}"><span>{{ $money($metrics[0]['value']) }}<small>Total Sales</small></span></div><div class="sales-report-mini-table"><div class="sales-report-mini-heading"><span>Category</span><span>Sales (Net)</span><span>% Share</span><span>Orders</span></div>@foreach($categoryRows as $row)<div class="sales-report-mini-row"><span><i style="--swatch: {{ $row['color'] }}"></i>{{ $row['label'] }}</span><strong>{{ $money($row['amount']) }}</strong><b>{{ $share($row['share']) }}</b><b>{{ $integer($row['orders']) }}</b></div>@endforeach</div></div>
                    <a class="sales-report-card-link" href="{{ route('admin.sales-reports.dashboard', array_merge($query, ['tab' => 'orders-by-category'])) }}">View Category Report <x-icon name="arrow-right" size="14" /></a>
                </article>

                <article class="sales-report-card sales-report-card--channel">
                    <div class="sales-report-card-head"><div><h2>Sales by Channel</h2><span class="sales-report-card-subtitle">Channel performance</span></div></div>
                    <div class="sales-report-donut-layout"><div class="sales-report-donut" style="--donut: {{ $channelDonut }}"><span>{{ $money($metrics[0]['value']) }}<small>Total Sales</small></span></div><div class="sales-report-mini-table"><div class="sales-report-mini-heading"><span>Channel</span><span>Sales (Net)</span><span>% Share</span></div>@foreach($channelRows as $row)<div class="sales-report-mini-row sales-report-mini-row--three"><span><i style="--swatch: {{ $row['color'] }}"></i>{{ $row['label'] }}</span><strong>{{ $money($row['amount']) }}</strong><b>{{ $share($row['share']) }}</b></div>@endforeach</div></div>
                    <a class="sales-report-card-link" href="{{ route('admin.sales-reports.dashboard', array_merge($query, ['tab' => 'channels'])) }}">View Channel Report <x-icon name="arrow-right" size="14" /></a>
                </article>

                <article class="sales-report-card sales-report-card--products">
                    <div class="sales-report-card-head"><h2>Top Selling Products <small>(By Revenue)</small></h2></div>
                    <table class="sales-report-table"><thead><tr><th>#</th><th>Product</th><th>Sales (Net)</th><th>% Share</th><th>Qty Sold</th></tr></thead><tbody>@foreach($productRows as $index => $row)<tr><td>{{ $index + 1 }}</td><td>{{ $row['label'] }}</td><td>{{ $money($row['amount']) }}</td><td>{{ $share($row['share']) }}</td><td>{{ $integer($row['quantity']) }}</td></tr>@endforeach</tbody></table>
                    <a class="sales-report-card-link" href="{{ route('admin.sales-reports.dashboard', array_merge($query, ['tab' => 'sales-by-products'])) }}">View Products Report <x-icon name="arrow-right" size="14" /></a>
                </article>

                <article class="sales-report-card sales-report-card--customers">
                    <div class="sales-report-card-head"><h2>Sales by Customer Group</h2></div>
                    <table class="sales-report-table"><thead><tr><th>Group</th><th>Sales (Net)</th><th>% Share</th><th>Customers</th></tr></thead><tbody>@foreach($customerRows as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $money($row['amount']) }}</td><td>{{ $share($row['share']) }}</td><td>{{ $integer($row['customers']) }}</td></tr>@endforeach</tbody></table>
                    <a class="sales-report-card-link" href="{{ route('admin.sales-reports.dashboard', array_merge($query, ['tab' => 'sales-by-customers'])) }}">View Customers Report <x-icon name="arrow-right" size="14" /></a>
                </article>

                <article class="sales-report-card sales-report-card--franchises">
                    <div class="sales-report-card-head"><h2>Top Performing Franchises <small>(By Sales)</small></h2></div>
                    <table class="sales-report-table"><thead><tr><th>Franchise</th><th>Sales (Net)</th><th>Orders</th><th>Stores</th></tr></thead><tbody>@foreach($franchiseRows as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $money($row['amount']) }}</td><td>{{ $integer($row['orders']) }}</td><td>{{ $integer($row['stores']) }}</td></tr>@endforeach</tbody></table>
                    <a class="sales-report-card-link" href="{{ route('admin.sales-reports.dashboard', array_merge($query, ['tab' => 'sales-by-franchises'])) }}">View Franchise Report <x-icon name="arrow-right" size="14" /></a>
                </article>

                <article class="sales-report-card sales-report-card--payment">
                    <div class="sales-report-card-head"><h2>Sales by Payment Method</h2></div>
                    <div class="sales-report-donut-layout sales-report-donut-layout--compact"><div class="sales-report-donut sales-report-donut--small" style="--donut: {{ $paymentDonut }}"><span>€<small>Sales</small></span></div><div class="sales-report-mini-table"><div class="sales-report-mini-heading"><span>Method</span><span>Sales (Net)</span><span>% Share</span></div>@foreach($paymentRows as $row)<div class="sales-report-mini-row sales-report-mini-row--three"><span><i style="--swatch: {{ $row['color'] }}"></i>{{ $row['label'] }}</span><strong>{{ $money($row['amount']) }}</strong><b>{{ $share($row['share']) }}</b></div>@endforeach</div></div>
                    <a class="sales-report-card-link" href="{{ route('admin.sales-reports.dashboard', array_merge($query, ['tab' => 'payments'])) }}">View Payments Report <x-icon name="arrow-right" size="14" /></a>
                </article>

                <article class="sales-report-card sales-report-card--impact">
                    <div class="sales-report-card-head"><h2>Discounts Impact</h2></div>
                    <dl class="sales-report-stats-list"><div><dt>Discounts Given</dt><dd>{{ $money($insights['discounts']) }}</dd></div><div><dt>Orders with Discount</dt><dd>{{ $integer($insights['discountedOrders']) }} <small>({{ $insights['discountRate'] }})</small></dd></div><div><dt>Avg. Discount per Order</dt><dd>{{ $insights['averageDiscount'] }}</dd></div><div><dt>Revenue Impact</dt><dd>{{ $money($insights['discounts']) }}</dd></div></dl>
                    <a class="sales-report-card-link" href="{{ route('admin.sales-reports.dashboard', array_merge($query, ['tab' => 'discounts'])) }}">View Discounts Report <x-icon name="arrow-right" size="14" /></a>
                </article>

                <article class="sales-report-card sales-report-card--impact">
                    <div class="sales-report-card-head"><h2>Returns Impact</h2></div>
                    <dl class="sales-report-stats-list"><div><dt>Return &amp; Refunds</dt><dd>{{ $money($metrics[4]['value']) }}</dd></div><div><dt>Return Rate (Orders)</dt><dd>{{ $insights['returnRate'] }}</dd></div><div><dt>Refunded Orders</dt><dd>{{ $integer($insights['refundedOrders']) }}</dd></div><div><dt>Revenue Impact</dt><dd>{{ $money($metrics[4]['value']) }} <small>({{ $insights['refundImpact'] }})</small></dd></div></dl>
                    <a class="sales-report-card-link" href="{{ route('admin.sales-reports.dashboard', array_merge($query, ['tab' => 'returns-refunds'])) }}">View Returns Report <x-icon name="arrow-right" size="14" /></a>
                </article>

                <article class="sales-report-card sales-report-card--country">
                    <div class="sales-report-card-head"><h2>Sales by Country</h2><span class="sales-report-card-subtitle">Net sales by destination</span></div>
                    <div class="sales-report-country-layout"><div class="sales-world-map" aria-label="World sales heat map"><span class="map-dot map-dot--one"></span><span class="map-dot map-dot--two"></span><span class="map-dot map-dot--three"></span><span class="map-dot map-dot--four"></span><span class="map-dot map-dot--five"></span></div><table class="sales-report-table sales-report-table--country"><thead><tr><th>Country</th><th>Sales (Net)</th><th>% Share</th></tr></thead><tbody>@foreach($countryRows as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $money($row['amount']) }}</td><td>{{ $share($row['share']) }}</td></tr>@endforeach</tbody></table></div>
                    <a class="sales-report-card-link" href="{{ route('admin.sales-reports.dashboard', array_merge($query, ['tab' => 'sales-by-retail-stores'])) }}">View Geography Report <x-icon name="arrow-right" size="14" /></a>
                </article>

                <article class="sales-report-card sales-report-card--summary">
                    <div class="sales-report-card-head"><h2>Sales Summary</h2></div>
                    <dl class="sales-report-summary-list">@foreach($summaryRows as $row)<div class="{{ $row['label'] === 'Net Revenue' ? 'emphasis' : '' }}"><dt>{{ $row['label'] }}</dt><dd>{{ $money($row['value']) }}</dd></div>@endforeach</dl>
                </article>
            </div>
        </main>

        <aside class="sales-report-rail">
            <section class="sales-report-rail-card sales-report-rail-card--filters">
                <div class="sales-report-rail-title"><h2>Report Filters</h2><x-icon name="filter" size="14" /></div>
                <form method="get" action="{{ route('admin.sales-reports.dashboard') }}">
                    <input type="hidden" name="tab" value="{{ $activeTab }}"><input type="hidden" name="category" value="{{ $filters['category'] }}"><input type="hidden" name="channel" value="{{ $filters['channel'] }}"><input type="hidden" name="from" value="{{ $filters['from_value'] }}"><input type="hidden" name="to" value="{{ $filters['to_value'] }}">
                    <label>Group By<select name="group_by"><option value="none" @selected($filters['group_by'] === 'none')>None (Summary)</option><option value="category" @selected($filters['group_by'] === 'category')>Order Category</option><option value="channel" @selected($filters['group_by'] === 'channel')>Channel</option><option value="customer_group" @selected($filters['group_by'] === 'customer_group')>Customer Group</option><option value="country" @selected($filters['group_by'] === 'country')>Country</option></select></label>
                    <label>Breakdown By<select name="breakdown"><option value="day" @selected($filters['breakdown'] === 'day')>Day</option><option value="week" @selected($filters['breakdown'] === 'week')>Week</option><option value="month" @selected($filters['breakdown'] === 'month')>Month</option></select></label>
                    <h3>Additional Filters</h3>
                    <label>Customer Type<select name="customer_group"><option value="all">All</option>@foreach($options['customer_groups'] as $option)<option value="{{ $option }}" @selected($filters['customer_group'] === $option)>{{ $option }}</option>@endforeach</select></label>
                    <label>Payment Method<select name="payment"><option value="all">All</option>@foreach($options['payments'] as $option)<option value="{{ $option }}" @selected($filters['payment'] === $option)>{{ $option }}</option>@endforeach</select></label>
                    <label>Fulfillment Type<select name="fulfillment"><option value="all">All</option>@foreach($options['fulfillment'] as $option)<option value="{{ $option }}" @selected($filters['fulfillment'] === $option)>{{ \Illuminate\Support\Str::headline($option) }}</option>@endforeach</select></label>
                    <label>Currency<select name="currency"><option value="all">All</option>@foreach($options['currencies'] as $option)<option value="{{ $option }}" @selected($filters['currency'] === $option)>{{ $option }}</option>@endforeach</select></label>
                    <label class="sales-report-checkbox"><input type="checkbox" name="include_tax" value="1" @checked(request('include_tax'))> <span>Include Tax in Sales</span> <small>ⓘ</small></label>
                    <button class="sales-report-primary-button" type="submit">Apply Filters</button>
                </form>
                <a class="sales-report-save-link" href="#sales-report-save-view"><x-icon name="copy" size="13" /> Save Filter View</a>
            </section>

            <section class="sales-report-rail-card">
                <div class="sales-report-rail-title"><h2>Quick Actions</h2><x-icon name="settings" size="14" /></div>
                <div class="sales-report-quick-actions">
                    <a href="{{ route('admin.sales-reports.export', array_merge($query, ['format' => 'csv'])) }}"><x-icon name="download" size="13" /> Export Sales Report (CSV)</a>
                    <a href="{{ route('admin.sales-reports.export', array_merge($query, ['format' => 'excel'])) }}"><x-icon name="download" size="13" /> Export Sales Report (Excel)</a>
                    <a href="{{ route('admin.reports.scheduler', ['report' => 'Sales Reports']) }}"><x-icon name="calendar" size="13" /> Schedule Email Report</a>
                    <a href="{{ route('admin.reports.custom', ['source' => 'Sales Reports']) }}"><x-icon name="file-text" size="13" /> Create Custom Report</a>
                    <a href="#sales-report-save-view"><x-icon name="copy" size="13" /> Save Current View</a>
                    <a href="{{ route('admin.reports.custom') }}"><x-icon name="folder" size="13" /> Manage Saved Reports</a>
                </div>
            </section>

            <section class="sales-report-rail-card" id="sales-report-save-view">
                <div class="sales-report-rail-title"><h2>Save Current View</h2><x-icon name="star" size="14" /></div>
                <form class="sales-report-save-form" method="post" action="{{ route('admin.sales-reports.views.store') }}">
                    @csrf
                    <input type="hidden" name="category" value="{{ $filters['category'] }}"><input type="hidden" name="channel" value="{{ $filters['channel'] }}"><input type="hidden" name="franchise" value="{{ $filters['franchise'] }}"><input type="hidden" name="store" value="{{ $filters['store'] }}"><input type="hidden" name="country" value="{{ $filters['country'] }}"><input type="hidden" name="from" value="{{ $filters['from_value'] }}"><input type="hidden" name="to" value="{{ $filters['to_value'] }}"><input type="hidden" name="customer_group" value="{{ $filters['customer_group'] }}"><input type="hidden" name="payment" value="{{ $filters['payment'] }}"><input type="hidden" name="fulfillment" value="{{ $filters['fulfillment'] }}"><input type="hidden" name="currency" value="{{ $filters['currency'] }}">
                    <label class="sr-only" for="sales-report-view-name">View name</label><input id="sales-report-view-name" name="name" placeholder="e.g. Weekly Online Sales" required maxlength="120"><button class="sales-report-primary-button" type="submit">Save View</button>
                </form>
                @if($savedViews->isNotEmpty())<div class="sales-report-saved-list">@foreach($savedViews as $saved)<div><x-icon name="file-text" size="12" /><span>{{ $saved->title }}</span><small>{{ $saved->public_uuid ? \Illuminate\Support\Str::limit($saved->public_uuid, 8, '') : 'saved' }}</small></div>@endforeach</div>@endif
            </section>

            <section class="sales-report-rail-card sales-report-recent-card">
                <div class="sales-report-rail-title"><h2>Recent Reports</h2><a href="{{ route('admin.reports.history') }}">View All</a></div>
                <div class="sales-report-recent-list">@foreach($recentReports as $report)<a href="{{ route('admin.sales-reports.dashboard', ['tab' => 'overview']) }}"><x-icon name="{{ $report['icon'] }}" size="12" /><span>{{ $report['name'] }}</span><small>{{ $report['when'] }}</small></a>@endforeach</div>
            </section>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/sales-reports-reference.js?v=20260910-1') }}"></script>
@endpush
