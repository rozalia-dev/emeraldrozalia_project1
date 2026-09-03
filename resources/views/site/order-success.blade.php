@extends('layouts.site')

@section('title', 'Order Confirmed — Emerald Rozalia')

@section('content')
    <section class="page-hero checkout-hero">
        <span class="eyebrow">ORDER RECEIVED</span>
        <h1>Thank You</h1>
        <p>Your order <strong>{{ $order->number }}</strong> has been created and is now visible in your account.</p>
    </section>

    <section class="order-success-card">
        <h2>Order confirmed</h2>
        <p>We’ve recorded your order using {{ str($order->payment_method ?: 'your selected payment method')->replace('_', ' ') }}. Payment status: <strong>{{ str($order->payment_status)->headline() }}</strong>.</p>
        <div class="order-success-totals">
            <p><span>Subtotal</span><strong>€{{ number_format((float) $order->subtotal, 2) }}</strong></p>
            <p><span>Shipping</span><strong>€{{ number_format((float) $order->shipping, 2) }}</strong></p>
            <p><span>Discount</span><strong>−€{{ number_format((float) $order->discount, 2) }}</strong></p>
            <p><span>Total</span><strong>€{{ number_format((float) $order->total, 2) }}</strong></p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('account.section', 'orders') }}">VIEW MY ORDERS</a>
            <a class="btn ghost" href="{{ route('shop') }}">CONTINUE SHOPPING</a>
        </div>
    </section>
@endsection
