@extends('layouts.site')

@section('title', $product->name . ' — Emerald Rozalia')

@php
    $mediaUrl = function ($media) {
        if (str_starts_with($media->path, 'http://') || str_starts_with($media->path, 'https://') || str_starts_with($media->path, '/')) {
            return $media->path;
        }
        return Storage::disk($media->disk ?: 'public')->url($media->path);
    };
    $imageCandidates = collect();
    if (filled($product->image)) {
        $imageCandidates->push(str_starts_with($product->image, 'http') || str_starts_with($product->image, '/') ? $product->image : Storage::disk('public')->url($product->image));
    }
    $product->media->whereIn('type', ['image', 'gallery'])->each(fn ($media) => $imageCandidates->push($mediaUrl($media)));
    $galleryImages = $imageCandidates->filter()->unique()->values()->all();
    $spinImages = collect($spinFrames ?? [])->filter()->values()->all();
    $thumbnailImages = collect($galleryImages ?: $spinImages)->values()->all();
    $firstImage = $galleryImages[0] ?? $spinImages[0] ?? null;
    $activeVariants = $product->variants->where('is_active', true)->values();
    $variantPayload = $activeVariants->map(fn ($variant) => [
        'id' => $variant->id,
        'colour' => $variant->colour,
        'size' => $variant->size,
        'price' => (float) ($variant->price ?? $product->price),
        'stock' => (int) $variant->stock,
        'image' => $variant->image ? (str_starts_with($variant->image, 'http') || str_starts_with($variant->image, '/') ? $variant->image : Storage::disk('public')->url($variant->image)) : null,
    ])->values()->all();
    $colours = $activeVariants->pluck('colour')->filter()->unique()->values()->all() ?: collect($product->colours ?? [])->filter()->values()->all();
    $sizes = $activeVariants->pluck('size')->filter()->unique()->values()->all() ?: collect($product->sizes ?? [])->filter()->values()->all();
    $reviewCount = $product->reviews->count();
    $averageRating = $reviewCount ? round($product->reviews->avg('rating'), 1) : 0;
    $basePrice = (float) $product->price;
    $stock = (int) $product->stock;
    $swatchColours = ['#614a31', '#1d2420', '#9b8365', '#244535', '#d9d2c4', '#232323'];
@endphp

