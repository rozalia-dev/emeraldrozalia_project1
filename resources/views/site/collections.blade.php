@extends('layouts.site')
@section('body-class','collections-reference-page')
@section('title','Collections — Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/collections.css?v=20260908-approved">
@endpush
@section('content')
@php
    $collectionCards = [
        ['slug'=>'baseball-caps','title'=>'BASEBALL CAPS','copy'=>'Classic. Everyday. Made to perform.','reference'=>'baseball','href'=>'/shop?category=baseball-caps'],
        ['slug'=>'bucket-hats','title'=>'BUCKET HATS','copy'=>'Comfortable. Versatile. Timeless.','reference'=>'bucket','href'=>'/shop?category=bucket-hats'],
        ['slug'=>'snapbacks','title'=>'SNAPBACKS','copy'=>'Modern fit. Stand out.','reference'=>'snapback','href'=>'/shop?category=snapbacks'],
        ['slug'=>'irish-traditional-flat-caps','title'=>'IRISH TRADITIONAL FLAT CAPS','copy'=>'Authentic style. Irish tradition.','reference'=>'traditional','href'=>'/irish-traditional','new'=>true],
        ['slug'=>'irish-heritage-hats','title'=>'IRISH HERITAGE HATS','copy'=>'Heritage designs. Timeless elegance.','reference'=>'heritage','href'=>'/irish-heritage'],
        ['slug'=>'beanies-more','title'=>'BEANIES & MORE','copy'=>'Warm. Stylish. Essential.','reference'=>'beanie','href'=>'/shop?category=beanies-more'],
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

<div class="collections-reference-shell" data-approved-reference="hats collection.png">
    <span class="sr-only">OUR COLLECTIONS</span>
    <section class="home-benefits collections-benefits" aria-label="Emerald Rozalia benefits">
        <div><span class="home-benefit-icon"><x-icon name="clover" size="34" /></span><b>MADE IN LIMERICK</b><span>Proudly designing &amp; manufacturing in Ireland.</span></div>
        <div><span class="home-benefit-icon"><x-icon name="star" size="34" /></span><b>PREMIUM QUALITY</b><span>Built to last with the finest materials.</span></div>
        <div><span class="home-benefit-icon"><x-icon name="users" size="34" /></span><b>TRADE &amp; BULK ORDERS WELCOME</b><span>Solutions for businesses of all sizes.</span></div>
        <div><span class="home-benefit-icon"><x-icon name="truck" size="34" /></span><b>FAST DISPATCH WORLDWIDE</b><span>Reliable delivery across the globe.</span></div>
        <div><span class="home-benefit-icon"><x-icon name="globe" size="34" /></span><b>GLOBAL REACH</b><span>Irish roots. Worn everywhere.</span></div>
    </section>

    <section class="home-section home-collections collections-reference-grid-section">
        <div class="home-section-heading collections-heading"><span></span><h1>SHOP BY COLLECTIONS</h1><span></span></div>
        <div class="home-collection-grid collections-reference-grid">
            @foreach($collectionCards as $item)
                @php($category = $categories->firstWhere('slug',$item['slug']))
                @php($href = $category ? route('category',['category'=>$category->slug]) : $item['href'])
                <a class="home-collection-card collections-reference-card" href="{{ $href }}">
                    <div class="home-reference-placeholder home-reference-placeholder--collection home-reference-placeholder--{{ $item['reference'] }} collections-reference-photo" role="img" aria-label="{{ $item['title'] }} collection image">
                        @if(!empty($item['new']))<b class="collections-new-badge">NEW</b>@endif
                    </div>
                    <div><h2>{{ $item['title'] }}</h2><p>{{ $item['copy'] }}</p><span>SHOP NOW <b aria-hidden="true"><x-icon name="arrow-right" /></b></span></div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="home-heritage collections-heritage">
        <div class="home-heritage-copy">
            <p class="eyebrow">THE IRISH HERITAGE<br>COLLECTION</p>
            <h2>Tradition, Made in Limerick.</h2>
            <p>Inspired by generations of Irish craftsmanship. Our flat caps and heritage hats are woven from premium fabrics and made to last.</p>
            <a class="btn" href="/irish-heritage">EXPLORE HERITAGE COLLECTION <x-icon name="arrow-right" /></a>
        </div>
        <div class="home-heritage-visual home-reference-image home-reference-image--heritage collections-heritage-visual" role="img" aria-label="Irish heritage flat cap, craftsmanship and Limerick collection photography">
            <div class="collections-heritage-points" aria-label="Irish heritage commitments">
                <span><x-icon name="clover" size="26" />AUTHENTIC<br>IRISH STYLE</span>
                <span><x-icon name="package" size="26" />PREMIUM<br>TWEED &amp; WOOL</span>
                <span><x-icon name="settings" size="26" />EXPERT<br>CRAFTSMANSHIP</span>
                <span><x-icon name="home" size="26" />MADE IN<br>LIMERICK</span>
            </div>
        </div>
    </section>

    <section class="home-section home-bestsellers collections-bestsellers">
        <div class="home-section-heading home-section-heading--left collections-bestseller-heading">
            <h2>BESTSELLERS</h2>
            <span></span>
            <a href="/shop">VIEW ALL <x-icon name="arrow-right" /></a>
        </div>
        <div class="home-product-grid collections-product-grid">
            @if($bestsellers->isNotEmpty())
                @foreach($bestsellers as $index => $product)
                    <article class="home-product-card collections-product-card">
                        <a class="collections-product-link" href="{{ route('product',['product'=>$product->slug]) }}">
                            <div class="home-product-media home-reference-placeholder home-reference-placeholder--product home-reference-placeholder--product-{{ $referenceProductClasses[$index % 6] }}">
                                @if($imageUrl = $productImageUrl($product->image))<img src="{{ $imageUrl }}" alt="{{ $product->name }}">@endif
                            </div>
                            <span>{{ $product->name }}</span>
                            <strong>€{{ number_format($product->price,2) }}</strong>
                        </a>
                        <form method="post" action="{{ route('cart.add',$product) }}" class="collections-cart-form">
                            @csrf
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" aria-label="Add {{ $product->name }} to cart"><x-icon name="shopping-bag" size="15" /></button>
                        </form>
                    </article>
                @endforeach
            @else
                @foreach($fallbackProducts as $item)
                    <article class="home-product-card collections-product-card">
                        <a class="collections-product-link" href="/shop">
                            <div class="home-product-media home-reference-placeholder home-reference-placeholder--product home-reference-placeholder--product-{{ $item['reference'] }}" role="img" aria-label="{{ $item['name'] }} product image"></div>
                            <span>{{ $item['name'] }}</span>
                            <strong>€{{ number_format($item['price'],2) }}</strong>
                        </a>
                        <a class="collections-cart-fallback" href="/shop" aria-label="Shop {{ $item['name'] }}"><x-icon name="shopping-bag" size="15" /></a>
                    </article>
                @endforeach
            @endif
        </div>
    </section>
</div>
@endsection
