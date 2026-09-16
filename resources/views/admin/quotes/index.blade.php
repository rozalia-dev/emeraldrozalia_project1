@extends('layouts.admin')

@section('title', 'Sales Quotes · Conversion Queue')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-quotes.css?v=20260916-2') }}">
@endpush

@section('content')
<div class="quotes-page" data-sales-quotes>
    <nav class="quotes-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Project 1 Control Panel</a>
        <x-icon name="chevron-right" size="11" />
        <a href="{{ route('admin.order-master.overview') }}">Order Management</a>
        <x-icon name="chevron-right" size="11" />
        <strong>Sales Quotes / Conversions</strong>
    </nav>

    <header class="quotes-hero">
        <div class="quotes-hero-copy">
            <span class="quotes-kicker">PRE-ORDER INTAKE → SHARED ORDER ENGINE</span>
            <h1>Sales Quotes &amp; Conversions</h1>
            <p>Corporate and Bulk requests arrive here as pre-orders. The Communication Center keeps the conversation; this queue owns pricing, approval and conversion into the correct Order Master.</p>
        </div>
        <div class="quotes-hero-actions">
            <a class="quotes-btn quotes-btn--soft" href="{{ route('admin.order-master.overview') }}"><x-icon name="shopping-bag" size="14" /> Order Master</a>
        </div>
    </header>

    @if(session('success'))
        <div class="quotes-alert quotes-alert--success"><x-icon name="check" size="16" /><span>{{ session('success') }}</span></div>
    @endif

    <section class="quotes-flow" aria-label="Quote conversion workflow">
        <div class="quotes-flow-copy">
            <strong>Admin conversion workflow</strong>
            <span>A quote becomes an Order only after pricing and approval.</span>
        </div>
        <ol>
            <li><span>1</span><div><b>Review &amp; Price</b><small>Add products, quantity and negotiated price.</small></div></li>
            <li><span>2</span><div><b>Approve Quote</b><small>Lock the commercial terms for conversion.</small></div></li>
            <li><span>3</span><div><b>Convert to Order</b><small>Create the Corporate or Bulk Order Master record.</small></div></li>
        </ol>
    </section>

    <section class="quotes-kpis" aria-label="Quote status summary">
        @foreach($statuses as $quoteStatus)
            @php
                $tone = match($quoteStatus) {
                    'submitted' => 'blue',
                    'approved' => 'green',
                    'rejected' => 'red',
                    'cancelled' => 'slate',
                    'converted' => 'purple',
                    default => 'slate',
                };
            @endphp
            <a class="quotes-kpi quotes-kpi--{{ $tone }} {{ $status === $quoteStatus ? 'is-active' : '' }}" href="{{ route('admin.quotes.index', array_filter(['status' => $quoteStatus, 'order_type' => $orderType, 'q' => $search])) }}">
                <span class="quotes-kpi-icon"><x-icon name="{{ $quoteStatus === 'converted' ? 'refresh' : ($quoteStatus === 'approved' ? 'check' : ($quoteStatus === 'rejected' ? 'alert' : 'file-text')) }}" size="16" /></span>
                <span><small>{{ str($quoteStatus)->replace('_', ' ')->headline() }}</small><strong>{{ number_format((int) ($statusCounts[$quoteStatus] ?? 0)) }}</strong></span>
            </a>
        @endforeach
    </section>

    <form method="get" action="{{ route('admin.quotes.index') }}" class="quotes-filters">
        <label class="quotes-search"><x-icon name="search" size="14" /><input type="search" name="q" value="{{ $search }}" placeholder="Search name, email, company or quote UUID" aria-label="Search quotes"></label>
        <label><span>Order type</span><select name="order_type"><option value="">Corporate + Bulk</option>@foreach($orderTypes as $type)<option value="{{ $type }}" @selected($orderType === $type)>{{ str($type)->headline() }} Orders</option>@endforeach</select></label>
        <label><span>Status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $quoteStatus)<option value="{{ $quoteStatus }}" @selected($status === $quoteStatus)>{{ str($quoteStatus)->replace('_', ' ')->headline() }}</option>@endforeach</select></label>
        <button class="quotes-btn quotes-btn--primary" type="submit"><x-icon name="filter" size="13" /> Apply filters</button>
        <a class="quotes-reset" href="{{ route('admin.quotes.index') }}"><x-icon name="refresh" size="12" /> Reset</a>
    </form>

    <section class="quotes-card">
        <header class="quotes-card-head">
            <div><h2>Live pre-order quote queue</h2><p>{{ number_format($quotes->total()) }} Corporate/Bulk quote{{ $quotes->total() === 1 ? '' : 's' }}. Franchise applications are managed separately in Franchise Management.</p></div>
            <span class="quotes-live"><i></i> Live data</span>
        </header>

        <div class="quotes-table-wrap">
            <table class="quotes-table">
                <thead>
                    <tr>
                        <th>QUOTE</th>
                        <th>TARGET ORDER MASTER</th>
                        <th>REQUESTER</th>
                        <th>VALUE</th>
                        <th>STATUS</th>
                        <th>SUBMITTED</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($quotes as $quote)
                    <tr>
                        <td>
                            <a class="quotes-quote-id" href="{{ route('admin.quotes.show', $quote) }}">{{ str($quote->uuid)->limit(13, '…') }}</a>
                            <small>v{{ $quote->version }}</small>
                        </td>
                        <td><span class="quotes-master quotes-master--{{ str($quote->order_type)->slug() }}">{{ str($quote->order_type)->headline() }} Orders</span></td>
                        <td><strong>{{ $quote->inquiry?->company ?: $quote->inquiry?->name ?: 'Unassigned requester' }}</strong><small>{{ $quote->inquiry?->email ?: 'No email' }}</small></td>
                        <td><strong>€{{ number_format((float) $quote->total, 2) }}</strong><small>{{ $quote->currency_code }}</small></td>
                        <td><span class="quotes-status quotes-status--{{ str($quote->status)->slug() }}">{{ str($quote->status)->replace('_', ' ')->headline() }}</span></td>
                        <td><strong>{{ optional($quote->submitted_at)->format('d M Y') ?: '—' }}</strong><small>{{ optional($quote->submitted_at)->format('H:i') ?: '' }}</small></td>
                        <td>
                            <div class="quotes-row-actions">
                                @if($quote->order)
                                    <a class="quotes-btn quotes-btn--primary quotes-btn--sm" href="{{ route('admin.order-master.show', [$quote->order->order_type, $quote->order]) }}"><x-icon name="shopping-bag" size="12" /> Open Order</a>
                                @elseif($quote->status === 'approved')
                                    <form method="post" action="{{ route('admin.quotes.convert', $quote) }}" onsubmit="return confirm('Convert this approved quote into the {{ str($quote->order_type)->headline() }} Order Master?')">
                                        @csrf
                                        <input type="hidden" name="expected_version" value="{{ $quote->version }}">
                                        <input type="hidden" name="idempotency_key" value="admin-quote-{{ $quote->uuid }}">
                                        <button class="quotes-btn quotes-btn--primary quotes-btn--sm" type="submit"><x-icon name="refresh" size="12" /> Convert to Order</button>
                                    </form>
                                    <a class="quotes-icon-btn" href="{{ route('admin.quotes.show', $quote) }}" title="Review quote" aria-label="Review quote"><x-icon name="eye" size="14" /></a>
                                @elseif($quote->status === 'submitted')
                                    <a class="quotes-btn quotes-btn--primary quotes-btn--sm" href="{{ route('admin.quotes.show', $quote) }}"><x-icon name="pencil" size="12" /> Review &amp; Price</a>
                                @else
                                    <a class="quotes-btn quotes-btn--soft quotes-btn--sm" href="{{ route('admin.quotes.show', $quote) }}"><x-icon name="eye" size="12" /> View</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="quotes-empty"><span><x-icon name="file-text" size="22" /></span><strong>No quotes match the current filters.</strong><p>New Corporate and Bulk public requests will appear here automatically.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <footer class="quotes-card-foot">
            <span>Showing {{ $quotes->firstItem() ?? 0 }}–{{ $quotes->lastItem() ?? 0 }} of {{ number_format($quotes->total()) }}</span>
            <div class="quotes-pagination">{{ $quotes->links() }}</div>
        </footer>
    </section>
</div>
@endsection
