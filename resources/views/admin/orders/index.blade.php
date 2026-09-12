@extends('layouts.admin')

@section('title', $label.' · Order Master')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/orders-reference.css?v=20260912-1') }}">
@endpush

@php
    $money = static fn ($value): string => '€'.number_format((float) $value, 2);
    $integer = static fn ($value): string => number_format((int) $value);
    $key = static fn ($value): string => str((string) $value)->replace('_', '-')->lower()->toString();
    $statusLabel = static fn ($value): string => match ((string) $value) {
        'pending' => 'New', 'completed' => 'Delivered', 'refunded' => 'Refunded', 'pay_on_delivery' => 'Pay on Delivery', default => str((string) $value)->replace('_', ' ')->headline()->toString(),
    };
    $fulfillmentLabel = static fn ($value): string => match ((string) $value) {
        'pending' => 'Unfulfilled', 'on_hold' => 'On Hold', 'ready_to_ship' => 'Ready to Ship', default => str((string) $value)->replace('_', ' ')->headline()->toString(),
    };
    $donut = static function (array $rows): string {
        $offset = 0; $stops = [];
        foreach ($rows as $row) {
            $end = min(100, $offset + (float) ($row['share'] ?? 0));
            $stops[] = ($row['color'] ?? '#dfe8e3').' '.$offset.'% '.$end.'%'; $offset = $end;
        }
        if ($offset < 100) $stops[] = '#e7efeb '.$offset.'% 100%';
        return implode(', ', $stops);
    };
    $statusDonut = $donut($statusRows);
    $queryWithoutTab = request()->except('tab', 'page');
    $summaryTotal = max(1, (int) $summary['total']);
@endphp

