<!doctype html>
<html lang="{{str_replace('_','-',app()->getLocale())}}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Emerald Rozalia')</title>
    <meta name="description" content="Emerald Rozalia — Irish made hats and caps, proudly manufacturing in Limerick, Ireland.">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="site-body">
<div class="topline">
    <span>✿ &nbsp; Proudly Manufacturing in Limerick, Ireland</span>
    <strong>Irish Made. Limerick Born. <em>Worn Everywhere.</em></strong>
    <div class="topline-tools">
        <form method="post" action="/context/language">@csrf
            <select name="locale" aria-label="Language" onchange="this.form.submit()">
                @foreach(\App\Models\Language::where('active',true)->get() as $l)<option value="{{$l->locale}}" @selected(($activeLocale??'en')===$l->locale)>🇮🇪 {{$l->locale}}</option>@endforeach
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
    <a href="/" class="brand"><img src="/assets/brand/emerald-rozalia-wordmark.png" alt="Emerald Rozalia Limited"></a>
    <button class="nav-toggle" data-nav-toggle aria-label="Open menu">☰</button>
    <nav data-nav><a href="/">HOME</a><a href="/shop">SHOP</a><a href="/collections">COLLECTIONS</a><a href="/new-arrivals">NEW ARRIVALS</a><a href="/corporate-orders">CORPORATE ORDER</a><a href="/bulk-orders">BULK ORDER</a><a href="/franchise">FRANCHISE APPLY</a><a href="/careers">HIRING APPLY</a></nav>
    <div class="utilities"><a href="/shop" aria-label="Search">⌕</a><a href="/account" aria-label="Account">♙</a><a href="/cart" aria-label="Cart">♧ <small>{{count(session('cart',[]))?'('.array_sum(array_column(session('cart',[]),'quantity')).')':''}}</small></a></div>
</header>
@if(session('success'))<div class="flash success">{{session('success')}}</div>@endif
@if($errors->any())<div class="flash error">{{implode(' ',$errors->all())}}</div>@endif
<main>@yield('content')</main>
<footer class="site-footer">
    <div class="footer-brand"><img src="/assets/brand/emerald-rozalia-wordmark.png" alt="Emerald Rozalia"><p>Proudly manufacturing<br>hats and caps in Limerick, Ireland.</p><div class="socials">f &nbsp; ◎ &nbsp; ♪ &nbsp; in &nbsp; ▶</div></div>
    <div><h4>SHOP</h4><a href="/shop">All Products</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/category/bucket-hats">Bucket Hats</a><a href="/category/snapbacks">Snapbacks</a><a href="/irish-traditional">Flat Caps</a></div>
    <div><h4>COLLECTIONS</h4><a href="/irish-traditional">Irish Traditional</a><a href="/irish-heritage">Irish Heritage</a><a href="/new-arrivals">New Arrivals</a><a href="/collections">Premium Collection</a></div>
    <div><h4>CUSTOMER CARE</h4><a href="/factory">Size Guide</a><a href="/factory">Shipping & Delivery</a><a href="/factory">Returns & Refunds</a><a href="/contact">Contact Us</a></div>
    <div><h4>COMPANY</h4><a href="/factory">Our Story</a><a href="/factory">Manufacturing</a><a href="/global-network">Sustainability</a><a href="/careers">Careers</a></div>
    <div class="newsletter"><h4>NEWSLETTER</h4><p>Stay updated with new arrivals and offers.</p><form><input type="email" placeholder="Your email address" aria-label="Your email address"><button class="btn" type="button">→</button></form><p class="payments">VISA &nbsp; ●● &nbsp; PayPal &nbsp;  Pay &nbsp; G Pay</p></div>
    <div class="footer-bottom"><span>© 2024 Emerald Rozalia Limited. All rights reserved.</span><span>♧ &nbsp; Designed & Manufactured in Limerick, Ireland</span><span><a href="/factory">Privacy Policy</a> &nbsp; <a href="/factory">Terms & Conditions</a></span></div>
</footer>
<script src="/js/app.js"></script>
</body>
</html>
