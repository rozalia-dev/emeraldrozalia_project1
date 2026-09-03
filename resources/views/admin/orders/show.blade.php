@extends('layouts.admin')

@section('title', $order->number.' · '.$label)

@section('content')
    <div class="page-head">
        <div><small>{{ strtoupper($label) }} · ORDER DETAIL</small><h1>{{ $order->number }}</h1><p>Customer-owned data, fulfilment state and payment/return history.</p></div>
        <a class="btn" href="{{ route('admin.order-master', $type) }}"><x-icon name="arrow-left" /> Back to {{ $label }}</a>
    </div>

    <div class="order-detail-layout">
        <div class="order-detail-main">
            <section class="admin-card">
                <div class="order-detail-heading"><div><h2>Order items</h2><span>Created {{ $order->created_at?->format('d M Y H:i') }}</span></div><span class="admin-status status-{{ str($order->status)->slug() }}">{{ str($order->status)->headline() }}</span></div>
                <div class="table-wrap">
                    <table class="order-master-table">
                        <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Unit price</th><th class="right">Total</th></tr></thead>
                        <tbody>
                            @forelse($order->items as $item)
                                <tr><td>{{ $item->name }} @if($item->variant)<small>{{ $item->variant->colour }} / {{ $item->variant->size }}</small>@endif</td><td>{{ $item->sku }}</td><td>{{ $item->quantity }}</td><td>€{{ number_format((float) $item->unit_price, 2) }}</td><td class="right">€{{ number_format((float) $item->total, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="empty-note">No order items recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="order-detail-totals"><p><span>Subtotal</span><strong>€{{ number_format((float) $order->subtotal, 2) }}</strong></p><p><span>Shipping</span><strong>€{{ number_format((float) $order->shipping, 2) }}</strong></p><p><span>Discount</span><strong>−€{{ number_format((float) $order->discount, 2) }}</strong></p><p><span>Total</span><strong>€{{ number_format((float) $order->total, 2) }}</strong></p></div>
            </section>

            <section class="admin-card">
                <div class="order-detail-heading"><div><h2>Payment ledger</h2><span>Provider-neutral transaction history</span></div><span class="admin-status payment-{{ str($order->payment_status)->slug() }}">{{ str($order->payment_status)->headline() }}</span></div>
                <div class="table-wrap"><table class="order-master-table"><thead><tr><th>Recorded</th><th>Provider / method</th><th>Status</th><th class="right">Amount</th></tr></thead><tbody>@forelse($order->payments as $payment)<tr><td>{{ $payment->created_at?->format('d M Y H:i') }}</td><td>{{ str($payment->provider)->headline() }}</td><td>{{ str($payment->status)->headline() }}</td><td class="right">€{{ number_format((float) $payment->amount, 2) }}</td></tr>@empty<tr><td colspan="4">No payment ledger entries.</td></tr>@endforelse</tbody></table></div>
            </section>

            <section class="admin-card">
                <div class="order-detail-heading"><div><h2>Returns and exchanges</h2><span>Customer requests linked to this order</span></div></div>
                <div class="table-wrap"><table class="order-master-table"><thead><tr><th>Request</th><th>Type</th><th>Reason</th><th>Status</th><th>Created</th></tr></thead><tbody>@forelse($order->returns as $return)<tr><td>{{ $return->number }}</td><td>{{ str($return->type)->headline() }}</td><td>{{ $return->reason }}</td><td><span class="admin-status status-{{ str($return->status)->slug() }}">{{ str($return->status)->headline() }}</span></td><td>{{ $return->created_at?->format('d M Y') }}</td></tr>@empty<tr><td colspan="5">No returns or exchanges.</td></tr>@endforelse</tbody></table></div>
            </section>
        </div>

        <aside class="order-detail-side">
            <section class="admin-card">
                <h2>Lifecycle update</h2>
                <form class="order-update-form" method="post" action="{{ route('admin.order-master.update', [$type, $order]) }}">
                    @csrf @method('PATCH')
                    <label>Order status<select name="status">@foreach(['pending','approved','processing','shipped','completed','cancelled','refunded'] as $status)<option value="{{ $status }}" @selected($order->status === $status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
                    <label>Payment status<select name="payment_status">@foreach(['unpaid','pending','pay_on_delivery','paid','failed','refunded'] as $status)<option value="{{ $status }}" @selected($order->payment_status === $status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
                    <button class="btn" type="submit">SAVE LIFECYCLE STATE</button>
                </form>
                <a class="admin-document-link" href="{{ route('admin.order-master.invoice', [$type, $order]) }}">PRINT ADMIN INVOICE <x-icon name="arrow-right" /></a>
            </section>

            <section class="admin-card order-customer-card">
                <h2>Customer</h2>
                <p><strong>{{ $order->user?->name ?: 'Guest customer' }}</strong><br>{{ $order->email }}<br>{{ $order->phone }}</p>
                <h3>Delivery address</h3>
                <p>{{ data_get($order->shipping_address, 'name') }}<br>{{ data_get($order->shipping_address, 'line1') }}<br>{{ data_get($order->shipping_address, 'city') }} {{ data_get($order->shipping_address, 'postcode') }}<br>{{ data_get($order->shipping_address, 'country') }}</p>
            </section>

            <section class="admin-card">
                <h2>Order metadata</h2>
                <dl class="order-metadata"><div><dt>Type</dt><dd>{{ str($order->order_type)->headline() }}</dd></div><div><dt>Currency</dt><dd>{{ $order->currency_code ?: $order->currency }}</dd></div><div><dt>Shipping</dt><dd>{{ $order->shipping_method ?: 'Configured standard rate' }}</dd></div><div><dt>Payment choice</dt><dd>{{ str($order->payment_method ?: 'manual')->headline() }}</dd></div></dl>
            </section>
        </aside>
    </div>
@endsection
