@extends('layouts.site')
@section('body-class','new-arrivals-reference-page')
@section('title','New Arrivals — Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/new-arrivals.css?v=20260908-approved&refresh=20260919-arrival-card-parity">
<link rel="stylesheet" href="/css/shop.css?v=20260919-catalog-type-scale">
@endpush
@section('content')
@php
    $selectedCategories = (array) request('category', []);
    $selectedColours = (array) request('colour', []);
    $selectedMaterials = (array) request('material', []);
    $colourOptions = [
        'black'=>'#111512','ivory'=>'#d8d2c1','stone'=>'#8d8878','brown'=>'#5a4327','olive'=>'#5f6226',
        'navy'=>'#18283a','camel'=>'#aa8455','green'=>'#294e32','forest'=>'#173c27'
    ];
    $materialOptions = ['Tweed','Wool','Cotton','Linen','Leather','Felt'];
    $priceCeiling = max(1, (int) ($priceCeiling ?? 1));
    $productImage = static function ($product): ?array {
        return app(\App\Services\PublicMediaResolver::class)->forProduct($product);
    };
    $swatchColour = static function ($value): string {
        $value = strtolower(trim((string) $value));
        $palette = ['black'=>'#111512','ivory'=>'#d8d2c1','stone'=>'#8d8878','brown'=>'#5a4327','olive'=>'#5f6226','navy'=>'#18283a','camel'=>'#aa8455','green'=>'#294e32','forest green'=>'#173c27','grey'=>'#686b67','gray'=>'#686b67','charcoal'=>'#363a37','cream'=>'#ded3b7','beige'=>'#b8a782'];
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)) return $value;
        return $palette[$value] ?? '#31412f';
    };
