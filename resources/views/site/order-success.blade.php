@extends('layouts.site')

@section('title', 'Order Confirmed — Emerald Rozalia')

@php($orderCurrency = \App\Models\Currency::query()->whereKey($order->currency_code ?: $order->currency ?: 'EUR')->first())
@php($orderMoney = fn ($amount) => $orderCurrency?->format($amount) ?? (($order->currency_code ?: $order->currency ?: 'EUR').' '.number_format((float)$amount, 2)))

@section('content')
    <section class="page-hero checkout-hero">
        <span class="eyebrow">ORDER RECEIVED</span>
        <h1>Thank You</h1>
        <p>Your order <strong>{{ $order->number }}</strong> has been created and is now visible in your account.</p>
    </section>

    <section class="order-success-card">
        <h2>Order confirmed</h2>
        <p>We’ve recorded your order using {{ str($order->payment_method ?: 'your selected payment method')->replace('_', ' ') }}. Payment status: <strong>{{ str($order->payment_status)->headline() }}</strong>.</p>
        <p><small>Transaction currency: <strong>{{ $order->currency_code ?: $order->currency ?: 'EUR' }}</strong> · Exchange-rate snapshot: {{ number_format((float)($order->exchange_rate ?: 1), 8) }}</small></p>
        <div class="order-success-totals">
            <p><span>Subtotal</span><strong>{{ $orderMoney($order->subtotal) }}</strong></p>
            <p><span>Shipping</span><strong>{{ $orderMoney($order->shipping) }}</strong></p>
            <p><span>Discount</span><strong>−{{ $orderMoney($order->discount) }}</strong></p>
            <p><span>Total</span><strong>{{ $orderMoney($order->total) }}</strong></p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('account.section', 'orders') }}">VIEW MY ORDERS</a>
            <a class="btn ghost" href="{{ route('shop') }}">CONTINUE SHOPPING</a>
        </div>
    </section>
@endsection
