@extends('layouts.admin')
@section('title','Order Master Overview')
@push('styles')
<link rel="stylesheet" href="/css/order-master.css?v=20260910-order-master-v1">
@endpush

@php
    $money = static fn ($value) => '€'.number_format((float)$value, 2);
    $customerName = static fn ($order) => $order->user?->name ?: data_get($order->shipping_address, 'name', 'Guest Customer');
    $statusLabel = static fn ($status) => str((string)$status)->replace('_',' ')->headline();
    $orderCount = max(1, (int)$summary['total_orders']);
    $categoryPalette = ['online'=>'#14864b','corporate'=>'#1471d9','bulk'=>'#6f35b5','franchise'=>'#f26d21','franchise_retail'=>'#098a78','buyer'=>'#e2344d'];
    $statusPalette = ['new'=>'#1488e0','processing'=>'#ff970f','pending'=>'#f2bd13','shipped'=>'#119bbd','delivered'=>'#159652','cancelled'=>'#e92d42','return_refund'=>'#4f5063'];
    $categoryStops=[];$cursor=0;
    foreach($categoryStats as $stat){$pct=($stat['count']/$orderCount)*100;$next=$cursor+$pct;$categoryStops[]=$categoryPalette[$stat['type']].' '.$cursor.'% '.$next.'%';$cursor=$next;}
    $statusStops=[];$cursor=0;
    foreach($statusCounts as $key=>$count){$pct=($count/$orderCount)*100;$next=$cursor+$pct;$statusStops[]=$statusPalette[$key].' '.$cursor.'% '.$next.'%';$cursor=$next;}
    $maxMonthly = max(1, (float)$monthlyValue->max('value'));
    $tabs = ['all'=>'All Orders','new'=>'New','processing'=>'Processing','pending'=>'Pending Approval / Payment','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled','returns'=>'Return / Refund'];
@endphp

