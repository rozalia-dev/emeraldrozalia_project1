@extends('layouts.site')

@section('title', 'Cart — Emerald Rozalia')

@section('content')
    <section class="page-hero">
        <span class="eyebrow">YOUR BAG</span>
        <h1>Shopping Cart</h1>
        <p>{{ array_sum(array_column($items, 'quantity')) }} item(s) ready for checkout.</p>
    </section>

    <section class="section cart-layout" aria-label="Shopping cart">
        <div class="cart-list">
            @forelse($items as $item)
                <article class="cart-row">
                    <div class="cart-item-image" aria-hidden="true">ER</div>
                    <div class="cart-item-details">
                        <strong>{{ $item['name'] }}</strong>
                        <small>{{ $item['sku'] }}</small>
                        @foreach($item['options'] as $key => $value)
                            <small>{{ ucfirst($key) }}: {{ $value }}</small>
                        @endforeach
                    </div>
                    <div class="cart-item-price">€{{ number_format($item['price'], 2) }}</div>
                    <form method="post" action="{{ route('cart.update', $item['key']) }}" class="cart-quantity-form">
                        @csrf
                        @method('PATCH')
                        <label class="sr-only" for="quantity-{{ md5($item['key']) }}">Quantity for {{ $item['name'] }}</label>
                        <input id="quantity-{{ md5($item['key']) }}" type="number" name="quantity" min="0" max="50" value="{{ $item['quantity'] }}">
                        <button type="submit">Update</button>
                    </form>
                    <form method="post" action="{{ route('cart.remove', $item['key']) }}">
                        @csrf
                        @method('DELETE')
                        <button class="cart-remove" type="submit">Remove</button>
                    </form>
                </article>
            @empty
                <div class="account-empty cart-empty">
                    <strong>Your cart is waiting for something special.</strong>
                    <span>Browse Irish-made hats and caps, then return here when you’re ready.</span>
                    <a class="btn" href="{{ route('shop') }}">BROWSE THE SHOP</a>
                </div>
            @endforelse
        </div>

        <aside class="summary-card cart-summary">
            <h2>Order Summary</h2>
            <p><span>Items ({{ array_sum(array_column($items, 'quantity')) }})</span><strong>€{{ number_format($subtotal, 2) }}</strong></p>
            <p><span>Shipping</span><small>Calculated at checkout</small></p>
            <hr>
            <p class="summary-total"><span>Subtotal</span><strong>€{{ number_format($subtotal, 2) }}</strong></p>
            @if($items)
                <a class="btn" href="{{ route('checkout') }}">PROCEED TO CHECKOUT <span aria-hidden="true">→</span></a>
            @endif
            <a class="cart-continue" href="{{ route('shop') }}">← Continue shopping</a>
        </aside>
    </section>
@endsection
