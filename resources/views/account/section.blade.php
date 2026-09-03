@extends('layouts.site')

@php
    $navigation = [
        'orders' => 'My Orders',
        'wishlist' => 'Wishlist',
        'rewards' => 'Rewards & Points',
        'addresses' => 'Address Book',
        'payments' => 'Payment Methods',
        'profile' => 'Account Details',
        'designs' => 'Custom Designs',
        'bulk-orders' => 'Bulk Orders',
        'returns' => 'Returns / Exchanges',
    ];
@endphp

@section('title', ucwords(str_replace('-', ' ', $section)).' — Emerald Rozalia')

@section('content')
    <section class="page-hero">
        <span class="eyebrow">CUSTOMER DASHBOARD</span>
        <h1>{{ ucwords(str_replace('-', ' ', $section)) }}</h1>
    </section>

    <section class="account-wrap">
        <aside class="account-nav" aria-label="Account navigation">
            <a href="{{ route('account.dashboard') }}">Dashboard</a>
            @foreach($navigation as $key => $label)
                <a class="{{ $section === $key ? 'is-active' : '' }}" href="{{ route('account.section', $key) }}">{{ $label }}</a>
            @endforeach
        </aside>

        <div class="account-main">
            @if($section === 'orders')
                @forelse($orders as $order)
                    @php($payment = $order->payments->sortByDesc('created_at')->first())
                    <article class="panel">
                        <h3>{{ $order->number }}</h3>
                        <p>Status: <strong>{{ str($order->status)->headline() }}</strong> · Payment: <strong>{{ str($order->payment_status)->headline() }}</strong> · Total €{{ number_format((float) $order->total, 2) }}</p>
                        @if($payment)
                            <p class="account-muted">Latest payment ledger entry: {{ str($payment->status)->headline() }} · {{ $payment->provider }} · €{{ number_format((float) $payment->amount, 2) }}</p>
                        @endif
                        <div class="account-actions">
                            <a class="btn" href="{{ route('account.invoice', $order) }}">VIEW / PRINT INVOICE</a>
                            @if(in_array($order->status, ['shipped', 'completed'], true))
                                <form method="post" action="{{ route('account.return.store', $order) }}">
                                    @csrf
                                    <select name="type" aria-label="Request type"><option value="return">Return</option><option value="exchange">Exchange</option></select>
                                    <input name="reason" placeholder="Reason" required>
                                    <input name="details" placeholder="Details (optional)">
                                    <button type="submit">SUBMIT REQUEST</button>
                                </form>
                            @else
                                <span class="account-muted">Returns open after dispatch.</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <p>No orders yet.</p>
                @endforelse
            @elseif($section === 'payments')
                <section class="panel">
                    <h2>Payment ledger</h2>
                    <p class="account-muted">Every payment state is recorded against its customer-owned order. Card details are never stored here.</p>
                    @forelse($payments as $payment)
                        <div class="order-row">
                            <span>{{ $payment->order->number }}</span>
                            <span>{{ str($payment->provider)->headline() }}</span>
                            <span>{{ str($payment->status)->headline() }}</span>
                            <strong>€{{ number_format((float) $payment->amount, 2) }}</strong>
                        </div>
                    @empty
                        <p>No payment transactions yet.</p>
                    @endforelse
                </section>
            @elseif($section === 'wishlist')
                @forelse($wishlist as $item)
                    <article class="panel"><a href="{{ route('product', $item->product) }}"><h3>{{ $item->product->name }}</h3></a><p>€{{ number_format((float) $item->product->price, 2) }}</p><form method="post" action="{{ route('wishlist.toggle', $item->product) }}">@csrf<button type="submit">Remove</button></form></article>
                @empty
                    <p>Your wishlist is empty.</p>
                @endforelse
            @elseif($section === 'rewards')
                <h2>Balance: {{ $rewards->sum('points') }} points</h2>
                @foreach($rewards as $reward)
                    <div class="order-row"><span>{{ $reward->created_at?->format('d M Y') }}</span><span>{{ $reward->description }}</span><strong>{{ $reward->points }}</strong></div>
                @endforeach
            @elseif($section === 'addresses')
                <div class="cards">
                    @foreach($addresses as $address)
                        <article class="panel"><h3>{{ $address->label }} {{ $address->is_default ? '· Default' : '' }}</h3><p>{{ $address->name }}<br>{{ $address->line1 }}<br>{{ $address->city }} {{ $address->postcode }}</p><form method="post" action="{{ route('account.address.delete', $address) }}">@csrf @method('DELETE')<button type="submit">Remove</button></form></article>
                    @endforeach
                </div>
                <h2>Add Address</h2>
                <form class="profile-form" method="post" action="{{ route('account.address.store') }}">@csrf<input name="label" value="Home" required><input name="name" placeholder="Name" required><input name="phone" placeholder="Phone"><input name="line1" placeholder="Address line 1" required><input name="line2" placeholder="Address line 2"><input name="city" placeholder="City" required><input name="county" placeholder="County"><input name="postcode" placeholder="Postcode"><input name="country" value="IE" maxlength="2" required><label><input type="checkbox" name="is_default" value="1"> Default</label><button class="btn" type="submit">SAVE ADDRESS</button></form>
            @elseif($section === 'profile')
                <form class="profile-form" method="post" action="{{ route('account.profile') }}">@csrf @method('PATCH')<label>Name<input name="name" value="{{ auth()->user()->name }}" required></label><label>Email<input value="{{ auth()->user()->email }}" disabled></label><label>Phone<input name="phone" value="{{ auth()->user()->phone }}"></label><button class="btn" type="submit">UPDATE PROFILE</button></form>
            @elseif($section === 'designs')
                <h2>Custom Designs</h2><p>Your saved hat customisation projects will appear here when a design configurator is connected.</p><a class="btn" href="{{ route('corporate.orders') }}">START A CUSTOM ORDER</a>
            @elseif($section === 'bulk-orders')
                <h2>Bulk Orders</h2><p>Use your account to request and track corporate and bulk enquiries.</p><a class="btn" href="{{ route('bulk.orders') }}">REQUEST BULK QUOTE</a>
            @elseif($section === 'returns')
                <h2>Returns and exchanges</h2>
                @forelse($returns as $return)
                    <div class="order-row"><span>{{ $return->number }}</span><span>{{ $return->order->number }}</span><span>{{ str($return->type)->headline() }}</span><strong>{{ str($return->status)->headline() }}</strong></div>
                @empty
                    <p>No return or exchange requests.</p>
                @endforelse
            @endif
        </div>
    </section>
@endsection
