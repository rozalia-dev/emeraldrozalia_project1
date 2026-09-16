@extends('layouts.admin')

@section('title', 'Sales Quotes · Conversion Queue')

@section('content')
<div class="admin-page-shell" data-sales-quotes>
    <div class="admin-page-heading">
        <div>
            <p class="admin-eyebrow">Pre-order Intake → Shared Order Engine</p>
            <h1>Sales Quotes &amp; Conversions</h1>
            <p>Corporate and Bulk public quote requests arrive here first and stay linked to their Communication Centre conversation. Price and approve the request, then convert it into the matching Corporate or Bulk Order Master. Franchise applications are managed separately under Franchise Management → Applications &amp; Leads.</p>
        </div>
        <a class="admin-button admin-button--secondary" href="{{ route('admin.order-master.overview') }}">Order Master</a>
    </div>

    @if(session('success'))
        <div class="admin-alert admin-alert--success">{{ session('success') }}</div>
    @endif

    <div class="admin-stat-grid" aria-label="Quote status summary">
        @foreach($statuses as $quoteStatus)
            <a class="admin-stat-card" href="{{ route('admin.quotes.index', ['status' => $quoteStatus, 'order_type' => $orderType, 'q' => $search]) }}">
                <span>{{ str($quoteStatus)->replace('_', ' ')->headline() }}</span>
                <strong>{{ number_format((int) ($statusCounts[$quoteStatus] ?? 0)) }}</strong>
            </a>
        @endforeach
    </div>

    <form method="get" action="{{ route('admin.quotes.index') }}" class="admin-filter-bar">
        <label>Search
            <input type="search" name="q" value="{{ $search }}" placeholder="Name, email, company or quote UUID">
        </label>
        <label>Order type
            <select name="order_type">
                <option value="">All quote types</option>
                @foreach($orderTypes as $type)
                    <option value="{{ $type }}" @selected($orderType === $type)>{{ str($type)->headline() }}</option>
                @endforeach
            </select>
        </label>
        <label>Status
            <select name="status">
                <option value="">All statuses</option>
                @foreach($statuses as $quoteStatus)
                    <option value="{{ $quoteStatus }}" @selected($status === $quoteStatus)>{{ str($quoteStatus)->replace('_', ' ')->headline() }}</option>
                @endforeach
            </select>
        </label>
        <button class="admin-button admin-button--primary" type="submit">Apply filters</button>
        <a class="admin-button admin-button--secondary" href="{{ route('admin.quotes.index') }}">Reset</a>
    </form>

    <section class="admin-card">
        <div class="admin-card-heading">
            <div>
                <h2>Live pre-order quote queue</h2>
                <p>Every row is backed by sales_quotes and traceable to its public enquiry and Communication Centre conversation. A quote is not counted as an Order until conversion succeeds.</p>
            </div>
            <span>{{ number_format($quotes->total()) }} total</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Quote</th>
                        <th>Target Order Master</th>
                        <th>Requester</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Version</th>
                        <th>Converted Order</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($quotes as $quote)
                    <tr>
                        <td><a href="{{ route('admin.quotes.show', $quote) }}">{{ str($quote->uuid)->limit(13, '…') }}</a><small>{{ optional($quote->submitted_at)->format('d M Y H:i') }}</small></td>
                        <td>{{ str($quote->order_type)->headline() }} Orders @if($quote->order_type === 'franchise')<small>Legacy/manual franchise quote</small>@endif</td>
                        <td><strong>{{ $quote->inquiry?->company ?: $quote->inquiry?->name ?: 'Unassigned requester' }}</strong><small>{{ $quote->inquiry?->email ?: 'No email' }}</small></td>
                        <td><strong>€{{ number_format((float) $quote->total, 2) }}</strong><small>{{ $quote->currency_code }}</small></td>
                        <td><span class="admin-status admin-status--{{ str($quote->status)->slug() }}">{{ str($quote->status)->replace('_', ' ')->headline() }}</span></td>
                        <td>{{ $quote->version }}</td>
                        <td>@if($quote->order)<a href="{{ route('admin.order-master.show', [$quote->order->order_type, $quote->order]) }}">{{ $quote->order->number }}</a>@else<span>Not converted</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="admin-empty-state"><strong>No quotes match the current filters.</strong><span>Public Corporate and Bulk quote requests will appear here automatically and remain linked to their Communication Centre conversations.</span></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $quotes->links() }}
    </section>
</div>
@endsection
