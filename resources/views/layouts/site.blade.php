<!doctype html>
@php($siteBranding = data_get($siteSettings ?? [], 'company-branding', []))
@php($siteTheme = data_get($siteSettings ?? [], 'theme', []))
@php($siteThemeMeta = data_get($siteSettings ?? [], 'theme_meta', []))
@php($siteLayoutRegions = data_get($siteLayout ?? [], 'regions', []))
@php($siteLayoutMeta = data_get($siteLayout ?? [], 'meta', []))
@php($siteLayoutService = app(\App\Services\SiteLayoutVersionService::class))
@php($layoutUrl = static fn ($link) => is_array($link) ? $siteLayoutService->urlFor($link) : null)
@php($layoutPath = static fn (?string $href) => $href ? (parse_url($href, PHP_URL_PATH) ?: '/') : null)
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-public-settings-source="{{ data_get($siteSettings ?? [], 'meta.source', 'company-fallback') }}" data-public-settings-version="{{ data_get($siteSettings ?? [], 'meta.version', 0) }}" data-public-theme-source="{{ data_get($siteThemeMeta, 'source', 'default-theme-fallback') }}" data-public-theme-version="{{ data_get($siteThemeMeta, 'version', 0) }}" data-public-layout-source="{{ data_get($siteLayoutMeta, 'source', 'default-layout-fallback') }}" data-public-layout-version="{{ data_get($siteLayoutMeta, 'version', 0) }}">
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
    @if($seoMetadata['schema'])
        <script type="application/ld+json">{!! json_encode($seoMetadata['schema'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
    <link rel="stylesheet" href="/css/app.css?v=20260905-public-header-type">
    <link rel="stylesheet" href="/css/theme-runtime.css?v=20260913-batch17-typography">
    @stack('styles')
</head>
<body class="site-body @yield('body-class')" style="--site-brand-primary: {{ data_get($siteTheme, 'colors.primary', data_get($siteBranding, 'brand_primary', '#075b2f')) }}; --site-brand-secondary: {{ data_get($siteTheme, 'colors.secondary', data_get($siteBranding, 'brand_secondary', '#0b1711')) }}; --site-brand-accent: {{ data_get($siteTheme, 'colors.accent', data_get($siteBranding, 'brand_accent', '#7fbd42')) }}; --site-surface: {{ data_get($siteTheme, 'colors.surface', '#ffffff') }}; --site-surface-muted: {{ data_get($siteTheme, 'colors.surface_muted', '#f3f6f2') }}; --site-text: {{ data_get($siteTheme, 'colors.text', '#0b1711') }}; --site-text-muted: {{ data_get($siteTheme, 'colors.text_muted', '#5c6c62') }}; --site-border: {{ data_get($siteTheme, 'colors.border', '#d9e3db') }}; --site-focus: {{ data_get($siteTheme, 'colors.focus', '#7fbd42') }}; --site-success: {{ data_get($siteTheme, 'colors.success', '#16784a') }}; --site-warning: {{ data_get($siteTheme, 'colors.warning', '#b7791f') }}; --site-danger: {{ data_get($siteTheme, 'colors.danger', '#b42318') }}; --site-info: {{ data_get($siteTheme, 'colors.info', '#2676cc') }}; --site-font-family: {{ data_get($siteTheme, 'typography.font_family', 'Inter, Arial, sans-serif') }}; --site-heading-family: {{ data_get($siteTheme, 'typography.heading_family', 'Georgia, serif') }}; --site-heading-weight: {{ data_get($siteTheme, 'typography.heading_weight', 700) }}; --site-base-size: {{ data_get($siteTheme, 'typography.base_size', '16px') }}; --site-section-y: {{ data_get($siteTheme, 'spacing.section_y', '64px') }}; --site-container-max: {{ data_get($siteTheme, 'spacing.container_max', '1280px') }}; --site-radius: {{ data_get($siteTheme, 'spacing.radius', '12px') }}; --site-button-radius: {{ data_get($siteTheme, 'controls.button_radius', '10px') }}; --site-input-radius: {{ data_get($siteTheme, 'controls.input_radius', '8px') }}; --site-button-height: {{ data_get($siteTheme, 'controls.button_height', '44px') }}; --site-motion-duration: {{ data_get($siteTheme, 'motion.duration_ms', 180) }}ms; --site-motion-easing: {{ data_get($siteTheme, 'motion.easing', 'ease-out') }};">
<div class="topline">
    <span><x-icon name="clover" size="14" /> {{ data_get($siteLayoutRegions, 'header.announcement.eyebrow', 'Proudly Manufacturing in Limerick, Ireland') }}</span>
    <strong>{{ data_get($siteLayoutRegions, 'header.announcement.headline', 'Irish Made. Limerick Born. Worn Everywhere.') }}</strong>
    <div class="topline-tools">
        <form method="post" action="/context/language">
            @csrf
            <label><span>Language</span>
                <select name="locale" aria-label="Language" onchange="this.form.submit()">
                    @foreach(\App\Models\Language::where('active', true)->get() as $l)
                        <option value="{{ $l->locale }}" @selected(($activeLocale ?? 'en') === $l->locale)>{{ strtoupper($l->locale) }}</option>
                    @endforeach
                </select>
            </label>
        </form>
        <form method="post" action="/context/currency">
            @csrf
            <label><span>Currency</span>
                <select name="currency" aria-label="Currency" onchange="this.form.submit()">
                    @foreach(\App\Models\Currency::where('active', true)->get() as $c)
                        <option value="{{ $c->code }}" @selected(($activeCurrency ?? 'EUR') === $c->code)>{{ $c->code }} {{ $c->symbol }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>
</div>
<header class="site-header">
    @php($headerLogoPath = data_get($siteLayoutRegions, 'header.logo.path', data_get($siteSettings, 'theme_assets.header_logo.path', data_get($siteBranding, 'header_logo_path', '/assets/logo/logo_one_line.png'))))
    @php($headerLogoPath = in_array($headerLogoPath, ['/assets/logo/logo_one_line.png', '/assets/logo/logo_two_line.png'], true) ? $headerLogoPath : '/assets/logo/logo_one_line.png')
    <a href="{{ url('/') }}" class="brand">
        <img class="brand-logo-image" src="{{ asset($headerLogoPath) }}" alt="{{ data_get($siteLayoutRegions, 'header.logo.alt', data_get($siteBranding, 'trading_name', 'Emerald Rozalia Limited')) }}">
    </a>
    <button class="nav-toggle" data-nav-toggle aria-label="Open menu"><x-icon name="menu" size="22" /></button>
    <nav data-nav aria-label="Primary">
        @foreach((array) data_get($siteLayoutRegions, 'header.primary_menu', []) as $navItem)
            @php($navHref = $layoutUrl($navItem))
            @if($navHref)
                @php($navPath = $layoutPath($navHref))
                <a class="{{ request()->getPathInfo() === $navPath ? 'is-active' : '' }}" href="{{ $navHref }}" {{ request()->getPathInfo() === $navPath ? 'aria-current="page"' : '' }}>{{ data_get($navItem, 'label') }}</a>
            @endif
        @endforeach
    </nav>
    <div class="utilities" aria-label="Account tools">
        @foreach((array) data_get($siteLayoutRegions, 'header.utility_menu', []) as $utilityItem)
            @php($utilityLink = $utilityItem)
            @if(auth()->check() && filled(data_get($utilityItem, 'auth_href')))
                @php($utilityLink = array_merge($utilityItem, ['href' => data_get($utilityItem, 'auth_href')]))
            @endif
            @php($utilityHref = $layoutUrl($utilityLink))
            @if($utilityHref)
                @php($utilityLabel = auth()->check() ? data_get($utilityItem, 'auth_label', data_get($utilityItem, 'label', 'Account')) : data_get($utilityItem, 'guest_label', data_get($utilityItem, 'label', 'Utility link')))
                <a href="{{ $utilityHref }}" aria-label="{{ $utilityLabel }}" title="{{ $utilityLabel }}">
                    <x-icon name="{{ data_get($utilityItem, 'icon', 'link') }}" size="20" />
                    @if(data_get($utilityItem, 'label') === 'Cart')
                        @php($cartQuantity = array_sum(array_column(session('cart', []), 'quantity')))
                        @if($cartQuantity)
                            <small>({{ $cartQuantity }})</small>
                        @endif
                    @endif
                </a>
            @endif
        @endforeach
    </div>
</header>
@if(session('success'))
    <div class="flash success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="flash error">{{ implode(' ', $errors->all()) }}</div>
@endif
<main>@yield('content')</main>
<footer class="site-footer">
    @php($footerLogoPath = data_get($siteSettings, 'theme_assets.footer_logo.path', data_get($siteBranding, 'footer_logo_path', '/assets/logo/logo_two_line.png')))
    @php($footerLogoPath = in_array($footerLogoPath, ['/assets/logo/logo_one_line.png', '/assets/logo/logo_two_line.png'], true) ? $footerLogoPath : '/assets/logo/logo_two_line.png')
    <div class="footer-brand">
        <img class="brand-logo-image" src="{{ asset($footerLogoPath) }}" alt="{{ data_get($siteBranding, 'legal_name', 'Emerald Rozalia Limited') }}">
        <p>{{ data_get($siteLayoutRegions, 'footer.brand_description', data_get($siteBranding, 'description', 'Proudly manufacturing hats and caps in Limerick, Ireland.')) }}</p>
        <div class="socials">
            @foreach((array) data_get($siteLayoutRegions, 'footer.social_links', []) as $social)
                @php($socialHref = $layoutUrl($social))
                @if($socialHref)
                    <a href="{{ $socialHref }}" aria-label="{{ data_get($social, 'label', 'Social profile') }}" rel="me noopener" target="_blank">
                        <x-icon name="{{ data_get($social, 'icon', 'link') }}" label="{{ data_get($social, 'label', 'Social profile') }}" />
                    </a>
                @endif
            @endforeach
        </div>
    </div>
    @foreach((array) data_get($siteLayoutRegions, 'footer.columns', []) as $footerColumn)
        <div>
            <h4>{{ data_get($footerColumn, 'title') }}</h4>
            @foreach((array) data_get($footerColumn, 'links', []) as $footerLink)
                @php($footerHref = $layoutUrl($footerLink))
                @if($footerHref)
                    <a href="{{ $footerHref }}">{{ data_get($footerLink, 'label') }}</a>
                @endif
            @endforeach
            @if(data_get($footerColumn, 'title') === 'COMPANY')
                @foreach($footerPages ?? [] as $footerPage)
                    <a href="{{ route('content.page', ['page' => $footerPage->slug]) }}">{{ $footerPage->title }}</a>
                @endforeach
            @endif
        </div>
    @endforeach
    @php($newsletter = data_get($siteLayoutRegions, 'footer.newsletter', []))
    @php($newsletterHref = $layoutUrl($newsletter))
    <div class="newsletter">
        <h4>{{ data_get($newsletter, 'title', 'NEWSLETTER') }}</h4>
        <p>{{ data_get($newsletter, 'description', 'Stay updated with new arrivals and offers.') }}</p>
        @if($newsletterHref)
            <a class="btn" href="{{ $newsletterHref }}">{{ data_get($newsletter, 'cta_label', 'Contact our team') }} <x-icon name="arrow-right" /></a>
        @endif
        <p class="payments">VISA &nbsp; Mastercard &nbsp; PayPal &nbsp; Apple Pay &nbsp; Google Pay</p>
    </div>
    <div class="footer-bottom">
        <span>{{ data_get($siteBranding, 'footer_text', '© '.now()->year.' Emerald Rozalia Limited. All rights reserved.') }}</span>
        <span><x-icon name="clover" size="14" /> Designed &amp; Manufactured in {{ data_get($siteBranding, 'city', 'Limerick') }}, {{ data_get($siteBranding, 'country', 'Ireland') }}</span>
        <span>
            @foreach((array) data_get($siteLayoutRegions, 'footer.legal_links', []) as $legalLink)
                @php($legalHref = $layoutUrl($legalLink))
                @if($legalHref)
                    <a href="{{ $legalHref }}">{{ data_get($legalLink, 'label') }}</a>
                    @if(!$loop->last)
                        &nbsp;
                    @endif
                @endif
            @endforeach
        </span>
    </div>
</footer>
@stack('scripts')
<script src="/js/app.js?v=20260905-scheduler"></script>
</body>
</html>
