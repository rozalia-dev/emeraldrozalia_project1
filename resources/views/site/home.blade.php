@extends('layouts.site')
@section('body-class','home-body')
@section('title','Emerald Rozalia — Irish Made Hats & Caps')
@push('styles')
<link rel="stylesheet" href="/css/home-collections.css?v=20260908-approved">
@endpush
@section('content')
<div class="home-page" data-home-collections-reference="approved-2026-09-08" data-homepage-page-uuid="{{ $homepage?->uuid ?: 'reserved-homepage-pending' }}" data-homepage-source="{{ $homepage ? 'content-page-active-record' : 'reserved-homepage-fallback' }}">
@if(($previewMode ?? false) || ($layoutPreviewMode ?? false))<div class="managed-page-preview-banner" role="status">Previewing {{ ($layoutPreviewMode ?? false) ? 'shared layout · Homepage render' : 'Homepage' }} · unpublished preview <a href="{{ ($layoutPreviewMode ?? false) ? route('admin.pages.layouts') : route('admin.pages.edit', $homepage) }}">Return to control panel</a></div>@endif
@php
    $homeCollections = [
        ['slug'=>'baseball-caps','title'=>'BASEBALL CAPS','copy'=>'Classic. Everyday. Made to perform.','reference'=>'baseball'],
        ['slug'=>'bucket-hats','title'=>'BUCKET HATS','copy'=>'Comfortable. Versatile. Timeless.','reference'=>'bucket'],
        ['slug'=>'snapbacks','title'=>'SNAPBACKS','copy'=>'Modern fit. Stand out.','reference'=>'snapback'],
        ['slug'=>'irish-traditional-flat-caps','title'=>'IRISH TRADITIONAL FLAT CAPS','copy'=>'Authentic style. Irish tradition.','reference'=>'traditional','new'=>true],
        ['slug'=>'irish-heritage-hats','title'=>'IRISH HERITAGE HATS','copy'=>'Heritage designs. Timeless elegance.','reference'=>'heritage'],
        ['slug'=>'beanies-more','title'=>'BEANIES & MORE','copy'=>'Warm. Stylish. Essential.','reference'=>'beanie'],
    ];
    $fallbackProducts = [
        ['name'=>'Classic Emerald Cap','price'=>34.99,'reference'=>'one'],
        ['name'=>'Emerald Signature Cap','price'=>34.99,'reference'=>'two'],
        ['name'=>'Rozalia Snapback','price'=>36.99,'reference'=>'three'],
        ['name'=>'Emerald Flat Cap','price'=>44.99,'reference'=>'four'],
        ['name'=>'Emerald Beanie','price'=>29.99,'reference'=>'five'],
        ['name'=>'Emerald Trucker Cap','price'=>34.99,'reference'=>'six'],
    ];
    $referenceProductClasses = ['one','two','three','four','five','six'];
    $productImageUrl = static function (?string $path): ?string {
        if (blank($path)) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) return $path;
        return Storage::disk('public')->url($path);
    };
@endphp

<section class="home-hero">
    <div class="home-hero-composition" aria-labelledby="home-hero-title">
        <picture>
            <source media="(min-width: 701px)" srcset="/assets/brand/home-page-hero-reference@2x.png?v=20260904-hero">
            <img src="/assets/brand/home-page-hero-reference.png?v=20260904-hero" alt="CRAFTED IN LIMERICK. WORN EVERYWHERE. EMERALD ROZALIA VIRTUAL TRY-ON CAMPAIGN.">
        </picture>
        <h1 id="home-hero-title" class="sr-only">CRAFTED IN LIMERICK. WORN EVERYWHERE.</h1>
        <p class="sr-only">VIRTUAL TRY-ON lets you preview Emerald Rozalia hats on your photo.</p>
        <a class="home-hero-hotspot home-hero-hotspot--arrivals" href="/new-arrivals" aria-label="Shop new arrivals"></a>
        <a class="home-hero-hotspot home-hero-hotspot--factory" href="/factory" aria-label="Our manufacturing story"></a>
        <a class="home-hero-hotspot home-hero-hotspot--tryon" href="/virtual-tryon" aria-label="Start virtual try-on"></a>
    </div>
</section>

