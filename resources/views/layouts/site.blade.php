<!doctype html>
@php($siteBranding = data_get($siteSettings ?? [], 'company-branding', []))
@php($siteTheme = data_get($siteSettings ?? [], 'theme', []))
@php($siteThemeMeta = data_get($siteSettings ?? [], 'theme_meta', []))
<html lang="{{str_replace('_','-',app()->getLocale())}}" data-public-settings-source="{{ data_get($siteSettings ?? [], 'meta.source', 'company-fallback') }}" data-public-settings-version="{{ data_get($siteSettings ?? [], 'meta.version', 0) }}" data-public-theme-source="{{ data_get($siteThemeMeta, 'source', 'default-theme-fallback') }}" data-public-theme-version="{{ data_get($siteThemeMeta, 'version', 0) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    @php($seoMetadata = app(\App\Services\SeoMetadata::class)->forView(trim($__env->yieldContent('title')) ?: 'Emerald Rozalia', $product ?? null, $category ?? $activeCategory ?? null, $managedPage ?? null))
    <title>{{ $seoMetadata['title'] }}</title>
    <meta name="description" content="{{ $seoMetadata['description'] }}">
    <link rel="canonical" href="{{ $seoMetadata['canonical'] }}">
    <meta name="robots" content="{{ $seoMetadata['noindex'] ? 'noindex,nofollow' : 'index,follow' }}">
    <meta property="og:title" content="{{ $seoMetadata['title'] }}">
    <meta property="og:description" content="{{ $seoMetadata['description'] }}">
    <meta property="og:url" content="{{ $seoMetadata['canonical'] }}">
    @if($seoMetadata['schema'])<script type="application/ld+json">{!! json_encode($seoMetadata['schema'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>@endif
    <link rel="stylesheet" href="/css/app.css?v=20260905-public-header-type">
    <link rel="stylesheet" href="/css/theme-runtime.css?v=20260913-batch17-typography">
    @stack('styles')
</head>
<body class="site-body @yield('body-class')" style="--site-brand-primary: {{ data_get($siteTheme, 'colors.primary', data_get($siteBranding, 'brand_primary', '#075b2f')) }}; --site-brand-secondary: {{ data_get($siteTheme, 'colors.secondary', data_get($siteBranding, 'brand_secondary', '#0b1711')) }}; --site-brand-accent: {{ data_get($siteTheme, 'colors.accent', data_get($siteBranding, 'brand_accent', '#7fbd42')) }}; --site-surface: {{ data_get($siteTheme, 'colors.surface', '#ffffff') }}; --site-surface-muted: {{ data_get($siteTheme, 'colors.surface_muted', '#f3f6f2') }}; --site-text: {{ data_get($siteTheme, 'colors.text', '#0b1711') }}; --site-text-muted: {{ data_get($siteTheme, 'colors.text_muted', '#5c6c62') }}; --site-border: {{ data_get($siteTheme, 'colors.border', '#d9e3db') }}; --site-focus: {{ data_get($siteTheme, 'colors.focus', '#7fbd42') }}; --site-success: {{ data_get($siteTheme, 'colors.success', '#16784a') }}; --site-warning: {{ data_get($siteTheme, 'colors.warning', '#b7791f') }}; --site-danger: {{ data_get($siteTheme, 'colors.danger', '#b42318') }}; --site-info: {{ data_get($siteTheme, 'colors.info', '#2676cc') }}; --site-font-family: {{ data_get($siteTheme, 'typography.font_family', 'Inter, Arial, sans-serif') }}; --site-heading-family: {{ data_get($siteTheme, 'typography.heading_family', 'Georgia, serif') }}; --site-heading-weight: {{ data_get($siteTheme, 'typography.heading_weight', 700) }}; --site-base-size: {{ data_get($siteTheme, 'typography.base_size', '16px') }}; --site-section-y: {{ data_get($siteTheme, 'spacing.section_y', '64px') }}; --site-container-max: {{ data_get($siteTheme, 'spacing.container_max', '1280px') }}; --site-radius: {{ data_get($siteTheme, 'spacing.radius', '12px') }}; --site-button-radius: {{ data_get($siteTheme, 'controls.button_radius', '10px') }}; --site-input-radius: {{ data_get($siteTheme, 'controls.input_radius', '8px') }}; --site-button-height: {{ data_get($siteTheme, 'controls.button_height', '44px') }}; --site-motion-duration: {{ data_get($siteTheme, 'motion.duration_ms', 180) }}ms; --site-motion-easing: {{ data_get($siteTheme, 'motion.easing', 'ease-out') }};">
<div class="topline">
    <span><x-icon name="clover" size="14" /> Proudly Manufacturing in Limerick, Ireland</span>
    <strong>Irish Made. Limerick Born. <em>Worn Everywhere.</em></strong>
    <div class="topline-tools">
        <form method="post" action="/context/language">@csrf<label><span>Language</span>
            <select name="locale" aria-label="Language" onchange="this.form.submit()">
            @foreach(\App\Models\Language::where('active',true)->get() as $l)<option value="{{$l->locale}}" @selected(($activeLocale??'en')===$l->locale)>{{strtoupper($l->locale)}}</option>@endforeach
            </select></label>
        </form>
        <form method="post" action="/context/currency">@csrf<label><span>Currency</span>
            <select name="currency" aria-label="Currency" onchange="this.form.submit()">
                @foreach(\App\Models\Currency::where('active',true)->get() as $c)<option value="{{$c->code}}" @selected(($activeCurrency??'EUR')===$c->code)>{{$c->code}} {{$c->symbol}}</option>@endforeach
            </select></label>
        </form>
    </div>
</div>
<header class="site-header">
    <a href="/" class="brand">
        <img class="brand-logo-image" src="{{asset(data_get($siteSettings, 'theme_assets.header_logo.path', data_get($siteBranding, 'header_logo_path', '/assets/logo/logo_one_line.png')))}}" alt="{{ data_get($siteBranding, 'trading_name', 'Emerald Rozalia Limited') }}">
    </a>
    <button class="nav-toggle" data-nav-toggle aria-label="Open menu"><x-icon name="menu" size="22" /></button>
    <nav data-nav aria-label="Primary">
        <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>HOME</a>
        <a class="{{ request()->routeIs('shop','category','product') ? 'is-active' : '' }}" href="{{ route('shop') }}" @if(request()->routeIs('shop','category','product')) aria-current="page" @endif>SHOP</a>
        <a class="{{ request()->routeIs('collections','irish.traditional','irish.heritage') ? 'is-active' : '' }}" href="{{ route('collections') }}" @if(request()->routeIs('collections','irish.traditional','irish.heritage')) aria-current="page" @endif>COLLECTIONS</a>
        <a class="{{ request()->routeIs('new.arrivals') ? 'is-active' : '' }}" href="{{ route('new.arrivals') }}" @if(request()->routeIs('new.arrivals')) aria-current="page" @endif>NEW ARRIVALS</a>
        <a class="{{ request()->routeIs('corporate.orders') ? 'is-active' : '' }}" href="{{ route('corporate.orders') }}" @if(request()->routeIs('corporate.orders')) aria-current="page" @endif>CORPORATE ORDER</a>
        <a class="{{ request()->routeIs('bulk.orders') ? 'is-active' : '' }}" href="{{ route('bulk.orders') }}" @if(request()->routeIs('bulk.orders')) aria-current="page" @endif>BULK ORDER</a>
        <a class="{{ request()->routeIs('franchise') ? 'is-active' : '' }}" href="{{ route('franchise') }}" @if(request()->routeIs('franchise')) aria-current="page" @endif>FRANCHISE APPLY</a>
        <a class="{{ request()->routeIs('careers') ? 'is-active' : '' }}" href="{{ route('careers') }}" @if(request()->routeIs('careers')) aria-current="page" @endif>HIRING APPLY</a>
    </nav>
    <div class="utilities" aria-label="Account tools"><a href="{{ route('shop') }}" aria-label="Search" title="Search"><x-icon name="search" size="20" /></a><a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}" aria-label="{{ auth()->check() ? 'Account' : 'Login' }}" title="{{ auth()->check() ? 'Account' : 'Login' }}"><x-icon name="user" size="20" /></a><a href="{{ route('cart') }}" aria-label="Cart" title="Cart"><x-icon name="shopping-bag" size="20" /><small>{{count(session('cart',[]))?'('.array_sum(array_column(session('cart',[]),'quantity')).')':''}}</small></a></div>
</header>
@if(session('success'))<div class="flash success">{{session('success')}}</div>@endif
@if($errors->any())<div class="flash error">{{implode(' ',$errors->all())}}</div>@endif
<main>@yield('content')</main>
<footer class="site-footer">
    <div class="footer-brand"><img class="brand-logo-image" src="{{asset(data_get($siteSettings, 'theme_assets.footer_logo.path', data_get($siteBranding, 'footer_logo_path', '/assets/logo/logo_two_line.png')))}}" alt="{{ data_get($siteBranding, 'legal_name', 'Emerald Rozalia Limited') }}"><p>{{ data_get($siteBranding, 'description', 'Proudly manufacturing hats and caps in Limerick, Ireland.') }}</p><div class="socials"><x-icon name="facebook" label="Facebook" /><x-icon name="instagram" label="Instagram" /><x-icon name="music" label="TikTok" /><x-icon name="linkedin" label="LinkedIn" /><x-icon name="youtube" label="YouTube" /></div></div>
    <div><h4>SHOP</h4><a href="/shop">All Products</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/category/bucket-hats">Bucket Hats</a><a href="/category/snapbacks">Snapbacks</a><a href="/irish-traditional">Flat Caps</a></div>
    <div><h4>COLLECTIONS</h4><a href="/irish-traditional">Irish Traditional</a><a href="/irish-heritage">Irish Heritage</a><a href="/new-arrivals">New Arrivals</a><a href="/collections">Premium Collection</a></div>
    <div><h4>CUSTOMER CARE</h4><a href="/factory">Size Guide</a><a href="/factory">Shipping & Delivery</a><a href="/factory">Returns & Refunds</a><a href="/contact">Contact Us</a></div>
    <div><h4>COMPANY</h4><a href="/factory">Our Story</a><a href="/factory">Manufacturing</a><a href="/global-network">Sustainability</a><a href="/careers">Careers</a>@foreach($footerPages ?? [] as $footerPage)<a href="{{ route('content.page', ['page' => $footerPage->slug]) }}">{{ $footerPage->title }}</a>@endforeach</div>
    <div class="newsletter"><h4>NEWSLETTER</h4><p>Stay updated with new arrivals and offers.</p><form><input type="email" placeholder="Your email address" aria-label="Your email address"><button class="btn" type="button" aria-label="Subscribe"><x-icon name="arrow-right" /></button></form><p class="payments">VISA &nbsp; Mastercard &nbsp; PayPal &nbsp; Apple Pay &nbsp; Google Pay</p></div>
    <div class="footer-bottom"><span>{{ data_get($siteBranding, 'footer_text', '© '.now()->year.' Emerald Rozalia Limited. All rights reserved.') }}</span><span><x-icon name="clover" size="14" /> Designed &amp; Manufactured in {{ data_get($siteBranding, 'city', 'Limerick') }}, {{ data_get($siteBranding, 'country', 'Ireland') }}</span><span><a href="/factory">Privacy Policy</a> &nbsp; <a href="/factory">Terms &amp; Conditions</a></span></div>
</footer>
@stack('scripts')
<script src="/js/app.js?v=20260905-scheduler"></script>
</body>
</html>
