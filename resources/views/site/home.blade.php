@extends('layouts.site')
@section('title','Emerald Rozalia — Irish Made Hats & Caps')
@section('content')
@php
    $homeCollections = [
        ['slug'=>'baseball-caps','title'=>'BASEBALL CAPS','copy'=>'Classic. Everyday. Made to perform.'],
        ['slug'=>'bucket-hats','title'=>'BUCKET HATS','copy'=>'Comfortable. Versatile. Timeless.'],
        ['slug'=>'snapbacks','title'=>'SNAPBACKS','copy'=>'Modern fit. Stand out.'],
        ['slug'=>'irish-traditional-flat-caps','title'=>'IRISH TRADITIONAL FLAT CAPS','copy'=>'Authentic style. Irish tradition.'],
        ['slug'=>'irish-heritage-hats','title'=>'IRISH HERITAGE HATS','copy'=>'Heritage designs. Timeless elegance.'],
        ['slug'=>'beanies-more','title'=>'BEANIES & MORE','copy'=>'Warm. Stylish. Essential.'],
    ];
@endphp

<section class="home-hero">
    <div class="home-hero-copy">
        <p class="eyebrow">IRISH MADE. LIMERICK BORN.</p>
        <h1>CRAFTED IN <em>LIMERICK.</em><br>WORN EVERYWHERE.</h1>
        <p>We are a Limerick based Irish manufacturer of premium hats and caps. Quality craftsmanship. Irish roots. Global reach.</p>
        <div class="actions"><a class="btn" href="/new-arrivals">SHOP NEW ARRIVALS <span aria-hidden="true">&rarr;</span></a><a class="btn ghost" href="/factory">OUR MANUFACTURING STORY</a></div>
    </div>
    <div class="home-hero-visual">
        <div class="hero-source-placeholder"><img src="/assets/brand/emerald-rozalia-headwear-badge.png" alt="Emerald Rozalia headwear badge"><span>Approved hero photography<br>awaiting source archive</span></div>
        <aside class="try-card"><p class="eyebrow">VIRTUAL TRY-ON</p><h2>See It.<br>Love It.<br>Own It.</h2><p>Upload your photo and see how our hats look on you.</p><a class="btn" href="/virtual-tryon">START TRY-ON <span aria-hidden="true">&rarr;</span></a><small>100% Private &amp; Secure</small></aside>
    </div>
</section>

<section class="home-benefits" aria-label="Emerald Rozalia benefits">
    <div><b>MADE IN LIMERICK</b><span>Proudly designing &amp; manufacturing in Ireland.</span></div>
    <div><b>PREMIUM QUALITY</b><span>Built to last with the finest materials.</span></div>
    <div><b>TRADE &amp; BULK ORDERS WELCOME</b><span>Solutions for businesses of all sizes.</span></div>
    <div><b>FAST DISPATCH WORLDWIDE</b><span>Reliable delivery across the globe.</span></div>
    <div><b>GLOBAL REACH</b><span>Irish roots. Worn everywhere.</span></div>
</section>

<section class="home-section home-collections">
    <div class="home-section-heading"><span></span><h2>SHOP BY COLLECTION</h2><span></span></div>
    <div class="home-collection-grid">
        @foreach($homeCollections as $item)
            @php($category = $categories->firstWhere('slug',$item['slug']))
            @if($category)
                <a class="home-collection-card" href="{{route('category',$category)}}">
                    <div class="home-reference-placeholder" role="img" aria-label="{{ $item['title'] }} image pending approved source archive">IMAGE PENDING</div>
                    <div><h3>{{ $item['title'] }}</h3><p>{{ $item['copy'] }}</p><span>SHOP NOW &rarr;</span></div>
                </a>
            @endif
        @endforeach
    </div>
</section>

<section class="home-heritage">
    <div class="home-heritage-copy"><p class="eyebrow">THE IRISH HERITAGE COLLECTION</p><h2>Tradition, Made in Limerick.</h2><p>Inspired by generations of Irish craftsmanship. Our flat caps and heritage hats are woven from premium fabrics and made to last.</p><a class="btn" href="/category/irish-heritage-hats">EXPLORE HERITAGE COLLECTION <span aria-hidden="true">&rarr;</span></a></div>
    <div class="home-heritage-visual" role="img" aria-label="Irish heritage collection imagery pending approved source archive">APPROVED COLLECTION IMAGE PENDING</div>
</section>

<section class="home-section home-bestsellers">
    <div class="home-section-heading home-section-heading--left"><h2>BESTSELLERS</h2><a href="/shop">VIEW ALL &rarr;</a></div>
    <div class="home-product-grid">
        @forelse($newProducts as $product)
            <a class="home-product-card" href="{{route('product',$product)}}"><div class="home-reference-placeholder">@if($product->image)<img src="{{Storage::url($product->image)}}" alt="{{ $product->name }}">@else IMAGE PENDING @endif</div><span>{{ $product->name }}</span><strong>€{{number_format($product->price,2)}}</strong></a>
        @empty
            <p class="empty-note">Products will appear here after catalogue media is approved.</p>
        @endforelse
    </div>
</section>

<section class="home-quality">
    <div class="home-quality-visual" role="img" aria-label="Manufacturing process imagery pending approved source archive">APPROVED MANUFACTURING IMAGE PENDING</div>
    <div class="home-quality-copy"><p class="eyebrow">FROM CONCEPT TO CREATION.</p><h2>QUALITY IN EVERY STITCH.</h2><p>Every hat and cap is made in-house by our skilled team in Limerick. From design and pattern engineering to embroidery, finishing and quality inspection.</p><a class="btn ghost" href="/factory">SEE OUR PROCESS <span aria-hidden="true">&rarr;</span></a></div>
</section>

<section class="home-franchise">
    <div><p class="eyebrow">FRANCHISE OPEN NOW</p><h2>FOR IRELAND</h2><p>Be part of Emerald Rozalia's growth journey. Own an exclusive territory and build a legacy with an Irish brand.</p><a class="btn" href="/franchise">APPLY FOR FRANCHISE <span aria-hidden="true">&rarr;</span></a></div>
    <div class="home-franchise-visual" role="img" aria-label="Franchise store imagery pending approved source archive">APPROVED FRANCHISE IMAGE PENDING</div>
</section>
@endsection