@push('styles')
<style>
.product-page{background:#050a08;color:#f5f6f2;min-height:100vh;padding:28px clamp(18px,4vw,58px) 72px;font-size:14px}
.product-page *{box-sizing:border-box}.product-breadcrumb{display:flex;flex-wrap:wrap;gap:9px;align-items:center;color:#a9b4ab;font-size:13px;margin:0 auto 22px;max-width:1500px}.product-breadcrumb a{color:#dce6dd;text-decoration:none}.product-breadcrumb a:hover{color:#8dd33f}.product-breadcrumb svg{opacity:.6}
.product-showcase{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(360px,.85fr);gap:clamp(24px,4vw,68px);max-width:1500px;margin:0 auto}
.product-media-shell{display:grid;grid-template-columns:82px minmax(0,1fr);gap:16px;min-width:0}.product-thumbnails{display:flex;flex-direction:column;gap:10px}.product-thumb{width:82px;height:82px;background:#141a17;border:1px solid #38443b;border-radius:8px;padding:4px;cursor:pointer;overflow:hidden}.product-thumb.is-active{border-color:#86d52e;box-shadow:0 0 0 2px #86d52e55}.product-thumb img{width:100%;height:100%;object-fit:cover;border-radius:5px}.product-thumb-fallback{font-size:11px;color:#87968a;display:grid;place-items:center;text-align:center;height:100%}
.product-viewer{min-width:0;border:1px solid #36423a;border-radius:10px;overflow:hidden;background:radial-gradient(circle at 50% 30%,#27342e,#0c1110 62%,#070907)}.product-viewer-toolbar{display:flex;justify-content:space-between;align-items:center;padding:13px 16px;border-bottom:1px solid #334038}.viewer-switcher{display:inline-flex;border:1px solid #536057;border-radius:6px;overflow:hidden}.viewer-switcher button{background:transparent;color:#e9f0eb;border:0;padding:10px 15px;min-width:125px;cursor:pointer;font-weight:700;font-size:12px;letter-spacing:.08em}.viewer-switcher button.is-active{background:#2f741d;color:#fff}.viewer-toolbar-actions{display:flex;gap:8px}.icon-button{width:38px;height:38px;border:1px solid #56635a;background:#121914;color:#f8fff8;border-radius:50%;display:grid;place-items:center;cursor:pointer}.icon-button:hover{border-color:#90d93b;color:#a6ec56}
.product-stage{position:relative;min-height:clamp(360px,45vw,630px);display:grid;place-items:center;overflow:hidden;touch-action:none;cursor:grab}.product-stage.is-dragging{cursor:grabbing}.product-stage img{width:100%;height:100%;max-height:630px;object-fit:contain;user-select:none;pointer-events:none;transition:transform .18s ease}.product-placeholder{width:min(70%,440px);aspect-ratio:1.25;display:grid;place-items:center;border:1px dashed #5a6b5e;border-radius:14px;color:#a6b4a9;text-align:center;padding:24px;line-height:1.55}.product-stage-overlay{position:absolute;inset:0;display:flex;justify-content:space-between;align-items:center;padding:0 14px;pointer-events:none}.product-stage-overlay button{pointer-events:auto}.stage-arrow{width:42px;height:42px;background:#08100cdd;border:1px solid #758278;color:#fff;border-radius:50%;display:grid;place-items:center;cursor:pointer}.stage-arrow:hover{border-color:#9ce348;color:#9ce348}.stage-loading{position:absolute;inset:0;display:grid;place-items:center;background:#07100bcc;color:#c3d2c5;opacity:0;pointer-events:none;transition:opacity .2s}.stage-loading.is-visible{opacity:1}.stage-loading span{border:2px solid #849589;border-top-color:#8bd834;border-radius:50%;width:25px;height:25px;animation:product-spin .8s linear infinite}@keyframes product-spin{to{transform:rotate(360deg)}}.stage-caption{position:absolute;left:16px;bottom:16px;background:#07100cdd;border:1px solid #536257;border-radius:5px;padding:8px 11px;color:#c6d4c9;font-size:12px}.stage-degree{position:absolute;right:16px;bottom:16px;background:#0a150edd;border:1px solid #536257;border-radius:5px;padding:8px 11px;color:#b9e77c;font-variant-numeric:tabular-nums}
.rotation-panel{padding:16px;border-top:1px solid #334038;background:#0b120e}.rotation-controls{display:flex;align-items:center;gap:12px}.rotation-controls output{width:52px;text-align:center;color:#bfe981;font-variant-numeric:tabular-nums}.rotation-slider{accent-color:#74c934;flex:1}.rotation-help{display:flex;justify-content:space-between;color:#8d9b90;font-size:12px;margin-top:8px}.product-media-features{display:grid;grid-template-columns:repeat(3,1fr);border-top:1px solid #334038}.media-feature{padding:13px 15px;display:flex;gap:10px;align-items:center;border-right:1px solid #334038}.media-feature:last-child{border-right:0}.media-feature strong{display:block;font-size:12px}.media-feature small{display:block;color:#8e9c91;margin-top:3px;font-size:11px}
.product-copy{padding:4px 0}.product-badge{display:inline-flex;background:#3d891e;color:#fff;border-radius:4px;padding:6px 10px;font-size:11px;font-weight:800;letter-spacing:.1em}.product-copy h1{font-family:Georgia,serif;font-size:clamp(30px,3.2vw,50px);line-height:1.08;margin:18px 0 12px;max-width:700px}.product-price{font-size:30px;font-weight:700;color:#fff;margin:0 0 12px}.product-meta{display:flex;flex-wrap:wrap;align-items:center;gap:12px;color:#c8d1ca;padding-bottom:18px;border-bottom:1px solid #39443b}.product-rating{color:#ffbd18;letter-spacing:2px}.product-rating span{color:#b8c3ba;letter-spacing:0;margin-left:5px}.product-description{font-size:16px;line-height:1.65;color:#d3dbd4;margin:18px 0 22px}.option-group{margin:21px 0}.option-heading{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;color:#eff7f0}.option-heading a{color:#9be64e;text-decoration:none;font-size:12px}.swatch-list,.size-list{display:flex;flex-wrap:wrap;gap:10px}.colour-option input,.size-option input{position:absolute;opacity:0}.colour-option label{width:45px;height:45px;border-radius:50%;display:block;border:2px solid #56625a;padding:3px;cursor:pointer;background:#222}.colour-option label span{display:block;width:100%;height:100%;border-radius:50%}.colour-option input:checked+label{border-color:#8fe33d;box-shadow:0 0 0 2px #8fe33d55}.size-option label{display:block;border:1px solid #59665c;border-radius:5px;padding:11px 18px;cursor:pointer;min-width:76px;text-align:center}.size-option input:checked+label{border-color:#8fe33d;background:#327c1b;color:#fff}.variant-select{width:100%;background:#111a14;color:#f4f8f4;border:1px solid #526056;border-radius:5px;padding:12px;font-size:14px}.purchase-row{display:grid;grid-template-columns:145px minmax(0,1fr) 52px;gap:10px;align-items:stretch}.quantity-control{display:flex;border:1px solid #59665c;border-radius:5px;overflow:hidden}.quantity-control button,.quantity-control input{background:#0b110d;color:#fff;border:0;width:44px;text-align:center;font-size:17px}.quantity-control input{width:56px;border-left:1px solid #36433a;border-right:1px solid #36433a;font-size:14px}.primary-cta{border:0;border-radius:5px;background:#4d9d24;color:#fff;font-weight:800;letter-spacing:.07em;cursor:pointer;font-size:14px}.primary-cta:hover{background:#67b936}.primary-cta:disabled{opacity:.45;cursor:not-allowed}.wishlist-button{border:1px solid #647166;background:#0c130e;color:#f8fff8;border-radius:5px;display:grid;place-items:center;cursor:pointer}.wishlist-button:hover{border-color:#9be64e;color:#9be64e}.purchase-note{color:#9dab9f;font-size:12px;margin-top:9px}.tryon-cta{display:flex;justify-content:space-between;align-items:center;border:1px solid #4f683d;border-radius:5px;padding:15px;margin-top:16px;color:#d9e7d9;text-decoration:none}.tryon-cta strong{color:#b3ea69;display:block}.tryon-cta small{color:#98a59a;display:block;margin-top:4px}.service-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:22px;border-top:1px solid #39443b;padding-top:18px}.service-item{display:flex;gap:8px;align-items:flex-start;color:#dce5dc;font-size:12px}.service-item svg{color:#9de044;flex:none}
.benefit-band{max-width:1500px;margin:32px auto 0;border:1px solid #26372c;background:#0a140e;border-radius:9px;display:grid;grid-template-columns:repeat(4,1fr)}.benefit-item{padding:20px 18px;display:flex;gap:13px;align-items:center;border-right:1px solid #26372c}.benefit-item:last-child{border-right:0}.benefit-item svg{color:#8cd932;flex:none}.benefit-item strong{display:block;font-size:14px}.benefit-item span{display:block;color:#9eaca1;font-size:12px;margin-top:4px;line-height:1.4}
.product-details{max-width:1500px;margin:34px auto 0}.detail-tabs{display:flex;gap:26px;border-bottom:1px solid #36453a;overflow:auto}.detail-tab{border:0;background:none;color:#a6b2a7;padding:14px 0;white-space:nowrap;cursor:pointer;font-size:14px}.detail-tab.is-active{color:#a3e85a;border-bottom:2px solid #8dd536}.detail-panel{padding:24px 0;color:#c8d3ca;line-height:1.65}.detail-panel[hidden]{display:none}.detail-panel h2{font-family:Georgia,serif;font-size:25px;color:#f4faf4;margin:0 0 10px}.detail-columns{display:grid;grid-template-columns:1fr 1fr;gap:28px}.spec-list{display:grid;grid-template-columns:repeat(2,1fr);gap:10px 22px;padding:0;margin:18px 0;list-style:none}.spec-list li{border-bottom:1px solid #26342b;padding:9px 0}.spec-list b{color:#eef6ef}.review-layout{display:grid;grid-template-columns:260px 1fr;gap:32px}.review-score{border:1px solid #35453a;border-radius:8px;padding:22px;text-align:center}.review-score strong{font-size:43px;color:#fff;display:block}.review-score .stars{color:#ffbd18;font-size:19px;letter-spacing:2px}.review-card{border-bottom:1px solid #2b3b31;padding:0 0 16px;margin-bottom:16px}.review-card header{display:flex;justify-content:space-between;color:#cbd8ce}.review-card p{color:#aab8ae;margin:7px 0 0}.review-form{border:1px solid #35453a;border-radius:8px;padding:18px;margin-top:20px}.review-form-grid{display:grid;grid-template-columns:160px 1fr;gap:10px}.review-form input,.review-form textarea{width:100%;background:#101812;color:#fff;border:1px solid #4d5e51;border-radius:4px;padding:10px}.review-form button{margin-top:10px;padding:10px 16px}.related-products{max-width:1500px;margin:28px auto 0}.related-products h2{font-family:Georgia,serif;font-size:28px}.related-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.related-card{border:1px solid #2d3c32;border-radius:7px;background:#0d1510;overflow:hidden}.related-card a{text-decoration:none;color:#fff}.related-card-media{height:190px;display:grid;place-items:center;background:#18221c;color:#849488}.related-card-media img{width:100%;height:100%;object-fit:cover}.related-card-body{padding:13px}.related-card h3{font-size:15px;margin:0 0 5px}.related-card p{color:#a9b7ab;font-size:12px;margin:0 0 9px}.related-card strong{color:#a0e351}
.product-sr-status{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:1100px){.product-showcase{grid-template-columns:1fr}.product-copy{max-width:850px}.product-stage{min-height:500px}.benefit-band{grid-template-columns:repeat(2,1fr)}.benefit-item:nth-child(2){border-right:0}.benefit-item:nth-child(-n+2){border-bottom:1px solid #26372c}.related-grid{grid-template-columns:repeat(4,1fr)}}
@media(max-width:700px){.product-page{padding-inline:14px}.product-media-shell{grid-template-columns:1fr}.product-thumbnails{order:2;flex-direction:row;overflow:auto}.product-thumb{flex:none;width:64px;height:64px}.product-viewer-toolbar{align-items:flex-start;gap:9px;flex-direction:column}.product-stage{min-height:360px}.product-media-features{grid-template-columns:1fr}.media-feature{border-right:0;border-bottom:1px solid #334038}.media-feature:last-child{border-bottom:0}.purchase-row{grid-template-columns:120px 1fr 46px}.service-grid{grid-template-columns:1fr}.detail-columns,.review-layout{grid-template-columns:1fr}.related-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:420px){.product-copy h1{font-size:31px}.viewer-switcher button{min-width:0;padding-inline:10px}.benefit-band{grid-template-columns:1fr}.benefit-item{border-right:0!important;border-bottom:1px solid #26372c}.benefit-item:last-child{border-bottom:0}.purchase-row{grid-template-columns:1fr 1fr}.quantity-control{grid-column:1/-1}.primary-cta{min-height:48px}.related-grid{grid-template-columns:1fr}.spec-list{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="product-page" data-product-page
     data-product-id="{{ $product->id }}"
     data-product-price="{{ number_format($basePrice, 2, '.', '') }}"
     data-product-stock="{{ $stock }}"
     data-variant-payload='@json($variantPayload)'>
    <nav class="product-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a><x-icon name="chevron-right" size="14" />
        <a href="{{ route('shop') }}">Shop</a><x-icon name="chevron-right" size="14" />
        @if($product->category)<a href="{{ route('category', $product->category) }}">{{ $product->category->name }}</a><x-icon name="chevron-right" size="14" />@endif
        <span aria-current="page">{{ $product->name }}</span>
    </nav>

    <section class="product-showcase">
        <div class="product-media-shell">
            <div class="product-thumbnails" data-product-thumbnails>
                @forelse($thumbnailImages as $image)
                    <button class="product-thumb @if($loop->first) is-active @endif" type="button" data-product-thumb data-index="{{ $loop->index }}" aria-label="View product image {{ $loop->iteration }}">
                        <img src="{{ $image }}" alt="{{ $product->name }} angle {{ $loop->iteration }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                    </button>
                @empty
                    <div class="product-thumb"><span class="product-thumb-fallback">Media<br>pending</span></div>
                @endforelse
            </div>

            <div class="product-viewer" data-product-viewer
                 data-spin-frames='@json($spinImages)'
                 data-gallery-frames='@json($galleryImages)'
                 data-initial-image="{{ $firstImage }}">
                <div class="product-viewer-toolbar">
                    <div class="viewer-switcher" role="tablist" aria-label="Product media">
                        <button type="button" class="is-active" data-product-mode="spin" role="tab" aria-selected="true"><x-icon name="rotate-ccw" size="15" /> 360° VIEW</button>
                        <button type="button" data-product-mode="gallery" role="tab" aria-selected="false"><x-icon name="image" size="15" /> PHOTOS</button>
                    </div>
                    <div class="viewer-toolbar-actions">
                        <button class="icon-button" type="button" data-media-zoom aria-label="Zoom product image"><x-icon name="search" size="17" /></button>
                        <button class="icon-button" type="button" data-media-fullscreen aria-label="Open full screen product viewer"><x-icon name="arrow-up" size="17" /></button>
                    </div>
                </div>
                <div class="product-stage" data-product-stage tabindex="0" aria-label="Interactive product viewer. Drag or swipe to rotate.">
                    @if($firstImage)<img data-product-stage-image src="{{ $firstImage }}" alt="{{ $product->name }}" draggable="false">@else<div class="product-placeholder" data-product-placeholder><div><x-icon name="image" size="34" /><br>Product imagery will appear here when approved in Product Media Manager.</div></div>@endif
                    <div class="product-stage-overlay"><button class="stage-arrow" type="button" data-rotate-prev aria-label="Previous angle"><x-icon name="arrow-left" size="18" /></button><button class="stage-arrow" type="button" data-rotate-next aria-label="Next angle"><x-icon name="arrow-right" size="18" /></button></div>
                    <div class="stage-loading" data-stage-loading aria-hidden="true"><span></span></div>
                    <span class="stage-caption" data-stage-caption>Drag to rotate</span><span class="stage-degree" data-stage-degree>0°</span>
                    <span class="product-sr-status" data-stage-status role="status" aria-live="polite"></span>
                </div>
                <div class="rotation-panel">
                    <div class="rotation-controls"><button class="icon-button" type="button" data-rotate-prev aria-label="Rotate left"><x-icon name="arrow-left" size="15" /></button><input class="rotation-slider" type="range" min="0" max="360" value="0" step="1" data-rotation-slider aria-label="Product rotation angle"><button class="icon-button" type="button" data-rotate-next aria-label="Rotate right"><x-icon name="arrow-right" size="15" /></button><output data-rotation-value>0°</output></div>
                    <div class="rotation-help"><span>0°</span><span>Drag, swipe or use arrow keys</span><span>360°</span></div>
                </div>
                <div class="product-media-features">
                    <div class="media-feature"><x-icon name="rotate-ccw" size="22" /><div><strong>360° VIEW</strong><small>Explore every angle</small></div></div>
                    <div class="media-feature"><x-icon name="search" size="22" /><div><strong>ZOOM</strong><small>Inspect the details</small></div></div>
                    <div class="media-feature"><x-icon name="arrow-up" size="22" /><div><strong>FULL SCREEN</strong><small>Close-up experience</small></div></div>
                </div>
            </div>
        </div>

        <div class="product-copy">
            @if($product->is_new)<span class="product-badge">NEW ARRIVAL</span>@elseif($product->compare_price)<span class="product-badge">BEST SELLER</span>@endif
            <h1>{{ $product->name }}</h1>
            <p class="product-price" data-product-price-display>€{{ number_format($basePrice, 2) }}</p>
            <span hidden data-variant-price-index>@foreach($activeVariants as $variant)€{{ number_format((float) ($variant->price ?? $basePrice), 2) }} @endforeach</span>
            <div class="product-meta"><span class="product-rating" aria-label="{{ $averageRating }} out of 5 stars">{{ $averageRating ? str_repeat('★', (int) round($averageRating)) : '☆' }}<span>({{ $reviewCount }})</span></span><span>|</span><span>SKU: {{ $product->sku }}</span></div>
            <p class="product-description">{{ $product->description ?: 'A timeless Emerald Rozalia classic, made in Limerick, Ireland from premium materials. Built for comfort and made to last.' }}</p>

            @if(count($colours))
                <div class="option-group"><div class="option-heading"><span>COLOUR: <b data-selected-colour>{{ $colours[0] }}</b></span><span>Product Media Manager swatches</span></div><div class="swatch-list">@foreach($colours as $colour)<div class="colour-option"><input type="radio" id="colour-{{ $loop->index }}" name="colour-choice" value="{{ $colour }}" @checked($loop->first)><label for="colour-{{ $loop->index }}" title="{{ $colour }}"><span style="background:{{ $swatchColours[$loop->index % count($swatchColours)] }}"></span></label></div>@endforeach</div></div>
            @endif
            @if(count($sizes))
                <div class="option-group"><div class="option-heading"><span>SIZE: <b data-selected-size>{{ $sizes[0] }}</b></span><a href="{{ route('factory') }}#size-guide">SIZE GUIDE <x-icon name="ruler" size="14" /></a></div><div class="size-list">@foreach($sizes as $size)<div class="size-option"><input type="radio" id="size-{{ $loop->index }}" name="size-choice" value="{{ $size }}" @checked($loop->first)><label for="size-{{ $loop->index }}">{{ $size }}</label></div>@endforeach</div></div>
            @elseif($activeVariants->count() > 0)
                <div class="option-group"><div class="option-heading"><span>SELECT VARIANT</span></div><select class="variant-select" data-variant-select><option value="">Choose an option</option>@foreach($activeVariants as $variant)<option value="{{ $variant->id }}">{{ $variant->colour }}{{ $variant->colour && $variant->size ? ' · ' : '' }}{{ $variant->size }} — €{{ number_format((float)($variant->price ?? $basePrice), 2) }}</option>@endforeach</select></div>
            @endif

            <form method="post" action="{{ route('cart.add', $product) }}" class="purchase-form" data-product-form>
                @csrf
                <input type="hidden" name="variant_id" data-variant-id>
                <input type="hidden" name="colour" data-colour-value @if(count($colours)) value="{{ $colours[0] }}" @endif>
                <input type="hidden" name="size" data-size-value @if(count($sizes)) value="{{ $sizes[0] }}" @endif>
                <div class="purchase-row">
                    <div class="quantity-control"><button type="button" data-quantity-minus aria-label="Decrease quantity">−</button><input type="number" name="quantity" min="1" max="{{ max(1, $stock) }}" value="1" data-quantity aria-label="Quantity"><button type="button" data-quantity-plus aria-label="Increase quantity">+</button></div>
                    <button class="primary-cta" type="submit" data-add-to-cart @disabled($stock < 1)>{{ $stock > 0 ? 'ADD TO CART' : 'OUT OF STOCK' }}</button>
                    @auth
                        <button class="wishlist-button" type="submit" form="wishlist-form" aria-label="Add to wishlist"><x-icon name="heart" size="21" /></button>
                    @else
                        <a class="wishlist-button" href="{{ route('login') }}" aria-label="Sign in to save this product"><x-icon name="heart" size="21" /></a>
                    @endauth
                </div>
                <p class="purchase-note" data-stock-note>{{ $stock > 0 ? $stock . ' available · Ships from Limerick, Ireland' : 'Currently unavailable' }}</p>
            </form>
            @auth<form id="wishlist-form" method="post" action="{{ route('wishlist.toggle', $product) }}">@csrf</form>@endauth
            <a class="tryon-cta" href="{{ route('virtual-tryon', ['product_id' => $product->id]) }}"><span><strong><x-icon name="camera" size="17" /> TRY IT ON</strong><small>See how it looks on you — private in-browser preview</small></span><x-icon name="arrow-right" size="18" /></a>
            <div class="service-grid"><div class="service-item"><x-icon name="truck" size="21" /><span><b>FAST DISPATCH</b><br>Worldwide delivery</span></div><div class="service-item"><x-icon name="refresh" size="21" /><span><b>EASY RETURNS</b><br>30-day returns</span></div><div class="service-item"><x-icon name="credit-card" size="21" /><span><b>SECURE PAYMENT</b><br>100% secure checkout</span></div></div>
        </div>
    </section>

    <section class="benefit-band"><div class="benefit-item"><x-icon name="clover" size="34" /><div><strong>IRISH MADE</strong><span>Proudly made in Limerick by skilled craftspeople.</span></div></div><div class="benefit-item"><x-icon name="package" size="34" /><div><strong>PREMIUM MATERIALS</strong><span>High-quality materials, built to last.</span></div></div><div class="benefit-item"><x-icon name="star" size="34" /><div><strong>TIMELESS STYLE</strong><span>Classic design that never goes out of style.</span></div></div><div class="benefit-item"><x-icon name="package" size="34" /><div><strong>PERFECT GIFT</strong><span>A thoughtful gift for any occasion.</span></div></div></section>

    <section class="product-details">
        <div class="detail-tabs" role="tablist"><button class="detail-tab is-active" type="button" data-detail-tab="details">PRODUCT DETAILS</button><button class="detail-tab" type="button" data-detail-tab="materials">MATERIALS &amp; CARE</button><button class="detail-tab" type="button" data-detail-tab="delivery">DELIVERY &amp; RETURNS</button><button class="detail-tab" type="button" data-detail-tab="reviews">REVIEWS ({{ $reviewCount }})</button></div>
        <div class="detail-panel" data-detail-panel="details"><div class="detail-columns"><div><h2>Made for every day</h2><p>{{ $product->description ?: 'Designed in Ireland and made with care, this piece brings an understated Emerald Rozalia signature to every look.' }}</p></div><ul class="spec-list"><li><b>SKU</b><br>{{ $product->sku }}</li><li><b>Origin</b><br>Limerick, Ireland</li><li><b>Collection</b><br>{{ $product->category?->name ?: 'Emerald Rozalia' }}</li><li><b>Availability</b><br><span data-detail-stock>{{ $stock > 0 ? 'In stock' : 'Out of stock' }}</span></li></ul></div></div>
        <div class="detail-panel" data-detail-panel="materials" hidden><h2>Materials &amp; care</h2><p>{{ $product->material ?: 'Premium, carefully selected materials.' }}</p><p>Spot clean gently and allow to air dry. Store away from direct sunlight and moisture. Every approved product asset is managed through the Product Media Manager.</p></div>
        <div class="detail-panel" data-detail-panel="delivery" hidden><h2>Dispatch, delivery &amp; returns</h2><p>Orders are dispatched from Limerick, Ireland. Delivery estimates are shown at checkout and tracking is provided when your order leaves our workshop.</p><p>Changed your mind? Return unworn items within 30 days. Contact our team through the <a href="{{ route('contact') }}">contact page</a> for support.</p></div>
        <div class="detail-panel" data-detail-panel="reviews" hidden><div class="review-layout"><div class="review-score"><strong>{{ $averageRating ?: '—' }}</strong><span class="stars">{{ $averageRating ? str_repeat('★', (int) round($averageRating)) : '☆☆☆☆☆' }}</span><p>{{ $reviewCount }} verified reviews</p></div><div>@forelse($product->reviews->take(3) as $review)<article class="review-card"><header><span>{{ $review->user?->name ?: 'Verified customer' }}</span><span class="stars">{{ str_repeat('★', (int) $review->rating) }}</span></header>@if($review->title)<b>{{ $review->title }}</b>@endif<p>{{ $review->body }}</p></article>@empty<p>No reviews yet. Be the first to share your experience.</p>@endforelse</div></div>@auth<div class="review-form"><h3>Share your experience</h3><form method="post" action="{{ route('reviews.store', $product) }}">@csrf<div class="review-form-grid"><label for="review-rating">Rating</label><select id="review-rating" name="rating"><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select><label for="review-title">Title</label><input id="review-title" name="title" maxlength="120"><label for="review-body">Review</label><textarea id="review-body" name="body" rows="3" maxlength="2000"></textarea></div><button class="primary-cta" type="submit">SUBMIT REVIEW</button></form></div>@else<p><a href="{{ route('login') }}">Sign in</a> to leave a review.</p>@endauth</div>
    </section>

    @if($related->count())
        <section class="related-products"><h2>You may also like</h2><div class="related-grid">@foreach($related as $item)<article class="related-card"><a href="{{ route('product', $item) }}"><div class="related-card-media">@if($item->image)<img src="{{ str_starts_with($item->image, 'http') || str_starts_with($item->image, '/') ? $item->image : Storage::disk('public')->url($item->image) }}" alt="{{ $item->name }}" loading="lazy">@else<x-icon name="package" size="34" />@endif</div><div class="related-card-body"><h3>{{ $item->name }}</h3><p>{{ $item->category?->name ?: 'Emerald Rozalia' }}</p><strong>€{{ number_format($item->price, 2) }}</strong></div></a></article>@endforeach</div></section>
    @endif
</div>
@php
    $productVideos = \App\Models\ProductVideo::where('product_id',$product->id)->with('product')->where('active',true)->orderBy('sort_order')->get()
        ->filter(fn ($video) => $video->isPubliclyPlayable() && data_get($video->metadata,'gallery',true));
@endphp
@if($productVideos->isNotEmpty())
<section class="product-details" style="padding:24px" aria-label="Product videos">
    <h2>Product videos</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:24px;margin-top:16px">
    @foreach($productVideos as $video)
        <article><h3>{{ $video->title }}</h3><x-video-player :video="$video" /><p>{{ data_get($video->metadata,'description') }}</p></article>
    @endforeach
    </div>
</section>
@endif
@php($productSpins = \App\Models\ProductSpin::where('product_id',$product->id)->with('product')->where('status','published')->where('visibility','public')->get()->filter(fn($spin)=>$spin->isPublic()))
@if($productSpins->isNotEmpty())
<link rel="stylesheet" href="/css/spins.css">
<section class="product-details" style="padding:24px" aria-label="Interactive 360° product views">
<h2>Explore in 360°</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:24px">
@foreach($productSpins as $spin)<article><h3>{{ $spin->title }}</h3><x-spin-viewer :spin="$spin" /></article>@endforeach
</div></section>
<script src="/js/spin-viewer.js" defer></script>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const page = document.querySelector('[data-product-page]');
    if (!page) return;
    const viewer = page.querySelector('[data-product-viewer]');
    const stage = page.querySelector('[data-product-stage]');
    const stageImage = page.querySelector('[data-product-stage-image]');
    const placeholder = page.querySelector('[data-product-placeholder]');
    const degree = page.querySelector('[data-stage-degree]');
    const slider = page.querySelector('[data-rotation-slider]');
    const rotationValue = page.querySelector('[data-rotation-value]');
    const status = page.querySelector('[data-stage-status]');
    const caption = page.querySelector('[data-stage-caption]');
    const loading = page.querySelector('[data-stage-loading]');
    const parse = (selector, fallback) => { try { return JSON.parse(viewer?.getAttribute(selector) || '[]') || fallback; } catch (error) { return fallback; } };
    const normalize = (value) => { if (!value) return ''; if (/^(https?:)?\//.test(value)) return value; if (/^storage\//.test(value)) return '/' + value; return '/storage/' + value.replace(/^\/+/, ''); };
    const spinFrames = parse('data-spin-frames', []).map(normalize).filter(Boolean);
    const galleryFrames = parse('data-gallery-frames', []).map(normalize).filter(Boolean);
    let mode = spinFrames.length ? 'spin' : 'gallery';
    let index = 0;
    let zoomed = false;
    let dragStart = null;
    let dragMoved = false;
    const frames = () => mode === 'spin' ? spinFrames : galleryFrames;
    const setLoading = (visible) => { if (loading) loading.classList.toggle('is-visible', visible); };
    const updateThumbs = () => page.querySelectorAll('[data-product-thumb]').forEach((button) => button.classList.toggle('is-active', Number(button.dataset.index) === index));
    const render = (announce = true) => {
        const list = frames();
        if (list.length && stageImage) {
            setLoading(true);
            const next = new Image();
            next.onload = () => { stageImage.src = list[index % list.length]; stageImage.hidden = false; placeholder?.setAttribute('hidden', 'hidden'); setLoading(false); };
            next.onerror = () => { setLoading(false); status.textContent = 'This approved media asset is temporarily unavailable.'; };
            next.src = list[index % list.length];
        } else if (stageImage && viewer?.dataset.initialImage) {
            stageImage.src = normalize(viewer.dataset.initialImage); stageImage.hidden = false; placeholder?.setAttribute('hidden', 'hidden');
        } else if (placeholder) {
            stageImage?.setAttribute('hidden', 'hidden'); placeholder.hidden = false;
        }
        const angle = list.length ? Math.round((index % list.length) * 360 / list.length) : 0;
        if (degree) degree.textContent = angle + '°';
        if (slider) slider.value = angle;
        if (rotationValue) rotationValue.textContent = angle + '°';
        if (caption) caption.textContent = mode === 'spin' && list.length ? 'Drag to rotate' : 'Product photos';
        if (announce) status.textContent = mode === 'spin' && list.length ? 'Showing angle ' + angle + ' degrees. Drag or swipe to rotate.' : 'Showing product photo ' + (index + 1) + '.';
        updateThumbs();
    };
    const step = (amount) => { const list = frames(); if (!list.length) return; index = (index + amount + list.length) % list.length; render(); };
    const setMode = (nextMode) => { mode = nextMode === 'spin' && spinFrames.length ? 'spin' : 'gallery'; index = Math.min(index, Math.max(frames().length - 1, 0)); page.querySelectorAll('[data-product-mode]').forEach((button) => { const active = button.dataset.productMode === mode; button.classList.toggle('is-active', active); button.setAttribute('aria-selected', active ? 'true' : 'false'); }); render(); };
    page.querySelectorAll('[data-product-mode]').forEach((button) => button.addEventListener('click', () => setMode(button.dataset.productMode)));
    page.querySelectorAll('[data-rotate-prev]').forEach((button) => button.addEventListener('click', () => step(-1)));
    page.querySelectorAll('[data-rotate-next]').forEach((button) => button.addEventListener('click', () => step(1)));
    page.querySelectorAll('[data-product-thumb]').forEach((button) => button.addEventListener('click', () => { index = Number(button.dataset.index) || 0; if (mode === 'spin' && !spinFrames.length) mode = 'gallery'; render(); }));
    slider?.addEventListener('input', (event) => { const list = frames(); if (!list.length) return; index = Math.round((Number(event.target.value) / 360) * list.length) % list.length; render(false); });
    stage?.addEventListener('pointerdown', (event) => { dragStart = event.clientX; dragMoved = false; stage.classList.add('is-dragging'); stage.setPointerCapture?.(event.pointerId); });
    stage?.addEventListener('pointermove', (event) => { if (dragStart === null || mode !== 'spin' || !spinFrames.length) return; const delta = event.clientX - dragStart; if (Math.abs(delta) > 8) { dragMoved = true; step(delta > 0 ? -1 : 1); dragStart = event.clientX; } });
    stage?.addEventListener('pointerup', (event) => { dragStart = null; stage.classList.remove('is-dragging'); stage.releasePointerCapture?.(event.pointerId); });
    stage?.addEventListener('pointercancel', () => { dragStart = null; stage.classList.remove('is-dragging'); });
    stage?.addEventListener('keydown', (event) => { if (event.key === 'ArrowLeft') { event.preventDefault(); step(-1); } if (event.key === 'ArrowRight') { event.preventDefault(); step(1); } if (event.key === 'Home') { index = 0; render(); } if (event.key === 'End') { index = Math.max(frames().length - 1, 0); render(); } if (event.key === 'Escape' && document.fullscreenElement) document.exitFullscreen?.(); });
    stage?.addEventListener('wheel', (event) => { if (event.ctrlKey || event.metaKey) { event.preventDefault(); zoomed = !zoomed; if (stageImage) stageImage.style.transform = zoomed ? 'scale(1.45)' : ''; } });
    page.querySelector('[data-media-zoom]')?.addEventListener('click', () => { zoomed = !zoomed; if (stageImage) stageImage.style.transform = zoomed ? 'scale(1.45)' : ''; status.textContent = zoomed ? 'Zoom enabled. Use the button again to reset.' : 'Zoom reset.'; });
    page.querySelector('[data-media-fullscreen]')?.addEventListener('click', () => { const shell = page.querySelector('.product-media-shell'); if (!document.fullscreenElement) shell?.requestFullscreen?.(); else document.exitFullscreen?.(); });

    let variants = [];
    try { variants = JSON.parse(page.dataset.variantPayload || '[]') || []; } catch (error) { variants = []; }
    const selected = { colour: page.querySelector('[data-colour-value]')?.value || '', size: page.querySelector('[data-size-value]')?.value || '' };
    const variantMatches = () => variants.filter((variant) => (!selected.colour || variant.colour === selected.colour) && (!selected.size || variant.size === selected.size));
    const applyVariant = () => {
        const match = variantMatches()[0];
        const price = match ? Number(match.price) : Number(page.dataset.productPrice || 0);
        const available = match ? Number(match.stock) : Number(page.dataset.productStock || 0);
        const priceDisplay = page.querySelector('[data-product-price-display]');
        const stockNote = page.querySelector('[data-stock-note]');
        const add = page.querySelector('[data-add-to-cart]');
        const quantity = page.querySelector('[data-quantity]');
        if (priceDisplay) priceDisplay.textContent = '€' + price.toFixed(2);
        if (page.querySelector('[data-variant-id]')) page.querySelector('[data-variant-id]').value = match?.id || '';
        if (match?.image && stageImage) { stageImage.src = normalize(match.image); stageImage.hidden = false; placeholder?.setAttribute('hidden', 'hidden'); }
        if (quantity) { quantity.max = String(Math.max(1, available)); if (Number(quantity.value) > available) quantity.value = Math.max(1, available); }
        if (stockNote) stockNote.textContent = available > 0 ? available + ' available · Ships from Limerick, Ireland' : 'This combination is currently unavailable';
        if (add) { add.disabled = available < 1 || (variants.length > 0 && !match); add.textContent = available > 0 ? 'ADD TO CART' : 'OUT OF STOCK'; }
        const detailStock = page.querySelector('[data-detail-stock]'); if (detailStock) detailStock.textContent = available > 0 ? 'In stock' : 'Out of stock';
        if (page.querySelector('[data-selected-colour]')) page.querySelector('[data-selected-colour]').textContent = selected.colour || 'Select';
        if (page.querySelector('[data-selected-size]')) page.querySelector('[data-selected-size]').textContent = selected.size || 'Select';
    };
    page.querySelectorAll('input[name="colour-choice"]').forEach((input) => input.addEventListener('change', () => { selected.colour = input.value; page.querySelector('[data-colour-value]').value = input.value; applyVariant(); }));
    page.querySelectorAll('input[name="size-choice"]').forEach((input) => input.addEventListener('change', () => { selected.size = input.value; page.querySelector('[data-size-value]').value = input.value; applyVariant(); }));
    page.querySelector('[data-variant-select]')?.addEventListener('change', (event) => { const match = variants.find((variant) => String(variant.id) === event.target.value); if (match) { selected.colour = match.colour || ''; selected.size = match.size || ''; page.querySelector('[data-colour-value]').value = selected.colour; page.querySelector('[data-size-value]').value = selected.size; } applyVariant(); });
    page.querySelector('[data-quantity-minus]')?.addEventListener('click', () => { const input = page.querySelector('[data-quantity]'); input.value = Math.max(1, Number(input.value || 1) - 1); });
    page.querySelector('[data-quantity-plus]')?.addEventListener('click', () => { const input = page.querySelector('[data-quantity]'); input.value = Math.min(Number(input.max || 50), Number(input.value || 1) + 1); });
    page.querySelector('[data-quantity]')?.addEventListener('change', (event) => { event.target.value = Math.max(1, Math.min(Number(event.target.max || 50), Number(event.target.value || 1))); });
    page.querySelectorAll('[data-detail-tab]').forEach((tab) => tab.addEventListener('click', () => { const target = tab.dataset.detailTab; page.querySelectorAll('[data-detail-tab]').forEach((item) => item.classList.toggle('is-active', item === tab)); page.querySelectorAll('[data-detail-panel]').forEach((panel) => { panel.hidden = panel.dataset.detailPanel !== target; }); }));
    render(false); applyVariant();
})();
</script>
@endpush