@section('content')
<div class="orders-reference-page" data-orders-dashboard>
    <div class="orders-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Project 1 Control Panel (cPanel)</a><x-icon name="chevron-right" size="12" />
        <a href="{{ route('admin.order-master.overview') }}">Online Sales</a><x-icon name="chevron-right" size="12" />
        <a href="{{ route('admin.order-master', $type) }}">Orders</a><x-icon name="chevron-right" size="12" /><strong>{{ $label }}</strong>
    </div>

    <div class="orders-heading-row">
        <div class="orders-heading"><h1>{{ $label }}</h1><p>{{ $meta['subtitle'] }}</p></div>
        <div class="orders-date-card"><span class="orders-date-icon"><x-icon name="calendar" size="18" /></span><span><b>Date Range</b><strong>{{ $dateRange['from_label'] }} - {{ $dateRange['to_label'] }}</strong><small>vs 01 Mar 2025 - 31 Mar 2025</small></span><x-icon name="chevron-down" size="13" /></div>
    </div>

    <div class="orders-layout">
        <main class="orders-main">
            <section class="orders-kpis" aria-label="{{ $label }} metrics">
                @foreach($metrics as $metric)
                    <article class="orders-kpi">
                        <span class="orders-kpi-icon orders-kpi-icon--{{ $metric['tone'] }}"><x-icon name="{{ $metric['icon'] }}" size="18" /></span>
                        <span class="orders-kpi-copy"><b>{{ $metric['label'] }}</b><strong>{{ $metric['kind'] === 'money' ? $money($metric['value']) : $integer($metric['value']) }}</strong><small><i>↑ {{ $metric['change'] }}</i> vs last 31 days</small></span>
                    </article>
                @endforeach
            </section>

            <div class="orders-tabs-actions">
                <nav class="orders-tabs" aria-label="Order statuses">
                    @foreach($tabs as $slug => $tabLabel)<a class="{{ $activeTab === $slug ? 'active' : '' }}" href="{{ route('admin.order-master', array_merge([$type], $queryWithoutTab, ['tab' => $slug])) }}">{{ $tabLabel }}</a>@endforeach
                </nav>
                <div class="orders-actions"><a class="orders-btn orders-btn--soft" href="{{ route('admin.order-master.export', array_merge(request()->query(), ['order_type' => $type])) }}"><x-icon name="download" size="13" /> Export</a><button class="orders-btn orders-btn--soft" type="button" data-order-modal-open="import"><x-icon name="upload" size="13" /> Import</button><button class="orders-btn orders-btn--primary" type="button" data-order-modal-open="create"><x-icon name="plus" size="13" /> Create {{ $meta['singular'] }}</button></div>
            </div>

            <form class="orders-filter-row" method="get" action="{{ route('admin.order-master', $type) }}" data-orders-filter>
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <label class="orders-search"><x-icon name="search" size="14" /><input name="q" value="{{ request('q') }}" placeholder="Search by Order #, Customer, Company, Store, Email, Phone..." aria-label="Search orders"></label>
                <button class="orders-filter-button" type="button" data-orders-filter-toggle><x-icon name="filter" size="13" /> Filters</button>
                <label><span class="sr-only">{{ $meta['selector_label'] }}</span><select name="{{ $meta['selector_key'] }}" onchange="this.form.submit()"><option value="">{{ $meta['selector_label'] }}</option>@foreach($selectorOptions as $option)<option value="{{ $option }}" @selected(request($meta['selector_key']) === $option)>{{ $option }}</option>@endforeach</select></label>
                <label><span class="sr-only">Payment methods</span><select name="payment_method" onchange="this.form.submit()"><option value="">All Payment Methods</option>@foreach($paymentMethods as $method)<option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ str($method)->replace('_', ' ')->headline() }}</option>@endforeach</select></label>
                <label><span class="sr-only">Fulfillment status</span><select name="fulfillment_status" onchange="this.form.submit()"><option value="">All Fulfillment Status</option>@foreach($fulfillmentStatuses as $fulfillment)<option value="{{ $fulfillment }}" @selected(request('fulfillment_status') === $fulfillment)>{{ $fulfillmentLabel($fulfillment) }}</option>@endforeach</select></label>
                <div class="orders-date-inputs"><input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Date from"><span>–</span><input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Date to"><x-icon name="calendar" size="13" /></div>
                <a class="orders-reset" href="{{ route('admin.order-master', $type) }}"><x-icon name="refresh" size="12" /> Reset</a>
                <div class="orders-filter-drawer {{ request('status') || request('payment_status') ? 'is-open' : '' }}" data-orders-filter-drawer>
                    <label>Order Status<select name="status"><option value="">Any order status</option>@foreach($orderStatuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabel($status) }}</option>@endforeach</select></label>
                    <label>Payment Status<select name="payment_status"><option value="">Any payment status</option>@foreach($paymentStatuses as $status)<option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></label>
                    <button class="orders-btn orders-btn--primary" type="submit">Apply Filters</button>
                </div>
            </form>

            <section class="orders-table-card">
                <div class="orders-table-wrap"><table class="orders-table"><thead><tr><th class="orders-check"><input type="checkbox" data-orders-select-all aria-label="Select all orders"></th><th>ORDER #</th><th>{{ $meta['entity_header'] }}</th>@if($meta['entity_mode'] === 'channel')<th>CHANNEL</th>@endif<th>DATE &amp; TIME</th><th>PAYMENT</th><th>AMOUNT</th><th>STATUS</th><th>FULFILLMENT</th><th>ACTIONS</th></tr></thead><tbody>
                    @if($preview)
                        @foreach($previewRows as $row)
                            <tr><td class="orders-check"><input type="checkbox" aria-label="Select {{ $row['number'] }}"></td><td><a class="orders-number" href="{{ route('admin.order-master.overview', ['order_type' => $type, 'q' => $row['number']]) }}">{{ $row['number'] }}</a></td><td><strong class="orders-entity">{{ $row['entity'] }}</strong><small>{{ $row['contact'] }}</small></td>@if($meta['entity_mode'] === 'channel')<td><span class="orders-source">{{ $row['channel'] }}</span></td>@endif<td><span>{{ $row['date'] }}</span></td><td><span>{{ $row['payment'] }}</span><small class="orders-payment orders-payment--{{ $key($row['payment_status']) }}">{{ $row['payment_status'] }}</small></td><td><strong>{{ $money($row['amount']) }}</strong><small>{{ $row['items'] }}</small></td><td><span class="orders-status orders-status--{{ $key($row['status_key']) }}">{{ $row['status'] }}</span></td><td><span class="orders-fulfillment orders-fulfillment--{{ $key($row['fulfillment_key']) }}">{{ $row['fulfillment'] }}</span></td><td><div class="orders-row-actions"><a href="{{ route('admin.order-master.overview', ['order_type' => $type, 'q' => $row['number']]) }}" title="Search this order"><x-icon name="eye" size="13" /></a><button type="button" data-order-modal-open="create" title="Create a live order from this row"><x-icon name="pencil" size="13" /></button><a href="{{ route('admin.order-master.overview', ['order_type' => $type, 'q' => $row['number']]) }}" title="Open order search"><x-icon name="dots" size="13" /></a></div></td></tr>
                        @endforeach
                    @else
                        @forelse($orders as $order)
                            @php $address=(array)($order->shipping_address ?: []); $name=$order->user?->name ?: data_get($address, 'name', 'Guest Customer'); $entity=$meta['entity_mode'] === 'store' ? data_get($address, 'store', $name) : ($meta['entity_mode'] === 'franchise' ? data_get($address, 'franchise', $name).' / '.data_get($address, 'store', 'Store') : ($meta['entity_mode'] === 'buyer' ? data_get($address, 'company', $name) : $name)); $contact=implode(' · ', array_filter([$order->email, $order->phone])); $channel=data_get($address, 'channel', $type === 'buyer' ? 'Marketplace' : 'Website'); @endphp
                            <tr><td class="orders-check"><input type="checkbox" name="order_ids[]" value="{{ $order->id }}" aria-label="Select {{ $order->number }}"></td><td><a class="orders-number" href="{{ route('admin.order-master.show', [$type, $order]) }}">{{ $order->number }}</a></td><td><strong class="orders-entity">{{ $entity }}</strong><small>{{ $contact ?: 'No contact details' }}</small></td>@if($meta['entity_mode'] === 'channel')<td><span class="orders-source">{{ $channel }}</span></td>@endif<td><span>{{ optional($order->created_at)->format('d M Y') }}</span><small>{{ optional($order->created_at)->format('g:i A') }}</small></td><td><span>{{ str((string)($order->payment_method ?: 'Not set'))->replace('_', ' ')->headline() }}</span><small class="orders-payment orders-payment--{{ $key($order->payment_status) }}">{{ str((string)$order->payment_status)->replace('_', ' ')->headline() }}</small></td><td><strong>{{ $money($order->total) }}</strong><small>{{ $integer($order->items->sum('quantity')) }} items</small></td><td><span class="orders-status orders-status--{{ $key($order->status) }}">{{ $statusLabel($order->status) }}</span></td><td><span class="orders-fulfillment orders-fulfillment--{{ $key($order->fulfillment_status ?: 'pending') }}">{{ $fulfillmentLabel($order->fulfillment_status ?: 'pending') }}</span></td><td><div class="orders-row-actions"><a href="{{ route('admin.order-master.show', [$type, $order]) }}" title="View order"><x-icon name="eye" size="13" /></a><a href="{{ route('admin.order-master.show', [$type, $order]) }}" title="Edit order"><x-icon name="pencil" size="13" /></a><a href="{{ route('admin.order-master.invoice', [$type, $order]) }}" title="Invoice"><x-icon name="file-text" size="13" /></a></div></td></tr>
                        @empty
                            <tr><td colspan="{{ $meta['entity_mode'] === 'channel' ? 10 : 9 }}" class="orders-empty"><x-icon name="shopping-bag" size="27" /><strong>No orders found</strong><span>Adjust the filters or create a new {{ strtolower($meta['singular']) }}.</span></td></tr>
                        @endforelse
                    @endif
                </tbody></table></div>
                <div class="orders-table-footer"><span>Showing {{ $preview ? '1' : number_format($orders->firstItem() ?? 0) }} to {{ $preview ? number_format(count($previewRows)) : number_format($orders->lastItem() ?? 0) }} of {{ $preview ? number_format(count($previewRows)) : number_format($orders->total()) }} orders</span>@if(!$preview)<div class="orders-pagination">{{ $orders->onEachSide(1)->links() }}</div>@else<div class="orders-pagination"><span class="active">1</span></div>@endif<label>Rows per page<select name="per_page" data-orders-per-page><option value="8" @selected((int)request('per_page', 8) === 8)>8</option><option value="16" @selected((int)request('per_page', 8) === 16)>16</option><option value="25" @selected((int)request('per_page', 8) === 25)>25</option><option value="50" @selected((int)request('per_page', 8) === 50)>50</option></select></label></div>
            </section>

            <section class="orders-report-grid">
                <article class="orders-report-card orders-report-card--status"><div class="orders-card-heading"><h2>Order Status Overview</h2><span>(All {{ $label }})</span></div><div class="orders-donut-layout"><div class="orders-donut" style="--orders-donut:conic-gradient({{ $statusDonut }})"><span><strong>{{ $integer($summary['total']) }}</strong><small>Total Orders</small></span></div><div class="orders-legend">@foreach($statusRows as $row)<div><i style="--orders-swatch:{{ $row['color'] }}"></i><span>{{ $row['label'] }}</span><b>{{ $integer($row['count']) }} ({{ number_format($row['share'], 1) }}%)</b></div>@endforeach</div></div><a href="{{ route('admin.order-master', array_merge([$type], $queryWithoutTab, ['tab' => 'all'])) }}">View All Orders <x-icon name="arrow-right" size="13" /></a></article>
                <article class="orders-report-card orders-report-card--products"><div class="orders-card-heading"><h2>Top Selling Products</h2><span>(By Revenue)</span></div><table class="orders-mini-table"><thead><tr><th>#</th><th>PRODUCT</th><th>REVENUE</th><th>QTY SOLD</th></tr></thead><tbody>@foreach($productRows as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row['label'] }}</td><td>{{ $money($row['amount']) }}</td><td>{{ $integer($row['quantity']) }}</td></tr>@endforeach</tbody></table><a href="{{ route('admin.resource', 'customer-order-reports') }}">View Products Report <x-icon name="arrow-right" size="13" /></a></article>
                <article class="orders-report-card orders-report-card--entities"><div class="orders-card-heading"><h2>{{ $meta['entity_title'] }}</h2><span>{{ $meta['entity_mode'] === 'channel' ? '(By Orders)' : '' }}</span></div><table class="orders-mini-table"><thead><tr><th>{{ $meta['entity_mode'] === 'channel' ? 'CHANNEL' : $meta['entity_header'] }}</th>@if($meta['entity_mode'] !== 'channel')<th>ORDER VALUE</th>@endif<th>ORDERS</th><th>{{ $meta['entity_mode'] === 'franchise' ? 'STORES' : '%' }}</th></tr></thead><tbody>@foreach($entityRows as $row)<tr><td>{{ $row['label'] }}</td>@if($meta['entity_mode'] !== 'channel')<td>{{ $money($row['amount']) }}</td>@endif<td>{{ $integer($row['orders']) }}</td><td>{{ $meta['entity_mode'] === 'franchise' ? $row['secondary'] : number_format((float)($row['share'] ?? 0), 1).'%' }}</td></tr>@endforeach</tbody></table><a href="{{ route('admin.sales-reports.dashboard', ['tab' => $meta['entity_mode'] === 'channel' ? 'channels' : 'sales-by-customers']) }}">View Full Report <x-icon name="arrow-right" size="13" /></a></article>
                <article class="orders-report-card orders-report-card--performance"><div class="orders-card-heading"><h2>{{ $meta['performance_title'] }}</h2><span>Live status mix</span></div><div class="orders-performance-list">@foreach($performanceRows as $row)<div><span><i style="--orders-swatch:{{ $row['color'] }}"></i>{{ $row['label'] }}</span><b>{{ $integer($row['value']) }}</b><em>{{ number_format($row['percent'], 1) }}%</em><progress max="100" value="{{ $row['percent'] }}"></progress></div>@endforeach</div><a href="{{ route('admin.order-master', array_merge([$type], $queryWithoutTab, ['tab' => 'processing'])) }}">Manage Fulfillment <x-icon name="arrow-right" size="13" /></a></article>
            </section>
        </main>

        <aside class="orders-rail">
            <section class="orders-rail-card"><div class="orders-rail-heading"><h2>{{ strtoupper($label) }} SUMMARY</h2><x-icon name="chart" size="14" /></div><dl><div><dt>Total {{ $label }}</dt><dd>{{ $integer($summary['total']) }}</dd></div><div><dt>Order Value</dt><dd>{{ $money($summary['value']) }}</dd></div><div><dt>Average Order Value</dt><dd>{{ $money($summary['average']) }}</dd></div><div><dt>Orders in Progress</dt><dd>{{ $integer($summary['in_progress']) }}</dd></div><div><dt>{{ $summary['pending_label'] }}</dt><dd>{{ $integer($summary['pending']) }}</dd></div><div><dt>Approved Orders</dt><dd>{{ $integer($summary['approved']) }}</dd></div><div><dt>Shipped Orders</dt><dd>{{ $integer($summary['shipped']) }}</dd></div><div><dt>Delivered Orders</dt><dd>{{ $integer($summary['delivered']) }}</dd></div><div><dt>Cancelled Orders</dt><dd>{{ $integer($summary['cancelled']) }}</dd></div><div><dt>Return / Refund Requests</dt><dd>{{ $integer($summary['returns']) }}</dd></div><div><dt>Conversion Rate</dt><dd>{{ number_format($summary['conversion'], 2) }}%</dd></div></dl></section>
            <section class="orders-rail-card"><div class="orders-rail-heading"><h2>QUICK ACTIONS</h2><x-icon name="dots" size="14" /></div><div class="orders-quick-actions"><button type="button" data-order-modal-open="create"><x-icon name="plus" size="13" />Create {{ $meta['singular'] }}</button><button type="button" data-order-modal-open="import"><x-icon name="upload" size="13" />Upload {{ $label }} (CSV)</button><button type="button" data-order-modal-open="import"><x-icon name="download" size="13" />Import Orders</button><button type="button" data-orders-filter-toggle><x-icon name="settings" size="13" />Manage Order Statuses</button><a href="{{ route('admin.discounts-coupons') }}"><x-icon name="star" size="13" />Apply Discount / Coupon</a><button type="button" data-orders-print><x-icon name="file-text" size="13" />Print / Download Invoices</button><a href="{{ route('admin.user-system.activity') }}"><x-icon name="file-text" size="13" />{{ $label }} Audit Log</a></div></section>
            <section class="orders-rail-card"><div class="orders-rail-heading"><h2>ORDER NOTIFICATIONS</h2><a href="{{ route('admin.order-master', $type) }}">View All</a></div><div class="orders-notifications">@foreach($notifications as $notice)<div><i class="orders-notice-dot orders-notice-dot--{{ $notice['tone'] }}"></i><span>{{ $notice['text'] }}</span><time>{{ $notice['time'] }}</time></div>@endforeach</div></section>
        </aside>
    </div>
