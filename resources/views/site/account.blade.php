@extends('layouts.site')

@section('title', 'Customer Dashboard — Emerald Rozalia')

@section('content')
    <section class="account-screen">
        <div class="account-banner">
            <span class="account-banner-mark" aria-hidden="true"><x-icon name="star" size="20" /></span>
            <div>
                <span class="eyebrow">CUSTOMER DASHBOARD</span>
                <h1>Welcome back, {{ explode(' ', auth()->user()->name)[0] }}!</h1>
                <p>Thanks for being part of the Emerald Rozalia family.</p>
            </div>
        </div>

        <div class="account-grid">
            <aside class="account-sidebar" aria-label="Account navigation">
                <div class="account-profile">
                    <span class="account-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <h2>{{ auth()->user()->name }}</h2>
                    <p>{{ auth()->user()->email }}</p>
                    <span class="member-badge">EMERALD MEMBER</span>
                </div>

                <nav class="account-menu">
                    <a class="is-active" href="{{ route('account.dashboard') }}"><x-icon name="home" /> <span>Dashboard</span></a>
                    <a href="{{ route('account.section', 'orders') }}"><x-icon name="package" /> <span>My Orders</span></a>
                    <a href="{{ route('account.section', 'designs') }}"><x-icon name="pencil" /> <span>My Designs</span></a>
                    <a href="{{ route('account.section', 'addresses') }}"><x-icon name="home" /> <span>Address Book</span></a>
                    <a href="{{ route('account.section', 'payments') }}"><x-icon name="credit-card" /> <span>Payment Methods</span></a>
                    <a href="{{ route('account.section', 'profile') }}"><x-icon name="user" /> <span>Account Details</span></a>
                    <a href="{{ route('account.section', 'wishlist') }}"><x-icon name="heart" /> <span>Wishlist</span><b>{{ $wishlistCount }}</b></a>
                    <a href="{{ route('account.section', 'rewards') }}"><x-icon name="star" /> <span>Rewards &amp; Points</span></a>
                    <a href="{{ route('account.section', 'bulk-orders') }}"><x-icon name="briefcase" /> <span>Bulk Orders</span></a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"><x-icon name="log-out" /> <span>Logout</span></button>
                    </form>
                </nav>

                <a class="account-sidebar-cta" href="/bulk-orders">
                    <strong>IRISH MADE<br>IN LIMERICK</strong>
                    <span>Designed, cut and sewn in our Limerick factory.</span>
                    <em>LEARN MORE</em>
                </a>
            </aside>

            <div class="account-content">
                @if (!auth()->user()->hasVerifiedEmail())
                    <div class="account-alert" role="status">
                        <div><strong>Verify your email address</strong><span>Check your inbox to unlock secure order and account notifications.</span></div>
                        <form method="post" action="{{ route('verification.send') }}">
                            @csrf
                            <button class="text-button" type="submit">RESEND EMAIL</button>
                        </form>
                    </div>
                @endif

                <div class="account-kpis">
                    <a href="{{ route('account.section', 'orders') }}"><span class="account-kpi-icon"><x-icon name="package" /></span><strong>{{ $orders->count() }}</strong><small>Orders</small><em>View all orders <x-icon name="arrow-right" /></em></a>
                    <a href="{{ route('account.section', 'designs') }}"><span class="account-kpi-icon"><x-icon name="pencil" /></span><strong>0</strong><small>Custom Designs</small><em>View my designs <x-icon name="arrow-right" /></em></a>
                    <a href="{{ route('account.section', 'wishlist') }}"><span class="account-kpi-icon"><x-icon name="heart" /></span><strong>{{ $wishlistCount }}</strong><small>Wishlist Items</small><em>View wishlist <x-icon name="arrow-right" /></em></a>
                    <a href="{{ route('account.section', 'rewards') }}"><span class="account-kpi-icon"><x-icon name="star" /></span><strong>{{ $rewards }}</strong><small>Reward Points</small><em>View rewards <x-icon name="arrow-right" /></em></a>
                </div>

                <div class="account-columns">
                    <section class="account-card recent-orders">
                        <div class="account-card-heading"><h2>Recent Orders</h2><a href="{{ route('account.section', 'orders') }}">View all orders <x-icon name="arrow-right" /></a></div>
                        @forelse($orders as $order)
                            @php($item = $order->items->first())
                            <article class="account-order">
                                <span class="order-thumb" aria-hidden="true">ER</span>
                                <div class="order-name"><strong>#{{ $order->number }}</strong><span>{{ $item?->name ?? 'Emerald Rozalia order' }}</span><small>{{ $item?->sku ?? 'Online order' }}</small></div>
                                <div class="order-date"><span>{{ $order->created_at?->format('d M Y') }}</span><strong>€{{ number_format((float) $order->total, 2) }}</strong></div>
                                <span class="order-status status-{{ str($order->status)->slug() }}">{{ str($order->status)->headline() }}</span>
                                <a class="order-link" href="{{ route('account.invoice', $order) }}">View Order <x-icon name="arrow-right" /></a>
                            </article>
                        @empty
                            <div class="account-empty"><strong>Your first Emerald Rozalia order belongs here.</strong><span>Explore the collection and we’ll keep your order history in one place.</span><a class="btn" href="{{ route('shop') }}">SHOP THE COLLECTION</a></div>
                        @endforelse
                    </section>

                    <aside class="account-side-cards">
                        <section class="account-card overview-card">
                            <div class="account-card-heading"><h2>Account Overview</h2><a href="{{ route('account.section', 'profile') }}">Edit</a></div>
                            <dl>
                                <div><dt><x-icon name="user" /></dt><dd><span>Name</span><strong>{{ auth()->user()->name }}</strong></dd></div>
                                <div><dt><x-icon name="mail" /></dt><dd><span>Email</span><strong>{{ auth()->user()->email }}</strong></dd></div>
                                <div><dt><x-icon name="message" /></dt><dd><span>Phone</span><strong>{{ auth()->user()->phone ?: 'Not added yet' }}</strong></dd></div>
                                <div><dt><x-icon name="clock" /></dt><dd><span>Member Since</span><strong>{{ auth()->user()->created_at?->format('F Y') }}</strong></dd></div>
                            </dl>
                            <a class="btn" href="{{ route('account.section', 'profile') }}">VIEW ACCOUNT DETAILS</a>
                        </section>

                        <section class="account-card quick-actions">
                            <h2>Quick Actions</h2>
                            <a href="{{ route('account.section', 'orders') }}"><x-icon name="package" /> <span>Track an Order</span> <x-icon name="chevron-right" /></a>
                            <a href="{{ route('account.section', 'returns') }}"><x-icon name="refresh" /> <span>Return or Exchange</span> <x-icon name="chevron-right" /></a>
                            <a href="{{ route('account.section', 'orders') }}"><x-icon name="file-text" /> <span>Download Invoices</span> <x-icon name="chevron-right" /></a>
                            <a href="{{ route('account.section', 'addresses') }}"><x-icon name="home" /> <span>Manage Addresses</span> <x-icon name="chevron-right" /></a>
                            <a href="{{ route('account.section', 'payments') }}"><x-icon name="credit-card" /> <span>Payment Methods</span> <x-icon name="chevron-right" /></a>
                        </section>

                        <section class="account-card rewards-card">
                            <h2>Emerald Rewards <span aria-hidden="true"><x-icon name="star" size="16" /></span></h2>
                            <p>You have</p>
                            <strong>{{ $rewards }} Points</strong>
                            <div class="reward-track"><span style="width: {{ min(100, max(0, ($rewards % 500) / 5)) }}%"></span></div>
                            <a class="btn ghost" href="{{ route('account.section', 'rewards') }}">VIEW REWARDS</a>
                        </section>
                    </aside>
                </div>
            </div>
        </div>
    </section>
@endsection
