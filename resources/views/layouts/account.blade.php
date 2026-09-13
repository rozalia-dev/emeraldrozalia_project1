@extends('layouts.site')

@php
    $accountSection = trim($__env->yieldContent('account-active')) ?: 'dashboard';
    $accountNavigation = [
        'orders' => ['label' => 'My Orders', 'icon' => 'package'],
        'returns' => ['label' => 'Returns & Exchanges', 'icon' => 'refresh', 'count' => $returnsCount ?? 0],
        'addresses' => ['label' => 'Address Book', 'icon' => 'home'],
        'payments' => ['label' => 'Payment Methods', 'icon' => 'credit-card'],
        'profile' => ['label' => 'Account Details', 'icon' => 'user'],
        'wishlist' => ['label' => 'Wishlist', 'icon' => 'heart', 'count' => $wishlistCount ?? 0],
        'rewards' => ['label' => 'Rewards & Points', 'icon' => 'star'],
        'designs' => ['label' => 'Custom Designs', 'icon' => 'pencil'],
        'bulk-orders' => ['label' => 'Bulk Orders', 'icon' => 'briefcase'],
    ];
@endphp

@section('content')
    <section class="account-screen" data-account-layout data-account-section="{{ $accountSection }}">
        @yield('account-header')

        <div class="account-grid">
            <aside class="account-sidebar" aria-label="Account navigation">
                <div class="account-profile">
                    <span class="account-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <h2>{{ auth()->user()->name }}</h2>
                    <p>{{ auth()->user()->email }}</p>
                    <span class="member-badge">EMERALD MEMBER</span>
                </div>

                <nav class="account-menu" data-account-navigation>
                    <a class="{{ $accountSection === 'dashboard' ? 'is-active' : '' }}" href="{{ route('account.dashboard') }}" @if($accountSection === 'dashboard') aria-current="page" @endif><x-icon name="home" /> <span>Dashboard</span></a>
                    @foreach($accountNavigation as $key => $item)
                        <a class="{{ $accountSection === $key ? 'is-active' : '' }}" href="{{ route('account.section', $key) }}" @if($accountSection === $key) aria-current="page" @endif>
                            <x-icon name="{{ $item['icon'] }}" /> <span>{{ $item['label'] }}</span>
                            @if(array_key_exists('count', $item))<b>{{ $item['count'] }}</b>@endif
                        </a>
                    @endforeach
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"><x-icon name="log-out" /> <span>Logout</span></button>
                    </form>
                </nav>

                <a class="account-sidebar-cta" href="{{ route('bulk.orders') }}">
                    <strong>IRISH MADE<br>IN LIMERICK</strong>
                    <span>Designed, cut and sewn in our Limerick factory.</span>
                    <em>LEARN MORE</em>
                </a>
            </aside>

            <div class="account-content">
                @if (!auth()->user()->hasVerifiedEmail())
                    <div class="account-alert" role="status" data-account-verification-alert>
                        <div><strong>Verify your email address</strong><span>Check your inbox to unlock secure order and account notifications.</span></div>
                        <form method="post" action="{{ route('verification.send') }}">
                            @csrf
                            <button class="text-button" type="submit">RESEND EMAIL</button>
                        </form>
                    </div>
                @endif

                @yield('account-content')
            </div>
        </div>
    </section>
@endsection