</div>

<div class="orders-modal" data-order-modal="create" aria-hidden="true"><div class="orders-modal-backdrop" data-order-modal-close></div><section class="orders-modal-card" role="dialog" aria-modal="true" aria-labelledby="orders-create-title"><header><div><span>ONLINE SALES · ORDER MASTER</span><h2 id="orders-create-title">Create {{ $meta['singular'] }}</h2></div><button type="button" data-order-modal-close aria-label="Close">×</button></header><form method="post" action="{{ route('admin.order-master.store') }}">@csrf<input type="hidden" name="order_type" value="{{ $type }}"><div class="orders-form-grid"><label>Customer / Company<input name="customer_name" required placeholder="Customer or company name"></label><label>Email<input type="email" name="email" required placeholder="customer@example.com"></label><label>Phone<input name="phone" placeholder="+353 ..."></label><label>Payment Method<input name="payment_method" placeholder="Card, PayPal, Bank Transfer..."></label><label>Subtotal (€)<input type="number" step="0.01" min="0" name="subtotal" value="0" required></label><label>Shipping (€)<input type="number" step="0.01" min="0" name="shipping" value="0"></label><label>Discount (€)<input type="number" step="0.01" min="0" name="discount" value="0"></label><label>Order Status<select name="status">@foreach($orderStatuses as $status)<option value="{{ $status }}" @selected($status === 'pending')>{{ $statusLabel($status) }}</option>@endforeach</select></label><label>Payment Status<select name="payment_status">@foreach($paymentStatuses as $status)<option value="{{ $status }}" @selected($status === 'pending')>{{ str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></label><label>Fulfillment<select name="fulfillment_status">@foreach($fulfillmentStatuses as $status)<option value="{{ $status }}" @selected($status === 'pending')>{{ $fulfillmentLabel($status) }}</option>@endforeach</select></label><label class="orders-form-wide">Notes<textarea name="notes" rows="3" placeholder="Internal order notes"></textarea></label></div><footer><button type="button" class="orders-btn orders-btn--soft" data-order-modal-close>Cancel</button><button class="orders-btn orders-btn--primary" type="submit">Create Order</button></footer></form></section></div>

<div class="orders-modal" data-order-modal="import" aria-hidden="true"><div class="orders-modal-backdrop" data-order-modal-close></div><section class="orders-modal-card orders-modal-card--small" role="dialog" aria-modal="true" aria-labelledby="orders-import-title"><header><div><span>CSV IMPORT</span><h2 id="orders-import-title">Import Orders</h2></div><button type="button" data-order-modal-close aria-label="Close">×</button></header><form method="post" enctype="multipart/form-data" action="{{ route('admin.order-master.import') }}">@csrf<label class="orders-dropzone"><x-icon name="upload" size="27" /><strong>Choose a CSV file</strong><span>Headers: number, order_type, customer_name, email, phone, status, payment_status, payment_method, fulfillment_status, subtotal, shipping, discount, total</span><input type="file" name="file" accept=".csv,text/csv" required></label><footer><button type="button" class="orders-btn orders-btn--soft" data-order-modal-close>Cancel</button><button class="orders-btn orders-btn--primary" type="submit">Import Orders</button></footer></form></section></div>
@endsection

@push('scripts')
    <script src="{{ asset('js/orders-reference.js?v=20260912-1') }}" defer></script>
@endpush