@section('content')
<div class="om-page" data-order-master data-overview-url="{{ route('admin.order-master.overview') }}">
    <div class="om-title-row">
        <div><h1>Order Master Overview</h1><p>Central view of all order categories and overall order performance.</p></div>
        <div class="om-date-card"><x-icon name="calendar" size="25" /><div><span>Today</span><small>{{ now()->format('l, j F Y') }}</small><strong>{{ now()->format('g:i A') }}</strong></div></div>
    </div>

    <section class="om-kpi-grid" aria-label="Order category summary">
        @foreach($categoryStats as $stat)
            <a class="om-kpi om-kpi--{{ $stat['tone'] }}" href="{{ route('admin.order-master', $stat['type']) }}">
                <span class="om-kpi-icon"><x-icon name="{{ $stat['icon'] }}" size="23" /></span>
                <div class="om-kpi-copy"><span>{{ $stat['label'] }}</span><strong>{{ number_format($stat['count']) }}</strong><b>{{ $money($stat['value']) }}</b><small class="{{ $stat['trend'] < 0 ? 'is-down' : 'is-up' }}">{{ $stat['trend'] < 0 ? '↓' : '↑' }} {{ number_format(abs($stat['trend']),1) }}% <em>vs last 30 days</em></small></div>
            </a>
        @endforeach
    </section>

    <div class="om-workspace">
        <main class="om-main">
            <div class="om-tabs-actions">
                <nav class="om-tabs" aria-label="Order status tabs">@foreach($tabs as $key=>$label)<a class="{{ $activeTab === $key ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab'=>$key,'page'=>null]) }}">{{ $label }}</a>@endforeach</nav>
                <div class="om-top-actions">
                    <a class="om-btn om-btn--soft" href="{{ route('admin.order-master.export', request()->query()) }}"><x-icon name="download" size="14" /> Export</a>
                    <button class="om-btn om-btn--soft" type="button" data-modal-open="import"><x-icon name="upload" size="14" /> Import</button>
                    <button class="om-btn om-btn--green" type="button" data-advanced-toggle><x-icon name="filter" size="14" /> Advanced Filters <x-icon name="chevron-down" size="12" /></button>
                </div>
            </div>

            <form class="om-filter-bar" method="get" action="{{ route('admin.order-master.overview') }}" data-order-filter>
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <label class="om-search"><x-icon name="search" size="15" /><input name="q" value="{{ request('q') }}" placeholder="Search by Order #, Customer, Company, Store, Email, Phone..."></label>
                <button class="om-filter-button" type="submit"><x-icon name="filter" size="14" /> Filters</button>
                <select name="order_type" onchange="this.form.submit()"><option value="">All Order Categories</option>@foreach($typeMeta as $key=>$meta)<option value="{{ $key }}" @selected(request('order_type')===$key)>{{ $meta['label'] }}</option>@endforeach</select>
                <select name="payment_method" onchange="this.form.submit()"><option value="">All Payment Methods</option>@foreach($paymentMethods as $method)<option value="{{ $method }}" @selected(request('payment_method')===$method)>{{ str($method)->replace('_',' ')->headline() }}</option>@endforeach</select>
                <select name="fulfillment_status" onchange="this.form.submit()"><option value="">All Fulfilment Status</option>@foreach($fulfillmentStatuses as $fulfillment)<option value="{{ $fulfillment }}" @selected(request('fulfillment_status')===$fulfillment)>{{ $statusLabel($fulfillment) }}</option>@endforeach</select>
                <div class="om-date-range"><input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Date from"><span>–</span><input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Date to"><x-icon name="calendar" size="14" /></div>
                <a class="om-reset" href="{{ route('admin.order-master.overview') }}"><x-icon name="refresh" size="13" /> Reset</a>
                <div class="om-advanced {{ request('status') || request('payment_status') ? 'is-open' : '' }}" data-advanced-panel>
                    <label>Status<select name="status"><option value="">Any status</option>@foreach($orderStatuses as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $statusLabel($status) }}</option>@endforeach</select></label>
                    <label>Payment status<select name="payment_status"><option value="">Any payment status</option>@foreach($paymentStatuses as $status)<option value="{{ $status }}" @selected(request('payment_status')===$status)>{{ $statusLabel($status) }}</option>@endforeach</select></label>
                    <button class="om-btn om-btn--green" type="submit">Apply Advanced Filters</button>
                </div>
            </form>

            <section class="om-table-card">
                <div class="om-table-wrap"><table class="om-table"><thead><tr><th class="om-check"><input type="checkbox" data-select-all aria-label="Select all orders"></th><th>ORDER #</th><th>ORDER CATEGORY</th><th>CUSTOMER / COMPANY</th><th>DATE &amp; TIME</th><th>TOTAL</th><th>PAYMENT</th><th>STATUS</th><th>FULFILMENT</th><th>ACTIONS</th></tr></thead><tbody>
                @forelse($orders as $order)
                    @php $meta=$typeMeta[$order->order_type] ?? $typeMeta['online']; @endphp
                    <tr>
                        <td class="om-check"><input type="checkbox" name="order_ids[]" value="{{ $order->id }}"></td>
                        <td><a class="om-order-number" href="{{ route('admin.order-master.show',[$order->order_type,$order]) }}">{{ $order->number }}</a></td>
                        <td><span class="om-category om-category--{{ $meta['tone'] }}"><x-icon name="{{ $meta['icon'] }}" size="13" />{{ $meta['label'] }}</span></td>
                        <td><strong class="om-customer">{{ $customerName($order) }}</strong><small>{{ $order->email ?: 'No email' }}</small><small>{{ $order->phone ?: 'No phone' }}</small></td>
                        <td><span>{{ optional($order->created_at)->format('d M Y') }}</span><small>{{ optional($order->created_at)->format('g:i A') }}</small></td>
                        <td><strong>{{ $money($order->total) }}</strong><small>{{ number_format((int)$order->items->sum('quantity')) }} items</small></td>
                        <td><span>{{ $statusLabel($order->payment_method ?: 'Not set') }}</span><small class="om-payment om-payment--{{ $order->payment_status }}">{{ $statusLabel($order->payment_status) }}</small></td>
                        <td><span class="om-status om-status--{{ $order->status }}">{{ $statusLabel($order->status) }}</span></td>
                        <td><span class="om-fulfil om-fulfil--{{ $order->fulfillment_status ?: 'pending' }}">{{ $statusLabel($order->fulfillment_status ?: 'pending') }}</span></td>
                        <td><div class="om-row-actions"><a title="View order" href="{{ route('admin.order-master.show',[$order->order_type,$order]) }}"><x-icon name="eye" size="14" /></a><a title="Edit order" href="{{ route('admin.order-master.show',[$order->order_type,$order]) }}"><x-icon name="pencil" size="14" /></a><a title="Invoice" href="{{ route('admin.order-master.invoice',[$order->order_type,$order]) }}"><x-icon name="dots" size="14" /></a></div></td>
                    </tr>
                @empty
                    <tr><td class="om-empty" colspan="10"><x-icon name="shopping-bag" size="28" /><strong>No orders found</strong><span>Adjust the filters or create a new order.</span></td></tr>
                @endforelse
                </tbody></table></div>
                <div class="om-pagination-row">
                    <span>Showing {{ number_format($orders->firstItem() ?? 0) }} to {{ number_format($orders->lastItem() ?? 0) }} of {{ number_format($orders->total()) }} orders</span>
                    <div class="om-pagination">{{ $orders->onEachSide(1)->links() }}</div>
                    <form method="get" action="{{ route('admin.order-master.overview') }}">@foreach(request()->except('per_page','page') as $key=>$value)@if(is_array($value))@foreach($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach @else <input type="hidden" name="{{ $key }}" value="{{ $value }}"> @endif @endforeach<label>Rows per page<select name="per_page" onchange="this.form.submit()">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',10)===$size)>{{ $size }}</option>@endforeach</select></label></form>
                </div>
            </section>

            <section class="om-report-grid">
                <article class="om-report-card"><h3>Orders by Category <span>(This Month)</span></h3><div class="om-donut-layout"><div class="om-donut" style="--donut:conic-gradient({{ implode(',', $categoryStops) }});"><strong>{{ number_format($summary['total_orders']) }}</strong><span>Total</span></div><div class="om-legend">@foreach($categoryStats as $stat)<div><i style="--legend:{{ $categoryPalette[$stat['type']] }}"></i><span>{{ $stat['label'] }}</span><b>{{ number_format($stat['count']) }} ({{ number_format(($stat['count']/$orderCount)*100,1) }}%)</b></div>@endforeach</div></div><a href="{{ route('admin.resource','sales-reports') }}">View Full Report <x-icon name="arrow-right" size="15" /></a></article>
                <article class="om-report-card"><h3>Top Selling Products <span>(All Orders)</span></h3><div class="om-products-list">@forelse($topProducts as $product)<div><b>{{ $loop->iteration }}</b><span class="om-product-thumb">{{ strtoupper(substr($product->name,0,1)) }}</span><span><strong>{{ $product->name }}</strong><small>SKU: {{ $product->sku ?: '—' }}</small></span><em>{{ number_format((int)$product->quantity_sum) }}</em><strong>{{ $money($product->revenue_sum) }}</strong></div>@empty<p class="om-muted">No sold products yet.</p>@endforelse</div><a href="{{ route('admin.resource','customer-order-reports') }}">View All Products Report <x-icon name="arrow-right" size="15" /></a></article>
                <article class="om-report-card"><h3>Order Value by Category <span>(This Month)</span></h3><div class="om-value-bars">@foreach($monthlyValue->sortByDesc('value') as $stat)<div><span>{{ $stat['label'] }}</span><i><b style="width:{{ min(100,($stat['value']/$maxMonthly)*100) }}%;background:{{ $categoryPalette[$stat['type']] }}"></b></i><strong>{{ $money($stat['value']) }}</strong></div>@endforeach</div><a href="{{ route('admin.resource','sales-reports') }}">View Sales Report <x-icon name="arrow-right" size="15" /></a></article>
                <article class="om-report-card"><h3>Order Status Overview <span>(All Orders)</span></h3><div class="om-donut-layout"><div class="om-donut" style="--donut:conic-gradient({{ implode(',', $statusStops) }});"><strong>{{ number_format($summary['total_orders']) }}</strong><span>Total</span></div><div class="om-legend">@foreach($statusCounts as $key=>$count)<div><i style="--legend:{{ $statusPalette[$key] }}"></i><span>{{ $tabs[$key==='return_refund'?'returns':$key] ?? $statusLabel($key) }}</span><b>{{ number_format($count) }} ({{ number_format(($count/$orderCount)*100,1) }}%)</b></div>@endforeach</div></div><a href="{{ route('admin.resource','customer-order-reports') }}">View All Statuses Report <x-icon name="arrow-right" size="15" /></a></article>
            </section>
        </main>

        <aside class="om-side">
            <section class="om-side-card"><h2>ORDER MASTER SUMMARY</h2><dl><div><dt>Total Orders</dt><dd>{{ number_format($summary['total_orders']) }}</dd></div><div><dt>Total Order Value</dt><dd>{{ $money($summary['total_value']) }}</dd></div><div><dt>Average Order Value</dt><dd>{{ $money($summary['average_value']) }}</dd></div><div><dt>Orders in Progress</dt><dd>{{ number_format($summary['in_progress']) }}</dd></div><div><dt>Pending Approvals / Payments</dt><dd>{{ number_format($summary['pending']) }}</dd></div><div><dt>Shipped Orders</dt><dd>{{ number_format($summary['shipped']) }}</dd></div><div><dt>Delivered Orders</dt><dd>{{ number_format($summary['delivered']) }}</dd></div><div><dt>Cancelled Orders</dt><dd>{{ number_format($summary['cancelled']) }}</dd></div><div><dt>Return / Refund Requests</dt><dd>{{ number_format($summary['returns']) }}</dd></div><div><dt>Conversion Rate (Orders/Visits)</dt><dd>{{ number_format($summary['conversion'],2) }}%</dd></div></dl></section>
            <section class="om-side-card"><h2>QUICK ACTIONS</h2><div class="om-quick-actions"><button type="button" data-modal-open="create"><x-icon name="plus" size="14" />Create New Order</button><button type="button" data-modal-open="import"><x-icon name="upload" size="14" />Upload Orders (CSV)</button><button type="button" data-modal-open="import"><x-icon name="download" size="14" />Import Orders</button><button type="button" data-advanced-toggle><x-icon name="settings" size="14" />Manage Order Statuses</button><a href="{{ route('admin.discounts-coupons') }}"><x-icon name="star" size="14" />Apply Discount / Coupon</a><button type="button" data-print-page><x-icon name="file-text" size="14" />Print / Download Invoices</button><a href="{{ route('admin.resource','audit-logs') }}"><x-icon name="file-text" size="14" />Order Audit Log</a></div></section>
            <section class="om-side-card"><h2>ORDER NOTIFICATIONS <a href="{{ route('admin.order-master.overview') }}">View All</a></h2><div class="om-notifications">@foreach($notifications as $notice)<div><i class="om-notice-dot om-notice-dot--{{ $notice['tone'] }}"></i><span>{{ $notice['text'] }}</span><time>{{ $notice['time'] }}</time></div>@endforeach</div></section>
        </aside>
    </div>
</div>

<div class="om-modal" data-modal="create" aria-hidden="true"><div class="om-modal-backdrop" data-modal-close></div><section class="om-modal-card" role="dialog" aria-modal="true" aria-labelledby="om-create-title"><header><div><span>ORDER MANAGEMENT</span><h2 id="om-create-title">Create New Order</h2></div><button type="button" data-modal-close aria-label="Close">×</button></header><form method="post" action="{{ route('admin.order-master.store') }}">@csrf<div class="om-form-grid"><label>Order Category<select name="order_type" required>@foreach($typeMeta as $key=>$meta)<option value="{{ $key }}">{{ $meta['label'] }}</option>@endforeach</select></label><label>Customer / Company<input name="customer_name" required placeholder="Customer or company name"></label><label>Email<input type="email" name="email" required placeholder="customer@example.com"></label><label>Phone<input name="phone" placeholder="+353 ..."></label><label>Subtotal (€)<input type="number" step="0.01" min="0" name="subtotal" value="0" required></label><label>Shipping (€)<input type="number" step="0.01" min="0" name="shipping" value="0"></label><label>Discount (€)<input type="number" step="0.01" min="0" name="discount" value="0"></label><label>Payment Method<input name="payment_method" placeholder="Card, PayPal, Bank Transfer..."></label><label>Order Status<select name="status">@foreach($orderStatuses as $status)<option value="{{ $status }}" @selected($status==='pending')>{{ $statusLabel($status) }}</option>@endforeach</select></label><label>Payment Status<select name="payment_status">@foreach($paymentStatuses as $status)<option value="{{ $status }}" @selected($status==='pending')>{{ $statusLabel($status) }}</option>@endforeach</select></label><label>Fulfilment<select name="fulfillment_status">@foreach($fulfillmentStatuses as $status)<option value="{{ $status }}" @selected($status==='pending')>{{ $statusLabel($status) }}</option>@endforeach</select></label><label class="om-form-wide">Notes<textarea name="notes" rows="3" placeholder="Internal order notes"></textarea></label></div><footer><button type="button" class="om-btn om-btn--soft" data-modal-close>Cancel</button><button class="om-btn om-btn--green" type="submit">Create Order</button></footer></form></section></div>

<div class="om-modal" data-modal="import" aria-hidden="true"><div class="om-modal-backdrop" data-modal-close></div><section class="om-modal-card om-modal-card--small" role="dialog" aria-modal="true" aria-labelledby="om-import-title"><header><div><span>CSV IMPORT</span><h2 id="om-import-title">Import Orders</h2></div><button type="button" data-modal-close aria-label="Close">×</button></header><form method="post" enctype="multipart/form-data" action="{{ route('admin.order-master.import') }}">@csrf<label class="om-dropzone"><x-icon name="upload" size="28" /><strong>Choose a CSV file</strong><span>Headers: number, order_type, customer_name, email, phone, status, payment_status, payment_method, fulfillment_status, subtotal, shipping, discount, total</span><input type="file" name="file" accept=".csv,text/csv" required></label><footer><button type="button" class="om-btn om-btn--soft" data-modal-close>Cancel</button><button class="om-btn om-btn--green" type="submit">Import Orders</button></footer></form></section></div>

@push('scripts')
<script src="/js/order-master.js?v=20260910-order-master-v1" defer></script>
@endpush
@endsection
