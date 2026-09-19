@extends('layouts.site')
@section('body-class','collections-reference-page collection-detail-page')
@section('title', $collection->name.' — Emerald Rozalia')

@push('styles')
<link rel="stylesheet" href="/css/collections.css?v=20260915-live-collections">
<style>
.collection-detail-hero{position:relative;min-height:330px;display:grid;place-items:center;overflow:hidden;background:#07130d;color:#fff;text-align:center}.collection-detail-hero img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.38}.collection-detail-hero:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(1,7,5,.88),rgba(1,7,5,.36),rgba(1,7,5,.88))}.collection-detail-hero__content{position:relative;z-index:1;max-width:820px;padding:56px 24px}.collection-detail-hero__content a{color:#b9dc86}.collection-detail-hero__content h1{margin:14px 0 12px;font:700 clamp(34px,5vw,64px)/1.02 var(--site-heading-family,Georgia,serif)}.collection-detail-hero__content p{max-width:720px;margin:0 auto;color:#e5ede7}.collection-detail-controls{max-width:1280px;margin:0 auto;padding:28px 24px 10px}.collection-detail-controls form{display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:16px;border:1px solid var(--site-border,#d9e3db);border-radius:var(--site-radius,12px);background:var(--site-surface,#fff)}.collection-detail-controls input,.collection-detail-controls select{min-height:42px;padding:0 12px;border:1px solid var(--site-border,#d9e3db);border-radius:var(--site-input-radius,8px);background:#fff}.collection-detail-controls input[type=search]{min-width:min(100%,280px);flex:1}.collection-category-options{display:flex;flex-wrap:wrap;gap:8px;flex:2}.collection-category-options label{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border:1px solid var(--site-border,#d9e3db);border-radius:999px;font-size:13px}.collection-detail-controls button{min-height:42px;padding:0 18px;border:0;border-radius:var(--site-button-radius,10px);background:var(--site-brand-primary,#075b2f);color:#fff;font-weight:700}.collection-product-section{max-width:1280px;margin:0 auto;padding:24px}.collection-product-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.collection-product-card{border:1px solid var(--site-border,#d9e3db);border-radius:var(--site-radius,12px);overflow:hidden;background:var(--site-surface,#fff)}.collection-product-card>a{display:block;color:inherit;text-decoration:none}.collection-product-media{aspect-ratio:1/1;background:#edf3ee;display:grid;place-items:center;overflow:hidden}.collection-product-media img{width:100%;height:100%;object-fit:cover}.collection-product-media span{padding:16px;text-align:center;color:var(--site-text-muted,#5c6c62)}.collection-product-copy{padding:15px}.collection-product-copy small{color:var(--site-text-muted,#5c6c62)}.collection-product-copy h2{margin:5px 0 8px;font-size:17px}.collection-product-copy strong{color:var(--site-brand-primary,#075b2f)}.collection-empty{padding:48px 24px;text-align:center;border:1px dashed var(--site-border,#d9e3db);border-radius:var(--site-radius,12px)}.collection-pagination{margin-top:28px}@media(max-width:980px){.collection-product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.collection-product-grid{grid-template-columns:1fr}.collection-detail-controls form{align-items:stretch}.collection-category-options{width:100%}}
</style>
@endpush

@section('content')
@php($publicMedia = app(\App\Services\PublicMediaResolver::class))
<section class="collection-detail-hero">
    @if($collectionMedia)
        <img src="{{ $collectionMedia['url'] }}" @if($collectionMedia['srcset']) srcset="{{ $collectionMedia['srcset'] }}" sizes="100vw" @endif width="{{ $collectionMedia['width'] ?: '' }}" height="{{ $collectionMedia['height'] ?: '' }}" alt="{{ $collectionMedia['alt'] }}">
    @endif
    <div class="collection-detail-hero__content">
        <div><a href="{{ route('home') }}">Home</a> / <a href="{{ route('collections') }}">Collections</a> / {{ $collection->name }}</div>
        <h1>{{ strtoupper($collection->name) }}</h1>
        <p>{{ $collection->description ?: 'A curated Emerald Rozalia collection, built from the current published product catalogue.' }}</p>
    </div>
</section>

<section class="collection-detail-controls" aria-label="Collection filters">
    <form method="get" action="{{ route('collection.show', ['collection' => $collection->slug]) }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search this collection" aria-label="Search this collection">
        @if($categories->isNotEmpty())
            <div class="collection-category-options" aria-label="Filter by category">
                @foreach($categories as $category)
                    <label><input type="checkbox" name="category[]" value="{{ $category->slug }}" @checked(in_array($category->slug, $selectedCategories, true))> {{ $category->name }} <small>({{ $category->products_count }})</small></label>
                @endforeach
            </div>
        @endif
        <select name="sort" aria-label="Sort collection products">
            <option value="featured" @selected($sort === 'featured')>Featured</option>
            <option value="newest" @selected($sort === 'newest')>Newest</option>
            <option value="price_low" @selected($sort === 'price_low')>Price: Low to High</option>
            <option value="price_high" @selected($sort === 'price_high')>Price: High to Low</option>
            <option value="name" @selected($sort === 'name')>Name: A–Z</option>
        </select>
        <button type="submit">APPLY</button>
    </form>
</section>

<section class="collection-product-section">
    <div class="home-section-heading home-section-heading--left">
        <h2>{{ $collection->name }}</h2><span></span><a href="{{ route('shop') }}">SHOP ALL <x-icon name="arrow-right" /></a>
    </div>
    @if($products->isNotEmpty())
        <div class="collection-product-grid">
            @foreach($products as $product)
                @php($imageMedia = $product->media->firstWhere('type', 'image'))
                @php($image = $imageMedia ? $publicMedia->forProductMedia($imageMedia, $product->name) : null)
                <article class="collection-product-card">
                    <a href="{{ route('product', $product) }}">
                        <div class="collection-product-media" data-public-media-state="{{ $image ? 'approved' : 'awaiting-approved-media' }}">
                            @if($image)
                                <img src="{{ $image['url'] }}" @if($image['srcset']) srcset="{{ $image['srcset'] }}" sizes="(max-width:620px) 100vw, (max-width:980px) 50vw, 25vw" @endif width="{{ $image['width'] ?: '' }}" height="{{ $image['height'] ?: '' }}" alt="{{ $image['alt'] }}" loading="lazy">
                            @else
                                <span>Approved product image is not configured.</span>
                            @endif
                        </div>
                        <div class="collection-product-copy">
                            <small>{{ $product->category?->name ?: 'Emerald Rozalia' }}</small>
                            <h2>{{ $product->name }}</h2>
                            <strong>€{{ number_format((float) $product->price, 2) }}</strong>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>
        <div class="collection-pagination">{{ $products->links() }}</div>
    @else
        <div class="collection-empty">
            <h2>No published products match this collection.</h2>
            <p>Products appear here only when they are published and assigned to this collection in cPanel.</p>
            @include('site.partials.product-search-contact')
        </div>
    @endif
</section>
@endsection
