<!doctype html>
<html lang="{{str_replace('_','-',app()->getLocale())}}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Emerald Rozalia')</title>
    <meta name="description" content="Emerald Rozalia — Irish made hats and caps, proudly manufacturing in Limerick, Ireland.">
    <link rel="stylesheet" href="/css/app.css?v=20260905-public-header-type">
</head>
<body class="site-body @yield('body-class')">
<div class="topline">
    <span><x-icon name="clover" size="14" /> Proudly Manufacturing in Limerick, Ireland</span>
    <strong>Irish Made. Limerick Born. <em>Worn Everywhere.</em></strong>
    <div class="topline-tools">
        <form method="post" action="/context/language">@csrf
            <select name="locale" aria-label="Language" onchange="this.form.submit()">
            @foreach(\App\Models\Language::where('active',true)->get() as $l)<option value="{{$l->locale}}" @selected(($activeLocale??'en')===$l->locale)>{{strtoupper($l->locale)}}</option>@endforeach
            </select>
        </form>
        <form method="post" action="/context/currency">@csrf
            <select name="currency" aria-label="Currency" onchange="this.form.submit()">
                @foreach(\App\Models\Currency::where('active',true)->get() as $c)<option value="{{$c->code}}" @selected(($activeCurrency??'EUR')===$c->code)>{{$c->code}} {{$c->symbol}}</option>@endforeach
            </select>
        </form>
    </div>
</div>
<header class="site-header">
    <a href="/" class="brand">
        <img class="brand-logo-image" src="{{asset('assets/logo/logo_one_line.png')}}" alt="Emerald Rozalia Limited">
    </a>
    <button class="nav-toggle" data-nav-toggle aria-label="Open menu"><x-icon name="menu" size="22" /></button>
    <nav data-nav>
        <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="/" @if(request()->routeIs('home')) aria-current="page" @endif>HOME</a>
        <a class="{{ request()->routeIs('shop','category','product') ? 'is-active' : '' }}" href="/shop" @if(request()->routeIs('shop','category','product')) aria-current="page" @endif>SHOP</a>
        <a class="{{ request()->routeIs('collections','irish.traditional','irish.heritage') ? 'is-active' : '' }}" href="/collections" @if(request()->routeIs('collections','irish.traditional','irish.heritage')) aria-current="page" @endif>COLLECTIONS</a>
        <a class="{{ request()->routeIs('new.arrivals') ? 'is-active' : '' }}" href="/new-arrivals" @if(request()->routeIs('new.arrivals')) aria-current="page" @endif>NEW ARRIVALS</a>
        <a class="{{ request()->routeIs('corporate.orders') ? 'is-active' : '' }}" href="/corporate-orders" @if(request()->routeIs('corporate.orders')) aria-current="page" @endif>CORPORATE ORDER</a>
        <a class="{{ request()->routeIs('bulk.orders') ? 'is-active' : '' }}" href="/bulk-orders" @if(request()->routeIs('bulk.orders')) aria-current="page" @endif>BULK ORDER</a>
        <a class="{{ request()->routeIs('franchise') ? 'is-active' : '' }}" href="/franchise" @if(request()->routeIs('franchise')) aria-current="page" @endif>FRANCHISE APPLY</a>
        <a class="{{ request()->routeIs('careers') ? 'is-active' : '' }}" href="/careers" @if(request()->routeIs('careers')) aria-current="page" @endif>HIRING APPLY</a>
        <a class="{{ request()->routeIs('contact') ? 'is-active' : '' }}" href="/contact" @if(request()->routeIs('contact')) aria-current="page" @endif>CONTACT US</a>
    </nav>
    <div class="utilities"><a href="/shop" aria-label="Search"><x-icon name="search" size="20" /></a><a href="/account" aria-label="Account"><x-icon name="user" size="20" /></a><a href="/cart" aria-label="Cart"><x-icon name="shopping-bag" size="20" /><small>{{count(session('cart',[]))?'('.array_sum(array_column(session('cart',[]),'quantity')).')':''}}</small></a></div>
</header>
@if(session('success'))<div class="flash success">{{session('success')}}</div>@endif
@if($errors->any())<div class="flash error">{{implode(' ',$errors->all())}}</div>@endif
<main>@yield('content')</main>
<footer class="site-footer">
    <div class="footer-brand"><img class="brand-logo-image" src="{{asset('assets/logo/logo_two_line.png')}}" alt="Emerald Rozalia Limited"><p>Proudly manufacturing<br>hats and caps in Limerick, Ireland.</p><div class="socials"><x-icon name="facebook" label="Facebook" /><x-icon name="instagram" label="Instagram" /><x-icon name="music" label="TikTok" /><x-icon name="linkedin" label="LinkedIn" /><x-icon name="youtube" label="YouTube" /></div></div>
    <div><h4>SHOP</h4><a href="/shop">All Products</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/category/bucket-hats">Bucket Hats</a><a href="/category/snapbacks">Snapbacks</a><a href="/irish-traditional">Flat Caps</a></div>
    <div><h4>COLLECTIONS</h4><a href="/irish-traditional">Irish Traditional</a><a href="/irish-heritage">Irish Heritage</a><a href="/new-arrivals">New Arrivals</a><a href="/collections">Premium Collection</a></div>
    <div><h4>CUSTOMER CARE</h4><a href="/factory">Size Guide</a><a href="/factory">Shipping & Delivery</a><a href="/factory">Returns & Refunds</a><a href="/contact">Contact Us</a></div>
    <div><h4>COMPANY</h4><a href="/factory">Our Story</a><a href="/factory">Manufacturing</a><a href="/global-network">Sustainability</a><a href="/careers">Careers</a></div>
    <div class="newsletter"><h4>NEWSLETTER</h4><p>Stay updated with new arrivals and offers.</p><form><input type="email" placeholder="Your email address" aria-label="Your email address"><button class="btn" type="button" aria-label="Subscribe"><x-icon name="arrow-right" /></button></form><p class="payments">VISA &nbsp; Mastercard &nbsp; PayPal &nbsp; Apple Pay &nbsp; Google Pay</p></div>
    <div class="footer-bottom"><span>© 2024 Emerald Rozalia Limited. All rights reserved.</span><span><x-icon name="clover" size="14" /> Designed &amp; Manufactured in Limerick, Ireland</span><span><a href="/factory">Privacy Policy</a> &nbsp; <a href="/factory">Terms &amp; Conditions</a></span></div>
</footer>
<script src="/js/app.js?v=20260905-scheduler"></script>
</body>
</html>
