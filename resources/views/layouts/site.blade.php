<!doctype html>
@php($siteBranding = data_get($siteSettings ?? [], 'company-branding', []))
@php($siteTheme = data_get($siteSettings ?? [], 'theme', []))
@php($siteThemeMeta = data_get($siteSettings ?? [], 'theme_meta', []))
@php($siteLayoutRegions = data_get($siteLayout ?? [], 'regions', []))
@php($siteLayoutMeta = data_get($siteLayout ?? [], 'meta', []))
@php($footerBrandDescription = data_get($siteLayoutRegions, 'footer.brand_description') ?: data_get($siteBranding, 'description', 'Proudly manufacturing hats and caps in Limerick, Ireland.'))
@php($footerPhone = data_get($siteBranding, 'phone') ?: config('app.brand_contact.whatsapp', '0899788187'))
@php($footerEmail = data_get($siteBranding, 'email') ?: config('app.brand_contact.email', 'urmos@rozalia.ie'))
@php($footerWebsite = data_get($siteBranding, 'website') ?: config('app.brand_contact.website', 'emeraldrozalia.ie'))
@php($footerLocation = data_get($siteBranding, 'address') ?: config('app.brand_contact.location', 'Limerick, Ireland'))
@php($footerWebsiteUrl = preg_match('/\Ahttps:\/\//i', (string) $footerWebsite) ? $footerWebsite : 'https://'.ltrim((string) $footerWebsite, '/'))
@php($siteLayoutService = app(\App\Services\SiteLayoutVersionService::class))
@php($publicMedia = app(\App\Services\PublicMediaResolver::class))
@php($layoutUrl = static fn ($link) => is_array($link) ? $siteLayoutService->urlFor($link) : null)
@php($layoutPath = static fn (?string $href) => $href ? (parse_url($href, PHP_URL_PATH) ?: '/') : null)
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-public-shell="shared" data-public-settings-source="{{ data_get($siteSettings ?? [], 'meta.source', 'company-fallback') }}" data-public-settings-version="{{ data_get($siteSettings ?? [], 'meta.version', 0) }}" data-public-theme-source="{{ data_get($siteThemeMeta, 'source', 'default-theme-fallback') }}" data-public-theme-version="{{ data_get($siteThemeMeta, 'version', 0) }}" data-public-layout-source="{{ data_get($siteLayoutMeta, 'source', 'default-layout-fallback') }}" data-public-layout-version="{{ data_get($siteLayoutMeta, 'version', 0) }}">
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
    <link rel="stylesheet" href="/css/app.css?v=20260919-contact-channel-icons">
    <link rel="stylesheet" href="/css/theme-runtime.css?v=20260913-batch17-typography">
    <link rel="stylesheet" href="/css/public-shell.css?v=20260919-empty-search-contact">
    <style id="public-media-contract">:root{--public-asset-home-reference:none;--public-asset-home-hero:none;--public-asset-home-collections:none;--public-asset-bulk-order:none;--public-asset-corporate-order:none;--public-asset-logo-two-line:none}</style>
    <style id="public-catalog-navigation">
        .site-nav-dropdown{position:relative;display:inline-flex;height:100%;align-items:center;color:var(--layout-header-text,#f4f4ef)}.site-nav-dropdown summary{display:inline-flex;align-items:center;gap:5px;padding:3px 0 4px;list-style:none;cursor:pointer;color:var(--layout-header-text,#f4f4ef);border-bottom:2px solid transparent}.site-nav-dropdown summary::-webkit-details-marker{display:none}.site-nav-dropdown summary.is-active{color:#8cc63e;border-bottom-color:var(--layout-header-accent,#8cc63e)}.site-nav-dropdown summary span{font-size:10px;transition:transform 160ms ease}.site-nav-dropdown[open] summary span{transform:rotate(180deg)}.site-nav-dropdown-menu{position:absolute;top:calc(100% - 14px);left:50%;z-index:90;display:grid;min-width:250px;max-width:330px;max-height:70vh;overflow:auto;padding:8px;transform:translateX(-50%);background:#06140d;border:1px solid #294731;border-radius:10px;box-shadow:0 18px 40px rgba(0,0,0,.35)}.site-body .site-header[data-public-shell-region="header"] nav .site-nav-dropdown-menu a{display:flex;min-height:38px;align-items:center;justify-content:space-between;gap:16px;padding:9px 11px;border:0;border-radius:7px;color:var(--layout-header-text,#f4f4ef);text-decoration:none}.site-body .site-header[data-public-shell-region="header"] nav .site-nav-dropdown-menu a:hover,.site-body .site-header[data-public-shell-region="header"] nav .site-nav-dropdown-menu a:focus-visible{background:#0e2a1b;color:var(--layout-header-accent,#9bd451)}.site-nav-dropdown-menu a:first-child{font-weight:700;border-bottom:1px solid #294731!important;border-radius:7px 7px 0 0!important}.site-nav-dropdown-menu small{color:#9fb0a5;font-size:11px}.site-body .site-header[data-public-shell-region="header"] .header-context-menu{position:relative;display:flex;flex:0 0 auto;align-items:center;margin:0}.site-body .site-header[data-public-shell-region="header"] .header-context-menu>summary{display:grid;place-items:center;width:40px;height:40px;padding:0;list-style:none;cursor:pointer;border:1px solid rgba(140,198,62,.6);border-radius:8px;background:rgba(140,198,62,.06);color:var(--layout-header-text,#f4f4ef);transition:color 150ms ease,border-color 150ms ease,background 150ms ease}.site-body .site-header[data-public-shell-region="header"] .header-context-menu>summary::-webkit-details-marker{display:none}.site-body .site-header[data-public-shell-region="header"] .header-context-menu>summary:hover,.site-body .site-header[data-public-shell-region="header"] .header-context-menu>summary:focus-visible,.site-body .site-header[data-public-shell-region="header"] .header-context-menu[open]>summary{border-color:var(--layout-header-accent,#8cc63e);background:rgba(140,198,62,.14);color:var(--layout-header-accent,#9bd451)}.site-body .site-header[data-public-shell-region="header"] .header-context-popover{position:absolute;top:calc(100% + 9px);right:0;z-index:110;display:grid;min-width:170px;max-height:310px;overflow:auto;padding:7px;background:#06140d;border:1px solid #294731;border-radius:9px;box-shadow:0 16px 34px rgba(0,0,0,.38)}.site-body .site-header[data-public-shell-region="header"] .header-context-popover button{display:flex;width:100%;align-items:center;justify-content:space-between;gap:14px;padding:9px 10px;border:0;border-radius:6px;background:transparent;color:#f4f4ef;text-align:left;font:600 12px/1.2 var(--site-font-family,Inter,Arial,sans-serif);cursor:pointer}.site-body .site-header[data-public-shell-region="header"] .header-context-popover button:hover,.site-body .site-header[data-public-shell-region="header"] .header-context-popover button:focus-visible{background:#0e2a1b;color:var(--layout-header-accent,#9bd451)}.site-body .site-header[data-public-shell-region="header"] .header-context-popover button.is-active{color:var(--layout-header-accent,#9bd451)}.site-body .site-header[data-public-shell-region="header"] .header-context-popover small{font-size:10px;color:#9fb0a5}@media(hover:hover) and (min-width:1321px){.site-nav-dropdown:not([open]):hover .site-nav-dropdown-menu{display:grid}.site-nav-dropdown:not([open]) .site-nav-dropdown-menu{display:none}}@media(max-width:1320px){.site-body .site-header[data-public-shell-region="header"] nav.open{max-height:calc(100vh - 72px);overflow-y:auto}.site-nav-dropdown{display:block;width:100%;height:auto}.site-nav-dropdown summary{width:100%;min-height:44px;justify-content:space-between;padding:11px 0}.site-nav-dropdown-menu{position:static;display:grid;width:100%;max-width:none;max-height:none;padding:4px 0 8px;transform:none;background:transparent;border:0;border-radius:0;box-shadow:none}.site-nav-dropdown:not([open]) .site-nav-dropdown-menu{display:none}.site-body .site-header[data-public-shell-region="header"] nav .site-nav-dropdown-menu a{min-height:40px;padding:9px 14px;color:#d9e6dc;background:#081a11}.site-nav-dropdown-menu a:first-child{border-bottom:0!important}.site-body .site-header[data-public-shell-region="header"] .header-context-menu>summary{width:36px;height:36px}.site-body .site-header[data-public-shell-region="header"] .header-context-popover{min-width:160px}}@media(max-width:700px){.site-body .site-header[data-public-shell-region="header"] .header-context-menu>summary{width:32px;height:32px}}@media(max-width:380px){.site-body .site-header[data-public-shell-region="header"] .header-context-menu>summary{width:30px;height:30px}}
    </style>
    @stack('styles')
</head>
<body class="site-body @yield('body-class')" style="--site-brand-primary: {{ data_get($siteTheme, 'colors.primary', data_get($siteBranding, 'brand_primary', '#075b2f')) }}; --site-brand-secondary: {{ data_get($siteTheme, 'colors.secondary', data_get($siteBranding, 'brand_secondary', '#0b1711')) }}; --site-brand-accent: {{ data_get($siteTheme, 'colors.accent', data_get($siteBranding, 'brand_accent', '#7fbd42')) }}; --site-surface: {{ data_get($siteTheme, 'colors.surface', '#ffffff') }}; --site-surface-muted: {{ data_get($siteTheme, 'colors.surface_muted', '#f3f6f2') }}; --site-text: {{ data_get($siteTheme, 'colors.text', '#0b1711') }}; --site-text-muted: {{ data_get($siteTheme, 'colors.text_muted', '#5c6c62') }}; --site-border: {{ data_get($siteTheme, 'colors.border', '#d9e3db') }}; --site-focus: {{ data_get($siteTheme, 'colors.focus', '#7fbd42') }}; --site-success: {{ data_get($siteTheme, 'colors.success', '#16784a') }}; --site-warning: {{ data_get($siteTheme, 'colors.warning', '#b7791f') }}; --site-danger: {{ data_get($siteTheme, 'colors.danger', '#b42318') }}; --site-info: {{ data_get($siteTheme, 'colors.info', '#2676cc') }}; --site-font-family: {{ data_get($siteTheme, 'typography.font_family', 'Inter, Arial, sans-serif') }}; --site-heading-family: {{ data_get($siteTheme, 'typography.heading_family', 'Georgia, serif') }}; --site-heading-weight: {{ data_get($siteTheme, 'typography.heading_weight', 700) }}; --site-base-size: {{ data_get($siteTheme, 'typography.base_size', '16px') }}; --site-section-y: {{ data_get($siteTheme, 'spacing.section_y', '64px') }}; --site-container-max: {{ data_get($siteTheme, 'spacing.container_max', '1280px') }}; --site-radius: {{ data_get($siteTheme, 'spacing.radius', '12px') }}; --site-button-radius: {{ data_get($siteTheme, 'controls.button_radius', '10px') }}; --site-input-radius: {{ data_get($siteTheme, 'controls.input_radius', '8px') }}; --site-button-height: {{ data_get($siteTheme, 'controls.button_height', '44px') }}; --site-motion-duration: {{ data_get($siteTheme, 'motion.duration_ms', 180) }}ms; --site-motion-easing: {{ data_get($siteTheme, 'motion.easing', 'ease-out') }};">
<div class="topline site-context-bar" data-public-context-controls aria-label="Site context controls">
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
<header class="site-header" data-public-shell-region="header" style="--layout-header-bg: {{ data_get($siteLayoutRegions, 'header.colors.background', '#010705') }}; --layout-header-text: {{ data_get($siteLayoutRegions, 'header.colors.text', '#f4f4ef') }}; --layout-header-accent: {{ data_get($siteLayoutRegions, 'header.colors.accent', '#8cc63e') }};">
    @php($layoutFallback = data_get($siteLayoutMeta, 'source') === 'default-layout-fallback')
    @php($headerLogoAlt = $layoutFallback
        ? (data_get($siteBranding, 'trading_name') ?: data_get($siteLayoutRegions, 'header.logo.alt', 'Emerald Rozalia Limited'))
        : data_get($siteLayoutRegions, 'header.logo.alt', data_get($siteBranding, 'trading_name', 'Emerald Rozalia Limited')))
    @php($headerLogoPath = ltrim((string) data_get($siteLayoutRegions, 'header.logo.path', '/assets/logo/logo_one_line.png'), '/'))
    @php($headerLogo = $publicMedia->forLegacyPath($headerLogoPath, $headerLogoAlt))
    @php($headerLogoUrl = data_get($headerLogo, 'url'))
    <a href="{{ url('/') }}" class="brand">
        @if($headerLogoUrl)
            <img class="brand-logo-image{{ $headerLogoPath === 'assets/logo/logo_one_line.png' ? ' brand-logo-image--wide' : '' }}" src="{{ $headerLogoUrl }}" @if(data_get($headerLogo, 'srcset')) srcset="{{ data_get($headerLogo, 'srcset') }}" sizes="{{ data_get($headerLogo, 'sizes') }}" @endif width="{{ data_get($headerLogo, 'width') ?: '' }}" height="{{ data_get($headerLogo, 'height') ?: '' }}" alt="{{ $headerLogoAlt }}">
        @else
            <span class="brand-logo-missing">{{ data_get($siteBranding, 'trading_name', 'Emerald Rozalia Limited') }}</span>
        @endif
    </a>
    <button class="nav-toggle" type="button" data-nav-toggle aria-label="Open menu"><x-icon name="menu" size="22" /></button>
    @php($sitePrimaryMenu = (array) data_get($siteLayoutRegions, 'header.primary_menu', []))
    <nav data-nav aria-label="Primary">
        @foreach($sitePrimaryMenu as $navItem)
            @php($navEnabled = !array_key_exists('enabled', $navItem) || filter_var(data_get($navItem, 'enabled'), FILTER_VALIDATE_BOOLEAN))
            @php($navHref = $layoutUrl($navItem))
            @if($navEnabled && $navHref)
                @php($navPath = $layoutPath($navHref))
                @php($isShopMenu = $navPath === '/shop')
                @php($isCollectionsMenu = $navPath === '/collections')
                @php($isNavActive = request()->getPathInfo() === $navPath || ($isShopMenu && request()->routeIs('category')) || ($isCollectionsMenu && request()->routeIs('collection.show')))
                @if($isShopMenu || $isCollectionsMenu)
                    <details class="site-nav-dropdown" data-catalog-nav="{{ $isShopMenu ? 'categories' : 'collections' }}">
                        <summary class="{{ $isNavActive ? 'is-active' : '' }}">{{ data_get($navItem, 'label') }} <span aria-hidden="true">▼</span></summary>
                        <div class="site-nav-dropdown-menu">
                            <a href="{{ $navHref }}">{{ $isShopMenu ? 'SHOP ALL' : 'VIEW ALL COLLECTIONS' }}</a>
                            @if($isShopMenu)
                                @foreach($catalogNavCategories ?? [] as $catalogCategory)
                                    <a href="{{ route('category', ['category' => $catalogCategory->slug]) }}"><span>{{ $catalogCategory->name }}</span><small>{{ $catalogCategory->products_count }}</small></a>
                                @endforeach
                            @else
                                @foreach($catalogNavCollections ?? [] as $catalogCollection)
                                    <a href="{{ route('collection.show', ['collection' => $catalogCollection->slug]) }}"><span>{{ $catalogCollection->name }}</span><small>{{ $catalogCollection->products_count }}</small></a>
                                @endforeach
                            @endif
                        </div>
                    </details>
                @else
                    <a class="{{ $isNavActive ? 'is-active' : '' }}" href="{{ $navHref }}" {{ $isNavActive ? 'aria-current="page"' : '' }}>{{ data_get($navItem, 'label') }}</a>
                @endif
            @endif
        @endforeach
    </nav>
    <div class="utilities" aria-label="Account tools">
        <details class="header-context-menu header-context-menu--language" data-header-language-menu>
            <summary aria-label="Change language" title="Language"><x-icon name="languages" size="19" /></summary>
            <form method="post" action="/context/language" class="header-context-popover">
                @csrf
                @foreach(\App\Models\Language::where('active', true)->orderBy('name')->get() as $l)
                    @php($languageActive = ($activeLocale ?? app()->getLocale()) === $l->locale)
                    <button type="submit" name="locale" value="{{ $l->locale }}" class="{{ $languageActive ? 'is-active' : '' }}" @if($languageActive) aria-current="true" @endif>
                        <span>{{ $l->native_name ?: $l->name }}</span><small>{{ strtoupper($l->locale) }}</small>
                    </button>
                @endforeach
            </form>
        </details>
        <details class="header-context-menu header-context-menu--currency" data-header-currency-menu>
            <summary aria-label="Change currency" title="Currency"><x-icon name="coins" size="19" /></summary>
            <form method="post" action="/context/currency" class="header-context-popover">
                @csrf
                @foreach(\App\Models\Currency::where('active', true)->orderBy('code')->get() as $c)
                    @php($currencyActive = ($activeCurrency ?? 'EUR') === $c->code)
                    <button type="submit" name="currency" value="{{ $c->code }}" class="{{ $currencyActive ? 'is-active' : '' }}" @if($currencyActive) aria-current="true" @endif>
                        <span>{{ $c->name }}</span><small>{{ $c->code }} {{ $c->symbol }}</small>
                    </button>
                @endforeach
            </form>
        </details>
        @foreach((array) data_get($siteLayoutRegions, 'header.utility_menu', []) as $utilityItem)
            @php($utilityEnabled = !array_key_exists('enabled', $utilityItem) || filter_var(data_get($utilityItem, 'enabled'), FILTER_VALIDATE_BOOLEAN))
            @continue(!$utilityEnabled)
            @php($utilityLink = $utilityItem)
            @if(auth()->check() && filled(data_get($utilityItem, 'auth_href')))
                @php($utilityLink = array_merge($utilityItem, ['href' => data_get($utilityItem, 'auth_href')]))
            @endif
            @php($utilityHref = $layoutUrl($utilityLink))
            @if($utilityHref)
                @php($utilityLabel = auth()->check() ? data_get($utilityItem, 'auth_label', data_get($utilityItem, 'label', 'Account')) : data_get($utilityItem, 'guest_label', data_get($utilityItem, 'label', 'Utility link')))
                @php($isCartUtility = data_get($utilityItem, 'label') === 'Cart')
                @php($cartQuantity = $isCartUtility ? array_sum(array_column(session('cart', []), 'quantity')) : 0)
                <a href="{{ $utilityHref }}" class="{{ $isCartUtility ? 'utility-cart-link' : '' }}" aria-label="{{ $utilityLabel }}{{ $cartQuantity ? ', '.$cartQuantity.' items' : '' }}" title="{{ $utilityLabel }}{{ $cartQuantity ? ' ('.$cartQuantity.')' : '' }}">
                    <x-icon name="{{ data_get($utilityItem, 'icon', 'link') }}" size="20" />
                    @if($isCartUtility && $cartQuantity)
                        <span class="cart-count-badge" aria-hidden="true">{{ $cartQuantity }}</span>
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
<footer class="site-footer" data-public-shell-region="footer" style="--layout-footer-bg: {{ data_get($siteLayoutRegions, 'footer.colors.background', '#03100b') }}; --layout-footer-text: {{ data_get($siteLayoutRegions, 'footer.colors.text', '#c7d1ca') }}; --layout-footer-accent: {{ data_get($siteLayoutRegions, 'footer.colors.accent', '#8cc63e') }};">
    @php($footerLogoAlt = $layoutFallback
        ? (data_get($siteBranding, 'legal_name') ?: data_get($siteLayoutRegions, 'footer.logo.alt', 'Emerald Rozalia Limited'))
        : data_get($siteLayoutRegions, 'footer.logo.alt', data_get($siteBranding, 'legal_name', 'Emerald Rozalia Limited')))
    @php($footerLogoPath = ltrim((string) ($layoutFallback ? $headerLogoPath : data_get($siteLayoutRegions, 'footer.logo.path', $headerLogoPath)), '/'))
    @php($footerLogo = $publicMedia->forLegacyPath($footerLogoPath, $footerLogoAlt))
    @php($footerLogoUrl = data_get($footerLogo, 'url'))
    @php($footerColumns = array_values((array) data_get($siteLayoutRegions, 'footer.columns', [])))
    @php($newsletter = (array) data_get($siteLayoutRegions, 'footer.newsletter', []))
    @php($newsletterHref = $layoutUrl($newsletter))
    @php($newsletterEnabled = ! array_key_exists('enabled', $newsletter) || filter_var(data_get($newsletter, 'enabled', true), FILTER_VALIDATE_BOOLEAN))
    @php($footerCopyright = data_get($siteLayoutRegions, 'footer.copyright_text')
        ?: data_get($siteBranding, 'footer_text')
        ?: '© '.now()->year.' Emerald Rozalia Limited. All rights reserved.')
    @php($footerManufacturing = data_get($siteLayoutRegions, 'footer.manufacturing_text')
        ?: 'Designed & Manufactured in '.data_get($siteBranding, 'city', 'Limerick').', '.data_get($siteBranding, 'country', 'Ireland'))

    <div class="footer-main">
        <section class="footer-brand" aria-label="Emerald Rozalia">
            @if($footerLogoUrl)
                <img class="brand-logo-image{{ $footerLogoPath === 'assets/logo/logo_two_line.png' ? ' brand-logo-image--wide' : '' }}" src="{{ $footerLogoUrl }}" @if(data_get($footerLogo, 'srcset')) srcset="{{ data_get($footerLogo, 'srcset') }}" sizes="{{ data_get($footerLogo, 'sizes') }}" @endif width="{{ data_get($footerLogo, 'width') ?: '' }}" height="{{ data_get($footerLogo, 'height') ?: '' }}" alt="{{ $footerLogoAlt }}">
            @else
                <span class="brand-logo-missing">{{ data_get($siteBranding, 'legal_name', 'Emerald Rozalia Limited') }}</span>
            @endif
            <p class="footer-brand-description">{{ $footerBrandDescription }}</p>
            <div class="footer-contact sr-only" aria-label="Emerald Rozalia contact details">
                @if(filled($footerPhone))<a href="tel:{{ preg_replace('/\\D+/', '', (string) $footerPhone) }}">{{ preg_replace('/\\D+/', '', (string) $footerPhone) }}</a>@endif
                @if(filled($footerEmail))<a href="mailto:{{ $footerEmail }}">{{ $footerEmail }}</a>@endif
                @if(filled($footerWebsite))<a href="{{ $footerWebsiteUrl }}" target="_blank" rel="noopener">{{ $footerWebsite }}</a>@endif
                @if(filled($footerLocation))<span>{{ $footerLocation }}</span>@endif
            </div>
            <div class="socials" aria-label="Social profiles">
                @foreach((array) data_get($siteLayoutRegions, 'footer.social_links', []) as $social)
                    @php($socialHref = $layoutUrl($social))
                    @if($socialHref)
                        <a href="{{ $socialHref }}" aria-label="{{ data_get($social, 'label', 'Social profile') }}" rel="me noopener" target="_blank">
                            <x-icon name="{{ data_get($social, 'icon', 'link') }}" size="15" />
                        </a>
                    @endif
                @endforeach
            </div>
        </section>

        @foreach($footerColumns as $footerColumn)
            <nav class="footer-column" aria-label="{{ data_get($footerColumn, 'title', 'Footer links') }}">
                <h4>{{ data_get($footerColumn, 'title') }}</h4>
                @foreach((array) data_get($footerColumn, 'links', []) as $footerLink)
                    @php($footerHref = $layoutUrl($footerLink))
                    @if($footerHref)
                        <a href="{{ $footerHref }}">{{ data_get($footerLink, 'label') }}</a>
                    @endif
                @endforeach
                @if(strtoupper((string) data_get($footerColumn, 'title')) === 'COMPANY')
                    @foreach($footerPages ?? [] as $footerPage)
                        <a href="{{ route('content.page', ['page' => $footerPage->slug]) }}">{{ $footerPage->title }}</a>
                    @endforeach
                @endif
            </nav>
        @endforeach

        @if($newsletterEnabled)
            <section class="newsletter" aria-label="{{ data_get($newsletter, 'title', 'Newsletter') }}">
                <h4>{{ data_get($newsletter, 'title', 'NEWSLETTER') }}</h4>
                <p>{{ data_get($newsletter, 'description', 'Stay updated with new arrivals and offers.') }}</p>
                @if($newsletterHref)
                    <form class="footer-newsletter-form" method="get" action="{{ $newsletterHref }}">
                        <label class="sr-only" for="footer-newsletter-email">{{ data_get($newsletter, 'placeholder', 'Your email address') }}</label>
                        <input id="footer-newsletter-email" type="email" name="email" autocomplete="email" placeholder="{{ data_get($newsletter, 'placeholder', 'Your email address') }}" aria-label="{{ data_get($newsletter, 'placeholder', 'Your email address') }}">
                        <button type="submit" aria-label="{{ data_get($newsletter, 'cta_label', 'Submit email') }}" title="{{ data_get($newsletter, 'cta_label', 'Submit email') }}"><x-icon name="arrow-right" size="16" /></button>
                    </form>
                @endif
                <div class="payments" aria-label="Accepted payment methods">
                    <span class="payment-badge payment-badge--visa" aria-label="Visa">VISA</span>
                    <span class="payment-badge payment-badge--mastercard" aria-label="Mastercard"><i></i><i></i></span>
                    <span class="payment-word payment-word--paypal">PayPal</span>
                    <span class="payment-word payment-word--apple">Apple Pay</span>
                    <span class="payment-word payment-word--gpay"><b>G</b> Pay</span>
                </div>
            </section>
        @endif
    </div>

    <div class="footer-bottom">
        <span class="footer-copyright">{{ $footerCopyright }}</span>
        <span class="footer-manufacturing"><x-icon name="clover" size="16" /> {{ $footerManufacturing }}</span>
        <nav class="footer-legal" aria-label="Legal">
            @foreach((array) data_get($siteLayoutRegions, 'footer.legal_links', []) as $legalLink)
                @php($legalHref = $layoutUrl($legalLink))
                @if($legalHref)
                    <a href="{{ $legalHref }}">{{ data_get($legalLink, 'label') }}</a>
                @endif
            @endforeach
        </nav>
    </div>
</footer>
@stack('scripts')
<script src="/js/app.js?v=20260905-scheduler"></script>
</body>
</html>
