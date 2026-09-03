@extends('layouts.site')

@section('title', 'Checkout — Emerald Rozalia')

@section('content')
    <section class="page-hero checkout-hero">
        <span class="eyebrow">SECURE CHECKOUT</span>
        <h1>Complete Your Order</h1>
        <p>Your order total is calculated securely from the selected address, delivery method and payment choice.</p>
    </section>

    <section class="checkout-layout" data-checkout data-subtotal="{{ $subtotal }}">
        <form class="checkout-form" method="post" action="{{ route('checkout.store') }}">
            @csrf

            <fieldset class="checkout-fieldset">
                <legend>Delivery Details</legend>
                @if($addresses->isNotEmpty())
                    <label for="address-id">Use a saved address</label>
                    <select id="address-id" name="address_id" data-address-select>
                        <option value="">Enter a new address below</option>
                        @foreach($addresses as $address)
                            <option value="{{ $address->id }}" @selected((string) old('address_id') === (string) $address->id)>
                                {{ $address->label }} — {{ $address->line1 }}, {{ $address->city }}
                            </option>
                        @endforeach
                    </select>
                    <p class="form-hint">Selecting a saved address fills delivery details securely from your account.</p>
                @endif

                <div class="checkout-address-grid">
                    <label for="checkout-name">Full name<input id="checkout-name" name="name" value="{{ old('name', auth()->user()->name) }}" autocomplete="name"></label>
                    <label for="checkout-email">Email<input id="checkout-email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" autocomplete="email" required></label>
                    <label for="checkout-phone">Phone <span>(optional)</span><input id="checkout-phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}" autocomplete="tel"></label>
                    <label for="checkout-line1">Address line 1<input id="checkout-line1" name="line1" value="{{ old('line1') }}" autocomplete="address-line1"></label>
                    <label for="checkout-line2">Address line 2 <span>(optional)</span><input id="checkout-line2" name="line2" value="{{ old('line2') }}" autocomplete="address-line2"></label>
                    <label for="checkout-city">City<input id="checkout-city" name="city" value="{{ old('city') }}" autocomplete="address-level2"></label>
                    <label for="checkout-county">County <span>(optional)</span><input id="checkout-county" name="county" value="{{ old('county') }}" autocomplete="address-level1"></label>
                    <label for="checkout-postcode">Postcode <span>(optional)</span><input id="checkout-postcode" name="postcode" value="{{ old('postcode') }}" autocomplete="postal-code"></label>
                    <label for="checkout-country">Country code<input id="checkout-country" name="country" value="{{ old('country', 'IE') }}" maxlength="2" autocomplete="country" required></label>
                </div>
            </fieldset>

            <fieldset class="checkout-fieldset">
                <legend>Shipping Method</legend>
                <label for="shipping-method">Choose delivery</label>
                <select id="shipping-method" name="shipping_method" data-shipping-select>
                    <option value="" data-price="0">Standard / configured rate</option>
                    @foreach($shippingMethods as $method)
                        <option value="{{ $method->code }}" data-price="{{ $method->price }}" data-free-over="{{ $method->free_over }}" @selected(old('shipping_method') === $method->code)>
                            {{ $method->name }} — €{{ number_format((float) $method->price, 2) }}
                        </option>
                    @endforeach
                </select>
                <p class="form-hint">Delivery is free when the configured order threshold is met.</p>
            </fieldset>

            <fieldset class="checkout-fieldset">
                <legend>Payment</legend>
                <div class="payment-options">
                    <label><input type="radio" name="payment_method" value="cod" @checked(old('payment_method', 'cod') === 'cod')> <span><strong>Pay on delivery</strong><small>Payment remains pending until delivery.</small></span></label>
                    <label><input type="radio" name="payment_method" value="bank_transfer" @checked(old('payment_method') === 'bank_transfer')> <span><strong>Bank transfer</strong><small>Instructions are provided after your order is placed.</small></span></label>
                    <label><input type="radio" name="payment_method" value="manual" @checked(old('payment_method') === 'manual')> <span><strong>Manual payment</strong><small>Use a configured payment gateway hook when enabled.</small></span></label>
                </div>
            </fieldset>

            <div class="checkout-extra">
                <label for="discount-code">Discount code <span>(optional)</span><input id="discount-code" name="discount_code" value="{{ old('discount_code') }}" placeholder="Enter code"></label>
                <label for="checkout-notes">Order notes <span>(optional)</span><textarea id="checkout-notes" name="notes" placeholder="Anything we should know?">{{ old('notes') }}</textarea></label>
            </div>

            <button class="btn checkout-submit" type="submit">PLACE ORDER <x-icon name="arrow-right" /></button>
        </form>

        <aside class="summary-card checkout-summary">
            <h2>Your Order</h2>
            <div class="checkout-items">
                @foreach($items as $item)
                    <p><span>{{ $item['quantity'] }} × {{ $item['name'] }}</span><strong>€{{ number_format($item['price'] * $item['quantity'], 2) }}</strong></p>
                @endforeach
            </div>
            <hr>
            <p><span>Subtotal</span><strong>€{{ number_format($subtotal, 2) }}</strong></p>
            <p><span>Shipping</span><strong data-checkout-shipping>€0.00</strong></p>
            <p><span>Discount</span><strong>Applied securely at checkout</strong></p>
            <hr>
            <p class="summary-total"><span>Estimated total</span><strong data-checkout-total>€{{ number_format($subtotal, 2) }}</strong></p>
            <small class="checkout-secure-note">Your final total is confirmed on the order confirmation page.</small>
        </aside>
    </section>
@endsection