@if(isset($banners) && $banners->isNotEmpty())
<section class="home-published-banners" data-public-source="published-banner-records" data-banner-position="Home - Main Slider" aria-label="Published Emerald Rozalia banners">
    @foreach($banners as $banner)
        @php($bannerTarget = filled($banner->target_url) ? $banner->target_url : null)
        @if($bannerTarget)<a class="home-published-banner" href="{{ $bannerTarget }}" data-banner-public-uuid="{{ $banner->public_uuid }}">@else<div class="home-published-banner" data-banner-public-uuid="{{ $banner->public_uuid }}">@endif
            @if($banner->imageUrl())
                <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt_text ?: $banner->title }}" @if($banner->title_text) title="{{ $banner->title_text }}" @endif>
            @endif
            <div class="home-published-banner__copy">
                @if($banner->title)<strong>{{ $banner->title }}</strong>@endif
                @if($banner->subtitle)<span>{{ $banner->subtitle }}</span>@endif
            </div>
        @if($bannerTarget)</a>@else</div>@endif
    @endforeach
</section>
@endif

<section class="home-benefits" aria-label="Emerald Rozalia benefits">
    <div><span class="home-benefit-icon"><x-icon name="clover" size="42" /></span><span class="home-benefit-copy"><b>MADE IN LIMERICK</b><small>Proudly designing &amp;<br>manufacturing in Ireland.</small></span></div>
    <div><span class="home-benefit-icon"><x-icon name="star" size="42" /></span><span class="home-benefit-copy"><b>PREMIUM QUALITY</b><small>Built to last with the<br>finest materials.</small></span></div>
    <div><span class="home-benefit-icon"><x-icon name="users" size="42" /></span><span class="home-benefit-copy"><b>TRADE &amp; BULK<br>ORDERS WELCOME</b><small>Solutions for businesses<br>of all sizes.</small></span></div>
    <div><span class="home-benefit-icon"><x-icon name="truck" size="42" /></span><span class="home-benefit-copy"><b>FAST DISPATCH<br>WORLDWIDE</b><small>Reliable delivery<br>across the globe.</small></span></div>
    <div><span class="home-benefit-icon"><x-icon name="globe" size="42" /></span><span class="home-benefit-copy"><b>GLOBAL REACH</b><small>Irish roots.<br>Worn everywhere.</small></span></div>
</section>

<section class="home-section home-collections">
    <div class="home-section-heading"><span></span><h2>SHOP BY COLLECTIONS</h2><span></span></div>
    <div class="home-collection-grid">
        @foreach($homeCollections as $item)
            @php($category = $categories->firstWhere('slug',$item['slug']))
            @php($href = $category ? route('category',$category) : url('/shop').'?category='.rawurlencode($item['slug']))
            <a class="home-collection-card" href="{{$href}}">
                <div class="home-reference-placeholder home-reference-placeholder--collection home-reference-placeholder--{{$item['reference']}}" role="img" aria-label="{{ $item['title'] }} collection image">
                    @if(!empty($item['new']))<span class="home-collection-new">NEW</span>@endif
                </div>
                <div><h3>{{ $item['title'] }}</h3><p>{{ $item['copy'] }}</p><span>SHOP NOW <b aria-hidden="true"><x-icon name="arrow-right" /></b></span></div>
            </a>
        @endforeach
    </div>
</section>

<section class="home-heritage" aria-labelledby="home-heritage-title">
    <div class="home-heritage-copy">
        <div class="home-heritage-title"><x-icon name="clover" size="34" /><h2 id="home-heritage-title">THE IRISH HERITAGE COLLECTION</h2><x-icon name="clover" size="25" /></div>
        <h3>Tradition, Made in Limerick.</h3>
        <p>Inspired by generations of Irish craftsmanship. Our flat caps and heritage hats are woven from premium fabrics and made to last.</p>
        <a class="btn" href="/irish-heritage">EXPLORE HERITAGE COLLECTION <x-icon name="arrow-right" /></a>
    </div>
    <div class="home-heritage-visual home-reference-image home-reference-image--heritage" role="img" aria-label="Irish flat cap, Emerald Rozalia label, embroidery craftsmanship and Limerick heritage photography"></div>
    <div class="home-heritage-badges" aria-label="Irish heritage collection qualities">
        <div><x-icon name="clover" size="28" /><span>AUTHENTIC<br>IRISH STYLE</span></div>
        <div><x-icon name="package" size="28" /><span>PREMIUM<br>TWEED &amp; WOOL</span></div>
        <div><x-icon name="settings" size="28" /><span>EXPERT<br>CRAFTSMANSHIP</span></div>
        <div><x-icon name="home" size="28" /><span>MADE IN<br>LIMERICK</span></div>
    </div>