@endphp
<div class="arrival-page" data-public-media-register="new-arrivals" data-public-media-state="awaiting-approved-media">
    <p class="sr-only">Approved new-arrivals editorial media is not configured.</p>
    <section class="arrival-hero" data-public-media-state="awaiting-approved-media">
        <div class="arrival-hero-inner">
            <div class="arrival-breadcrumb"><a href="/">Home</a><x-icon name="chevron-right" size="12" /><span>New Arrivals</span></div>
            <h1>NEW ARRIVALS</h1>
            <div class="arrival-hero-rule" aria-hidden="true"><b>♣</b></div>
            <p class="arrival-hero-tagline">Fresh styles. Timeless heritage.</p>
            <p class="arrival-hero-copy">Discover our latest hats and caps, crafted in Limerick with care, quality and traditional craftsmanship.</p>
        </div>
    </section>

    <section class="arrival-benefits" aria-label="New arrivals benefits">
        <div class="arrival-benefit"><x-icon name="clover" size="30" /><b>JUST LANDED</b><span>The latest styles &amp; designs</span></div>
        <div class="arrival-benefit"><x-icon name="home" size="30" /><b>IRISH CRAFTSMANSHIP</b><span>Made in Limerick, Ireland</span></div>
        <div class="arrival-benefit"><x-icon name="star" size="30" /><b>PREMIUM QUALITY</b><span>Built to last, made to wear</span></div>
        <div class="arrival-benefit"><x-icon name="package" size="30" /><b>LIMITED STOCK</b><span>Grab yours before it's gone</span></div>
        <div class="arrival-benefit"><x-icon name="truck" size="30" /><b>FAST DELIVERY</b><span>Worldwide shipping</span></div>
    </section>

    <section class="arrival-catalog">
        <aside class="arrival-filters">
            <form id="arrival-filters" method="get" action="{{ route('new.arrivals') }}">
                @if(request()->filled('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                <div class="arrival-filter-title"><h2>FILTERS</h2><a href="{{ route('new.arrivals') }}">RESET ALL</a></div>

                <div class="arrival-filter-group">
                    <strong>CATEGORY</strong>
                    @foreach($categories->take(6) as $category)
                        <label class="arrival-check">
                            <input type="checkbox" name="category[]" value="{{ $category->slug }}" @checked(in_array($category->slug,$selectedCategories,true))>
                            <span>{{ $category->name }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="arrival-filter-group">
                    <strong>COLOR</strong>
                    <div class="arrival-colours">
                        @foreach($colourOptions as $colour => $hex)
                            <label class="arrival-colour" title="{{ str($colour)->headline() }}">
                                <input type="checkbox" name="colour[]" value="{{ $colour }}" aria-label="{{ str($colour)->headline() }}" @checked(in_array($colour,$selectedColours,true))>
                                <span style="background:{{ $hex }}"></span>
                            </label>
                        @endforeach
                    </div>
                    <span class="arrival-colour-more">+ More</span>
                </div>

                <div class="arrival-filter-group">
                    <strong>MATERIAL</strong>
                    @foreach($materialOptions as $material)
                        <label class="arrival-check">
                            <input type="checkbox" name="material[]" value="{{ $material }}" @checked(in_array($material,$selectedMaterials,true))>
                            <span>{{ $material }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="arrival-filter-group">
                    <strong>PRICE</strong>
                    @php($selectedMaxPrice = min($priceCeiling, max(0, (int) request('max_price', $priceCeiling))))
                    <div class="arrival-price-head"><span>€0</span><span id="arrival-price-value">€{{ $selectedMaxPrice }}</span></div>
                    <input type="range" name="max_price" aria-label="Maximum price" min="0" max="{{ $priceCeiling }}" step="1" value="{{ $selectedMaxPrice }}" oninput="document.getElementById('arrival-price-value').textContent='€'+this.value">
                </div>

                <button class="arrival-apply" type="submit">APPLY FILTERS</button>
            </form>
        </aside>

        <div class="arrival-results">
            <div class="arrival-results-head">
                <p>Showing {{ $products->firstItem() ?: 0 }}–{{ $products->lastItem() ?: 0 }} of {{ $products->total() }} products</p>
                <div class="arrival-sort">
                    <select name="sort" form="arrival-filters" aria-label="Sort new arrivals" onchange="document.getElementById('arrival-filters').submit()">
                        <option value="" @selected(blank(request('sort')))>Sort by: Newest</option>
                        <option value="price_low" @selected(request('sort')==='price_low')>Price: Low to High</option>
                        <option value="price_high" @selected(request('sort')==='price_high')>Price: High to Low</option>
                        <option value="name" @selected(request('sort')==='name')>Name: A–Z</option>
                    </select>
                </div>
            </div>

            <div class="arrival-product-grid">
                @forelse($products as $index => $product)
                    @php($rating = max(0,min(5,(int) round((float) ($product->reviews_avg_rating ?? 0)))))
                    @php($colours = collect($product->colours ?? [])->filter()->take(3))
                    @php($hasPublicSpin = $product->latestPublicSpin() !== null)
                    @php($image = $productImage($product))
                    <article class="arrival-product-card shop-product-card">
                        <div class="arrival-product-media shop-product-media" data-public-media-state="{{ $image ? 'approved' : 'awaiting-approved-media' }}">
                            <a class="arrival-product-link" href="{{ route('product',['product'=>$product->slug]) }}" aria-label="View {{ $product->name }}">
                                @if($image)
                                    <img src="{{ $image['url'] }}" @if($image['srcset']) srcset="{{ $image['srcset'] }}" sizes="{{ $image['sizes'] }}" @endif width="{{ $image['width'] ?: '' }}" height="{{ $image['height'] ?: '' }}" alt="{{ $image['alt'] }}">
                                @else
                                    <span class="shop-product-placeholder"><x-icon name="clover" size="34" /><b>EMERALD ROZALIA</b><small>Product image coming soon</small></span>
                                @endif
                            </a>
                            <div class="shop-card-badges"><span class="shop-badge shop-badge--new">NEW</span></div>
                            @if($hasPublicSpin)<span class="arrival-spin-badge">360°</span>@endif
                        </div>
                        <div class="arrival-product-info shop-product-content">
                            <a href="{{ route('product',['product'=>$product->slug]) }}" class="shop-product-main-link">
                                <small class="shop-product-category">{{ $product->category?->name ?: 'Emerald Rozalia' }}</small>
                                <h3>{{ $product->name }}</h3>
                                <div class="arrival-rating shop-rating" aria-label="Rated {{ number_format((float) ($product->reviews_avg_rating ?? 0), 1) }} out of 5">
                                    <span class="arrival-stars">{{ str_repeat('★',$rating) }}{{ str_repeat('☆',5-$rating) }}</span>
                                    <small class="arrival-reviews">({{ $product->reviews_count ?? 0 }})</small>
                                </div>
                                <div class="shop-price-row"><strong class="arrival-price">€{{ number_format((float)$product->price,2) }}</strong></div>
                            </a>
                            <div class="arrival-card-bottom shop-card-footer">
                                <div class="arrival-swatches shop-swatches" aria-label="Available colours">
                                    @forelse($colours as $colour)
                                        <span class="arrival-swatch" title="{{ $colour }}" style="--shop-swatch:{{ $swatchColour($colour) }}"></span>
                                    @empty
                                        <small class="arrival-no-swatches">Colour data not configured</small>
                                    @endforelse
                                </div>
                            </div>
                            <div class="shop-product-actions">
                                <a href="{{ route('product',['product'=>$product->slug]) }}">VIEW DETAILS</a>
                                <form method="post" action="{{ route('cart.add',$product) }}" class="arrival-cart-form">
                                    @csrf
                                    <input type="hidden" name="quantity" value="1">
                                    <button class="arrival-cart" type="submit" aria-label="Add {{ $product->name }} to cart"><x-icon name="shopping-bag" size="15" /> ADD TO CART</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="arrival-empty"><strong>No new arrivals match those filters.</strong><p>Reset the filters to see all newly launched Emerald Rozalia styles.</p>@include('site.partials.product-search-contact')</div>
                @endforelse
            </div>
            <div class="arrival-pagination">{{ $products->links() }}</div>
        </div>
    </section>

    <section class="arrival-tryon">
        <div class="arrival-tryon-side">
            <span class="arrival-camera" aria-hidden="true">◉</span>
            <div><h2>SEE IT ON YOU</h2><p>Use our Virtual Try-On Studio<br>to find your perfect fit.</p><a class="btn" href="{{ route('virtual-tryon') }}">TRY IT ON</a></div>
        </div>
        <div class="arrival-tryon-photos" data-public-media-state="awaiting-approved-media" aria-label="Approved Try-On editorial media is not configured"><span class="arrival-tryon-photo"></span><span class="arrival-tryon-photo"></span><span class="arrival-tryon-photo"></span><span class="arrival-tryon-photo"></span></div>
        <div class="arrival-return"><div><h2>LOVE IT OR RETURN IT</h2><p>30-day easy returns<br>for complete peace of mind.</p></div><span class="arrival-return-mark"><x-icon name="clover" size="40" /></span></div>
    </section>

    <section class="arrival-bottom-benefits" aria-label="Shopping assurances">
        <div class="arrival-benefit"><x-icon name="clover" size="30" /><b>IRISH MADE</b><span>Proudly made in Limerick</span></div>
        <div class="arrival-benefit"><x-icon name="star" size="30" /><b>PREMIUM QUALITY</b><span>Finest materials, built to last</span></div>
        <div class="arrival-benefit"><x-icon name="truck" size="30" /><b>FAST DISPATCH</b><span>Worldwide delivery</span></div>
        <div class="arrival-benefit"><x-icon name="package" size="30" /><b>EASY RETURNS</b><span>30-day returns</span></div>
        <div class="arrival-benefit"><x-icon name="shopping-bag" size="30" /><b>SECURE PAYMENT</b><span>100% secure checkout</span></div>
    </section>

    <section class="arrival-newsletter">
        <div class="arrival-newsletter-intro"><x-icon name="package" size="32" /><div><strong>STAY IN THE LOOP</strong><span>Exclusive offers, new arrivals and<br>Irish made stories.</span></div></div>
        <form class="arrival-subscribe" method="post" action="{{ route('inquiry') }}">
            @csrf
            <input type="hidden" name="type" value="contact"><input type="hidden" name="name" value="Newsletter subscriber"><input type="hidden" name="subject" value="Newsletter subscription"><input type="hidden" name="message" value="Please add this email address to the Emerald Rozalia newsletter."><input type="hidden" name="consent" value="1">
            <input type="email" name="email" required placeholder="Enter your email address" aria-label="Email address"><button type="submit">SUBSCRIBE</button>
        </form>
        <div class="arrival-follow"><strong>FOLLOW US</strong><span>f</span><span>◎</span><span>♪</span><span>▶</span></div>
    </section>
</div>
@endsection
