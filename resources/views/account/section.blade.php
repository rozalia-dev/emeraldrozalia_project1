@extends('layouts.account')

@section('title', ucwords(str_replace('-', ' ', $section)).' — Emerald Rozalia')

@section('account-active', $section)

@section('account-header')
    <div class="account-banner">
        <span class="account-banner-mark" aria-hidden="true"><x-icon name="star" size="20" /></span>
        <div>
            <span class="eyebrow">CUSTOMER DASHBOARD</span>
            <h1>{{ ucwords(str_replace('-', ' ', $section)) }}</h1>
            <p>Manage your Emerald Rozalia account securely in one place.</p>
        </div>
    </div>
@endsection

@section('account-content')
    @if($section === 'orders')
        @forelse($orders as $order)
            @php
                $payment = $order->payments->sortByDesc('created_at')->first();
            @endphp
            <article class="panel">
                <h3>{{ $order->number }}</h3>
                <p>Order type: <strong>{{ str($order->order_type ?: 'online')->headline() }}</strong> · Status: <strong>{{ str($order->status)->headline() }}</strong> · Payment: <strong>{{ str($order->payment_status)->headline() }}</strong> · Total {{ strtoupper($order->currency ?? 'EUR') }} {{ number_format((float) $order->total, 2) }}</p>
                @if($payment)
                    <p class="account-muted">Latest payment ledger entry: {{ str($payment->status)->headline() }} · {{ $payment->provider }} · {{ strtoupper($payment->currency ?? $order->currency ?? 'EUR') }} {{ number_format((float) $payment->amount, 2) }}</p>
                @endif
                <div class="account-actions">
                    <a class="btn" href="{{ route('account.invoice', $order) }}">VIEW / PRINT INVOICE</a>
                    @if(in_array($order->status, ['shipped', 'completed'], true))
                        <form method="post" action="{{ route('account.return.store', $order) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
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
    @endif

    @if(in_array($section, ['corporate-orders', 'bulk-orders'], true))
        @php
            $isCorporate = $section === 'corporate-orders';
            $businessLabel = $isCorporate ? 'Corporate' : 'Bulk';
            $businessQuotes = $isCorporate ? $corporateQuotes : $bulkQuotes;
            $businessOrders = $isCorporate ? $corporateOrders : $bulkOrders;
            $requestUrl = $isCorporate ? route('corporate.orders') : route('bulk.orders');
        @endphp
        <section class="panel">
            <h2>{{ $businessLabel }} Quotes &amp; Orders</h2>
            <p class="account-muted">Requests submitted while signed in with this verified account email are synchronized here. A quote becomes an order only after Emerald Rozalia approves and converts it.</p>
            <a class="btn" href="{{ $requestUrl }}">REQUEST {{ strtoupper($businessLabel) }} QUOTE</a>
        </section>

        <h2>Quote requests</h2>
        @forelse($businessQuotes as $quote)
            <article class="panel">
                <h3>Quote {{ \Illuminate\Support\Str::limit((string) $quote->uuid, 14, '…') }}</h3>
                <p>Status: <strong>{{ str($quote->status)->headline() }}</strong> · Value: <strong>{{ strtoupper($quote->currency_code ?? 'EUR') }} {{ number_format((float) $quote->total, 2) }}</strong> · Submitted: {{ $quote->submitted_at?->format('d M Y') ?? $quote->created_at?->format('d M Y') }}</p>
                @if($quote->order)
                    <div class="account-actions"><a class="btn" href="{{ route('account.invoice', $quote->order) }}">VIEW CONVERTED ORDER</a></div>
                @else
                    <p class="account-muted">This request has not been converted into an order yet.</p>
                @endif
            </article>
        @empty
            <p>No {{ strtolower($businessLabel) }} quote requests are linked to this account yet.</p>
        @endforelse

        <h2>{{ $businessLabel }} Orders</h2>
        @forelse($businessOrders as $order)
            <article class="panel">
                <h3>{{ $order->number }}</h3>
                <p>Status: <strong>{{ str($order->status)->headline() }}</strong> · Payment: <strong>{{ str($order->payment_status)->headline() }}</strong> · Total: <strong>{{ strtoupper($order->currency ?? 'EUR') }} {{ number_format((float) $order->total, 2) }}</strong></p>
                <div class="account-actions"><a class="btn" href="{{ route('account.invoice', $order) }}">VIEW / PRINT INVOICE</a></div>
            </article>
        @empty
            <p>No converted {{ strtolower($businessLabel) }} orders yet.</p>
        @endforelse
    @endif

    @if($section === 'franchise')
        <section class="panel">
            <h2>Franchise Applications &amp; Orders</h2>
            <p class="account-muted">Franchise Apply is an application workflow, not an order. Applications submitted while signed in with this verified account email appear here. Actual franchise supply or retail orders are listed separately once they exist.</p>
            <a class="btn" href="{{ route('franchise') }}">APPLY / VIEW FRANCHISE OPPORTUNITY</a>
        </section>

        <h2>My Franchise Applications</h2>
        @forelse($franchiseApplications as $application)
            @php
                $applicationStatus = $application->status === 'converted' ? 'Active Partner' : str($application->status)->headline();
            @endphp
            <article class="panel">
                <h3>Application {{ \Illuminate\Support\Str::limit((string) $application->uuid, 14, '…') }}</h3>
                <p>Status: <strong>{{ $applicationStatus }}</strong> · Territory: <strong>{{ $application->territory ?: 'Not assigned' }}</strong> · Preferred location: <strong>{{ $application->preferred_location ?: 'Not specified' }}</strong></p>
                <p class="account-muted">Submitted {{ $application->created_at?->format('d M Y') }}. Communication remains linked to the same application in Emerald Rozalia's Communication Centre.</p>
            </article>
        @empty
            <p>No franchise application is linked to this account yet.</p>
        @endforelse

        <h2>Franchise Orders</h2>
        @forelse($franchiseOrders as $order)
            <article class="panel">
                <h3>{{ $order->number }}</h3>
                <p>Order type: <strong>{{ str($order->order_type)->headline() }}</strong> · Status: <strong>{{ str($order->status)->headline() }}</strong> · Payment: <strong>{{ str($order->payment_status)->headline() }}</strong> · Total: <strong>{{ strtoupper($order->currency ?? 'EUR') }} {{ number_format((float) $order->total, 2) }}</strong></p>
                <div class="account-actions"><a class="btn" href="{{ route('account.invoice', $order) }}">VIEW / PRINT INVOICE</a></div>
            </article>
        @empty
            <p>No franchise or franchise retail orders are linked to this account yet.</p>
        @endforelse
    @endif

    @if($section === 'payments')
        <section class="panel">
            <h2>Payment ledger</h2>
            <p class="account-muted">Every payment state is recorded against its customer-owned order. Card details are never stored here.</p>
            @forelse($payments as $payment)
                <div class="order-row">
                    <span>{{ $payment->order->number }}</span>
                    <span>{{ str($payment->provider)->headline() }}</span>
                    <span>{{ str($payment->status)->headline() }}</span>
                    <strong>{{ strtoupper($payment->currency ?? 'EUR') === 'EUR' ? '€' : strtoupper($payment->currency ?? 'EUR').' ' }}{{ number_format((float) $payment->amount, 2) }}</strong>
                </div>
            @empty
                <p>No payment transactions yet.</p>
            @endforelse
        </section>
    @endif

    @if($section === 'wishlist')
        @forelse($wishlist as $item)
            <article class="panel"><a href="{{ route('product', $item->product) }}"><h3>{{ $item->product->name }}</h3></a><p>€{{ number_format((float) $item->product->price, 2) }}</p><form method="post" action="{{ route('wishlist.toggle', $item->product) }}">@csrf<button type="submit">Remove</button></form></article>
        @empty
            <p>Your wishlist is empty.</p>
        @endforelse
    @endif

    @if($section === 'rewards')
        <h2>Balance: {{ $rewards->sum('points') }} points</h2>
        @foreach($rewards as $reward)
            <div class="order-row"><span>{{ $reward->created_at?->format('d M Y') }}</span><span>{{ $reward->description }}</span><strong>{{ $reward->points }}</strong></div>
        @endforeach
    @endif

    @if($section === 'addresses')
        <div class="cards">
            @forelse($addresses as $address)
                <article class="panel"><h3>{{ $address->label }} {{ $address->is_default ? '· Default' : '' }}</h3><p>{{ $address->name }}<br>{{ $address->line1 }}<br>{{ $address->city }} {{ $address->postcode }}</p><form method="post" action="{{ route('account.address.delete', $address) }}">@csrf @method('DELETE')<button type="submit">Remove</button></form></article>
            @empty
                <p>No saved addresses yet.</p>
            @endforelse
        </div>
        <h2>Add Address</h2>
        <form class="profile-form" method="post" action="{{ route('account.address.store') }}">@csrf<input name="label" value="Home" required><input name="name" placeholder="Name" required><input name="phone" placeholder="Phone"><input name="line1" placeholder="Address line 1" required><input name="line2" placeholder="Address line 2"><input name="city" placeholder="City" required><input name="county" placeholder="County"><input name="postcode" placeholder="Postcode"><input name="country" value="IE" maxlength="2" required><label><input type="checkbox" name="is_default" value="1"> Default</label><button class="btn" type="submit">SAVE ADDRESS</button></form>
    @endif

    @if($section === 'profile')
        <form class="profile-form" method="post" action="{{ route('account.profile') }}">@csrf @method('PATCH')<label>Name<input name="name" value="{{ auth()->user()->name }}" required></label><label>Email<input value="{{ auth()->user()->email }}" disabled></label><label>Phone<input name="phone" value="{{ auth()->user()->phone }}"></label><button class="btn" type="submit">UPDATE PROFILE</button></form>
    @endif

    @if($section === 'designs')
        <h2>Custom Designs</h2><p>Your saved hat customisation projects will appear here when a design configurator is connected.</p><a class="btn" href="{{ route('corporate.orders') }}">START A CUSTOM ORDER</a>
    @endif

    @if($section === 'returns')
        <h2>Returns and exchanges</h2>
        @forelse($returns as $return)
            <div class="order-row"><span>{{ $return->number }}</span><span>{{ $return->order->number }}</span><span>{{ str($return->type)->headline() }}</span><strong>{{ str($return->status)->headline() }}</strong></div>
        @empty
            <p>No return or exchange requests.</p>
        @endforelse
    @endif
@endsection