</section>

<section class="home-section home-bestsellers" data-home-bestsellers>
    <div class="home-section-heading home-section-heading--left"><h2>BESTSELLERS</h2><span></span><a href="/shop">VIEW ALL <x-icon name="arrow-right" /></a></div>
    <div class="home-product-carousel">
        <button class="home-carousel-arrow home-carousel-arrow--prev" type="button" data-home-carousel-prev aria-label="Previous bestsellers"><x-icon name="chevron-left" size="20" /></button>
        <div class="home-product-grid" data-home-carousel-track>
            @if($newProducts->isNotEmpty())
                @foreach($newProducts->take(6) as $index => $product)
                    <article class="home-product-card">
                        <a class="home-product-link" href="{{route('product',$product)}}">
                            <div class="home-product-media home-reference-placeholder home-reference-placeholder--product home-reference-placeholder--product-{{$referenceProductClasses[$index % 6]}}">
                                @if($imageUrl = $productImageUrl($product->image))<img src="{{$imageUrl}}" alt="{{ $product->name }}">@endif
                            </div>
                            <span>{{ $product->name }}</span><strong>€{{number_format($product->price,2)}}</strong>
                        </a>
                        @if((int)$product->stock > 0)
                            <form method="post" action="{{route('cart.add',$product)}}" class="home-product-cart-form">@csrf<input type="hidden" name="quantity" value="1"><button class="home-product-cart" type="submit" aria-label="Add {{ $product->name }} to cart"><x-icon name="shopping-bag" size="16" /></button></form>
                        @else
                            <a class="home-product-cart" href="{{route('product',$product)}}" aria-label="View {{ $product->name }}"><x-icon name="arrow-right" size="16" /></a>
                        @endif
                    </article>
                @endforeach
            @else
                @foreach($fallbackProducts as $item)
                    <article class="home-product-card">
                        <a class="home-product-link" href="/shop">
                            <div class="home-product-media home-reference-placeholder home-reference-placeholder--product home-reference-placeholder--product-{{$item['reference']}}" role="img" aria-label="{{$item['name']}} product image"></div>
                            <span>{{$item['name']}}</span><strong>€{{number_format($item['price'],2)}}</strong>
                        </a>
                        <a class="home-product-cart" href="/shop" aria-label="Shop {{$item['name']}}"><x-icon name="shopping-bag" size="16" /></a>
                    </article>
                @endforeach
            @endif
        </div>
        <button class="home-carousel-arrow home-carousel-arrow--next" type="button" data-home-carousel-next aria-label="Next bestsellers"><x-icon name="chevron-right" size="20" /></button>
    </div>
</section>

<section class="home-quality">
    <div class="home-quality-visual home-reference-image home-reference-image--quality" role="img" aria-label="Emerald Rozalia manufacturing and embroidery process"></div>
    <div class="home-quality-copy"><p class="eyebrow">FROM CONCEPT TO CREATION.</p><h2>QUALITY IN EVERY STITCH.</h2><p>Every hat and cap is made in-house by our skilled team in Limerick. From design and pattern engineering to embroidery, finishing and quality inspection.</p><a class="btn ghost" href="/factory">SEE OUR PROCESS <x-icon name="arrow-right" /></a></div>
</section>

<section class="home-franchise">
    <div><p class="eyebrow">FRANCHISE OPEN NOW</p><h2>FOR IRELAND</h2><p>Be part of Emerald Rozalia's growth journey. Own an exclusive territory and build a legacy with an Irish brand.</p><a class="btn" href="/franchise">APPLY FOR FRANCHISE <x-icon name="arrow-right" /></a></div>
    <div class="home-franchise-visual home-reference-image home-reference-image--franchise" role="img" aria-label="Emerald Rozalia franchise store and Ireland opportunity"></div>
</section>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-home-bestsellers]');
    if (!root) return;
    const track = root.querySelector('[data-home-carousel-track]');
    const step = () => Math.max(220, Math.round(track.clientWidth * .72));
    root.querySelector('[data-home-carousel-prev]')?.addEventListener('click', () => track.scrollBy({left:-step(),behavior:'smooth'}));
    root.querySelector('[data-home-carousel-next]')?.addEventListener('click', () => track.scrollBy({left:step(),behavior:'smooth'}));
})();
</script>
@endpush
