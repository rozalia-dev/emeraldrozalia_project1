@extends('layouts.site')
@section('body-class','home-body')
@section('title','Emerald Rozalia — Irish Made Hats & Caps')
@section('content')
<div class="home-page">
@php
    $homeCollections = [
        ['slug'=>'baseball-caps','title'=>'BASEBALL CAPS','copy'=>'Classic. Everyday. Made to perform.','reference'=>'baseball'],
        ['slug'=>'bucket-hats','title'=>'BUCKET HATS','copy'=>'Comfortable. Versatile. Timeless.','reference'=>'bucket'],
        ['slug'=>'snapbacks','title'=>'SNAPBACKS','copy'=>'Modern fit. Stand out.','reference'=>'snapback'],
        ['slug'=>'irish-traditional-flat-caps','title'=>'IRISH TRADITIONAL FLAT CAPS','copy'=>'Authentic style. Irish tradition.','reference'=>'traditional'],
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
@endphp

<section class="home-hero">
    <div class="home-hero-copy">
        <p class="eyebrow">IRISH MADE. LIMERICK BORN.</p>
        <h1><span>CRAFTED IN</span><em>LIMERICK.</em><span>WORN</span><span>EVERYWHERE.</span></h1>
        <p>We are a Limerick based Irish manufacturer of premium hats and caps. Quality craftsmanship. Irish roots. Global reach.</p>
        <div class="actions"><a class="btn" href="/new-arrivals">SHOP NEW ARRIVALS <x-icon name="arrow-right" /></a><a class="btn ghost" href="/factory">OUR MANUFACTURING STORY</a></div>
    </div>
    <div class="home-hero-visual">
        <div class="hero-source-placeholder home-reference-image home-reference-image--hero" role="img" aria-label="Emerald Rozalia hero campaign in Limerick"></div>
        <aside class="try-card">
            <p class="eyebrow">VIRTUAL TRY-ON</p>
            <h2>See It.<br>Love It.<br>Own It.</h2>
            <p>Upload your photo and see how our hats look on you.</p>
            <a class="home-upload-box" href="/virtual-tryon"><span class="home-upload-icon"><x-icon name="upload" size="26" /></span><strong>UPLOAD YOUR PHOTO</strong><small>or</small><span class="home-camera-button"><x-icon name="camera" size="13" /> TAKE PHOTO</span></a>
            <a class="btn home-try-button" href="/virtual-tryon">START TRY-ON <x-icon name="arrow-right" /></a>
            <small><x-icon name="check" size="11" /> 100% Private &amp; Secure</small>
            <div class="home-try-thumbs" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
        </aside>
    </div>
</section>

<section class="home-benefits" aria-label="Emerald Rozalia benefits">
    <div><span class="home-benefit-icon"><x-icon name="clover" size="29" /></span><b>MADE IN LIMERICK</b><span>Proudly designing &amp; manufacturing in Ireland.</span></div>
    <div><span class="home-benefit-icon"><x-icon name="star" size="29" /></span><b>PREMIUM QUALITY</b><span>Built to last with the finest materials.</span></div>
    <div><span class="home-benefit-icon"><x-icon name="users" size="29" /></span><b>TRADE &amp; BULK ORDERS WELCOME</b><span>Solutions for businesses of all sizes.</span></div>
    <div><span class="home-benefit-icon"><x-icon name="truck" size="29" /></span><b>FAST DISPATCH WORLDWIDE</b><span>Reliable delivery across the globe.</span></div>
    <div><span class="home-benefit-icon"><x-icon name="globe" size="29" /></span><b>GLOBAL REACH</b><span>Irish roots. Worn everywhere.</span></div>
</section>

<section class="home-section home-collections">
    <div class="home-section-heading"><span></span><h2>SHOP BY COLLECTION</h2><span></span></div>
    <div class="home-collection-grid">
        @foreach($homeCollections as $item)
            @php($category = $categories->firstWhere('slug',$item['slug']))
            @php($href = $category ? route('category',$category) : url('/shop').'?category='.rawurlencode($item['slug']))
            <a class="home-collection-card" href="{{$href}}">
                <div class="home-reference-placeholder home-reference-placeholder--collection home-reference-placeholder--{{$item['reference']}}" role="img" aria-label="{{ $item['title'] }} collection image"></div>
                <div><h3>{{ $item['title'] }}</h3><p>{{ $item['copy'] }}</p><span>SHOP NOW <b aria-hidden="true"><x-icon name="arrow-right" /></b></span></div>
            </a>
        @endforeach
    </div>
</section>

<section class="home-heritage">
    <div class="home-heritage-copy"><p class="eyebrow">THE IRISH HERITAGE COLLECTION</p><h2>Tradition, Made in Limerick.</h2><p>Inspired by generations of Irish craftsmanship. Our flat caps and heritage hats are woven from premium fabrics and made to last.</p><a class="btn" href="/category/irish-heritage-hats">EXPLORE HERITAGE COLLECTION <x-icon name="arrow-right" /></a></div>
    <div class="home-heritage-visual home-reference-image home-reference-image--heritage" role="img" aria-label="Irish heritage collection photography"></div>
</section>

<section class="home-section home-bestsellers">
    <div class="home-section-heading home-section-heading--left"><h2>BESTSELLERS</h2><a href="/shop">VIEW ALL <x-icon name="arrow-right" /></a></div>
    <div class="home-product-grid">
        @if($newProducts->isNotEmpty())
            @foreach($newProducts->take(6) as $index => $product)
                <a class="home-product-card" href="{{route('product',$product)}}">
                    <div class="home-product-media home-reference-placeholder home-reference-placeholder--product home-reference-placeholder--product-{{$referenceProductClasses[$index % 6]}}">
                        @if(filled($product->image))<img src="{{Storage::url($product->image)}}" alt="{{ $product->name }}">@endif
                    </div>
                    <span>{{ $product->name }}</span><strong>€{{number_format($product->price,2)}}</strong><b class="home-product-cart"><x-icon name="shopping-bag" size="14" /></b>
                </a>
            @endforeach
        @else
            @foreach($fallbackProducts as $item)
                <a class="home-product-card" href="/shop">
                    <div class="home-product-media home-reference-placeholder home-reference-placeholder--product home-reference-placeholder--product-{{$item['reference']}}" role="img" aria-label="{{$item['name']}} product image"></div>
                    <span>{{$item['name']}}</span><strong>€{{number_format($item['price'],2)}}</strong><b class="home-product-cart"><x-icon name="shopping-bag" size="14" /></b>
                </a>
            @endforeach
        @endif
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
