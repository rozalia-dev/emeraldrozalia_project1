@extends('layouts.admin')

@section('title', $label.' · Order Master')

@section('content')
    <div class="page-head">
        <div><small>ORDER MANAGEMENT · {{ strtoupper($type) }}</small><h1>{{ $label }}</h1><p>Isolated workflow, lifecycle and ledger view for this order type.</p></div>
        <a class="btn" href="{{ route('admin.dashboard') }}">← Dashboard</a>
    </div>

    <div class="order-master-metrics">
        <div><small>Total orders</small><strong>{{ $metrics['total'] }}</strong><span>All recorded in this master</span></div>
        <div><small>Open workflow</small><strong>{{ $metrics['open'] }}</strong><span>Pending, approved or processing</span></div>
        <div><small>Paid orders</small><strong>{{ $metrics['paid'] }}</strong><span>Payment ledger confirmed</span></div>
        <div><small>Paid revenue</small><strong>€{{ number_format($metrics['revenue'], 2) }}</strong><span>Recorded paid totals</span></div>
        <div><small>Open returns</small><strong>{{ $metrics['returns'] }}</strong><span>Awaiting review or receipt</span></div>
    </div>

    <section class="admin-card order-master-card">
        <form class="toolbar order-master-filters" method="get">
            <input name="q" value="{{ request('q') }}" placeholder="Order number or email" aria-label="Search order number or email">
            <select name="status" aria-label="Filter by order status">
                <option value="">All order statuses</option>
                @foreach(['pending','approved','processing','shipped','completed','cancelled','refunded'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                @endforeach
            </select>
            <select name="payment_status" aria-label="Filter by payment status">
                <option value="">All payment statuses</option>
                @foreach(['unpaid','pending','pay_on_delivery','paid','failed','refunded'] as $status)
                    <option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ str($status)->headline() }}</option>
                @endforeach
            </select>
            <label>From <input type="date" name="date_from" value="{{ request('date_from') }}"></label>
            <label>To <input type="date" name="date_to" value="{{ request('date_to') }}"></label>
            <button type="submit">Filter</button>
            @if(request()->query())<a class="clear-filter" href="{{ route('admin.order-master', $type) }}">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="order-master-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Created</th><th>Order state</th><th>Payment</th><th>Returns</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td><a class="order-number" href="{{ route('admin.order-master.show', [$type, $order]) }}">{{ $order->number }}</a><small>{{ str($type)->headline() }}</small></td>
                            <td>{{ $order->user?->name ?: 'Guest customer' }}<small>{{ $order->email }}</small></td>
                            <td><strong>{{ $order->currency_code ?: $order->currency }} {{ number_format((float) $order->total, 2) }}</strong></td>
                            <td>{{ $order->created_at?->format('d M Y H:i') }}</td>
                            <td><span class="admin-status status-{{ str($order->status)->slug() }}">{{ str($order->status)->headline() }}</span></td>
                            <td><span class="admin-status payment-{{ str($order->payment_status)->slug() }}">{{ str($order->payment_status)->headline() }}</span></td>
                            <td>{{ $order->returns_count }}</td>
                            <td>
                                <div class="order-actions">
                                    <a href="{{ route('admin.order-master.show', [$type, $order]) }}">Details</a>
                                    <a href="{{ route('admin.order-master.invoice', [$type, $order]) }}">Invoice</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-note">No orders in this master for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </section>
@endsection
