@extends('layouts.site')
@section('title', ($catalogue->title ?: 'Product Catalogue').' — Emerald Rozalia')
@section('body-class','product-catalogue-page')

@push('styles')
<style>
.product-catalogue-shell{max-width:1240px;margin:0 auto;padding:58px 24px 88px}.product-catalogue-hero{display:grid;grid-template-columns:minmax(260px,.85fr) minmax(0,1.35fr);gap:46px;align-items:center;padding:38px;border:1px solid var(--site-border);border-radius:calc(var(--site-radius) + 4px);background:linear-gradient(135deg,var(--site-surface),var(--site-surface-muted));box-shadow:0 18px 48px rgba(6,48,32,.07)}.product-catalogue-cover{min-height:410px;border-radius:var(--site-radius);overflow:hidden;background:linear-gradient(150deg,var(--site-brand-primary),var(--site-brand-secondary));display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 16px 38px rgba(0,0,0,.13)}.product-catalogue-cover img{width:100%;height:100%;min-height:410px;object-fit:cover}.product-catalogue-cover-fallback{padding:38px;text-align:center}.product-catalogue-cover-fallback strong{display:block;font-family:var(--site-heading-family);font-size:2rem;line-height:1.15;margin-bottom:12px}.product-catalogue-cover-fallback span{font-size:.78rem;letter-spacing:.12em}.product-catalogue-copy .eyebrow{font-size:.76rem;letter-spacing:.16em;font-weight:900;color:var(--site-brand-primary)}.product-catalogue-copy h1{font-family:var(--site-heading-family);font-size:clamp(2.25rem,5vw,4.3rem);line-height:1.02;margin:11px 0 18px}.product-catalogue-copy>p{font-size:1.03rem;line-height:1.75;color:var(--site-text-muted);max-width:720px}.product-catalogue-live{display:inline-flex;align-items:center;gap:8px;margin-top:8px;padding:8px 11px;border-radius:999px;background:#e7f7ef;color:#16784a;font-size:.75rem;font-weight:900;letter-spacing:.04em}.product-catalogue-live:before{content:'●';font-size:.72rem}.product-catalogue-meta{display:flex;flex-wrap:wrap;gap:9px;margin:20px 0 0}.product-catalogue-meta span{border:1px solid var(--site-border);border-radius:999px;padding:8px 12px;font-size:.8rem;background:var(--site-surface)}.product-catalogue-actions{display:flex;flex-wrap:wrap;gap:11px;margin-top:26px}.product-catalogue-action{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 18px;border-radius:var(--site-button-radius);font-weight:850;text-decoration:none;letter-spacing:.025em}.product-catalogue-action.primary{background:var(--site-brand-primary);color:#fff}.product-catalogue-action.secondary{border:1px solid var(--site-border);background:var(--site-surface);color:var(--site-text)}.product-catalogue-note{margin-top:18px;font-size:.83rem;color:var(--site-text-muted)}.product-catalogue-section{margin-top:64px}.product-catalogue-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:22px;padding-bottom:16px;border-bottom:1px solid var(--site-border)}.product-catalogue-section-head h2{margin:0;font-family:var(--site-heading-family);font-size:clamp(1.8rem,3vw,2.7rem)}.product-catalogue-section-head p{margin:0;color:var(--site-text-muted);font-size:.9rem}.product-catalogue-chips{display:flex;gap:8px;flex-wrap:wrap;margin:22px 0 0}.product-catalogue-chip{display:inline-flex;align-items:center;padding:8px 12px;border:1px solid var(--site-border);border-radius:999px;color:var(--site-text);text-decoration:none;font-size:.8rem;font-weight:750;background:var(--site-surface)}.product-catalogue-category{scroll-margin-top:110px;margin-top:42px}.product-catalogue-category-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}.product-catalogue-category-head h3{margin:0;font-size:1.25rem}.product-catalogue-category-head span{height:1px;flex:1;background:var(--site-border)}.product-catalogue-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.product-catalogue-card{border:1px solid var(--site-border);border-radius:var(--site-radius);overflow:hidden;background:var(--site-surface);transition:transform var(--site-motion-duration) var(--site-motion-easing),box-shadow var(--site-motion-duration) var(--site-motion-easing)}.product-catalogue-card:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(6,48,32,.1)}.product-catalogue-card a{display:block;color:inherit;text-decoration:none}.product-catalogue-media{aspect-ratio:1/1;background:var(--site-surface-muted);display:flex;align-items:center;justify-content:center;overflow:hidden}.product-catalogue-media img{width:100%;height:100%;object-fit:cover}.product-catalogue-media-empty{padding:18px;text-align:center;color:var(--site-text-muted);font-size:.82rem}.product-catalogue-info{padding:16px}.product-catalogue-info .category{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.09em;color:var(--site-brand-primary);font-weight:850;margin-bottom:7px}.product-catalogue-info h4{margin:0 0 8px;font-size:1rem;line-height:1.35}.product-catalogue-info p{margin:0 0 12px;color:var(--site-text-muted);font-size:.82rem;line-height:1.55}.product-catalogue-card-foot{display:flex;justify-content:space-between;gap:12px;align-items:center}.product-catalogue-card-foot small{color:var(--site-text-muted)}.product-catalogue-card-foot strong{font-size:1rem}.product-catalogue-empty{padding:42px;border:1px dashed var(--site-border);border-radius:var(--site-radius);background:var(--site-surface-muted);text-align:center;color:var(--site-text-muted)}@media(max-width:1020px){.product-catalogue-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:820px){.product-catalogue-hero{grid-template-columns:1fr;padding:24px}.product-catalogue-cover{min-height:360px}.product-catalogue-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.product-catalogue-section-head{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.product-catalogue-shell{padding:34px 16px 64px}.product-catalogue-grid{grid-template-columns:1fr}.product-catalogue-actions{flex-direction:column}.product-catalogue-action{width:100%;box-sizing:border-box}}
</style>
@endpush

@section('content')
<div class="product-catalogue-shell">
    <section class="product-catalogue-hero">
        <div class="product-catalogue-cover">
            @if($uploadedPdfAvailable && $catalogue->cover_path)
                <img src="{{ route('catalogue.cover') }}" alt="{{ $catalogue->title ?: 'Emerald Rozalia Product Catalogue' }} cover">
            @else
                <div class="product-catalogue-cover-fallback">
                    <strong>EMERALD ROZALIA LIMITED</strong>
                    <span>PRODUCT CATALOGUE · IRISH MANUFACTURER · LIMERICK</span>
                </div>
            @endif
        </div>
        <div class="product-catalogue-copy">
            <div class="eyebrow">CURRENT PRODUCT CATALOGUE</div>
            <h1>{{ $catalogue->title ?: 'Emerald Rozalia Product Catalogue' }}</h1>
            <p>{{ $catalogue->description ?: 'Browse the current Emerald Rozalia hats and caps range, generated directly from products that are published on the website.' }}</p>
            <div class="product-catalogue-live">AUTO-GENERATED FROM CURRENT PRODUCTS</div>
            <div class="product-catalogue-meta">
                <span>{{ number_format($products->count()) }} published {{ \Illuminate\Support\Str::plural('product',$products->count()) }}</span>
                <span>{{ number_format($categories->count()) }} {{ \Illuminate\Support\Str::plural('category',$categories->count()) }}</span>
                <span>Updated from live product data</span>
                @if($catalogue->version)<span>Edition {{ $catalogue->version }}</span>@endif
            </div>
            <div class="product-catalogue-actions">
                <a class="product-catalogue-action primary" href="{{ route('catalogue.print',['autoprint'=>1]) }}" target="_blank" rel="noopener"><x-icon name="download" size="17" /> SAVE CURRENT CATALOGUE AS PDF</a>
                @if($uploadedPdfAvailable)
                    <a class="product-catalogue-action secondary" href="{{ route('catalogue.download') }}"><x-icon name="file-text" size="17" /> DOWNLOAD DESIGNED PDF</a>
                @endif
                <a class="product-catalogue-action secondary" href="{{ route('shop') }}">SHOP PRODUCTS <x-icon name="arrow-right" size="16" /></a>
            </div>
            <div class="product-catalogue-note">The generated catalogue updates automatically whenever published products, pricing, descriptions or approved product images change.</div>
        </div>
    </section>

    <section class="product-catalogue-section" aria-labelledby="catalogue-products-title">
        <div class="product-catalogue-section-head">
            <div><div class="eyebrow">LIVE PRODUCT RANGE</div><h2 id="catalogue-products-title">Browse the current catalogue</h2></div>
            <p>Only products currently published on the website are included.</p>
        </div>

        @if($categories->isNotEmpty())
            <nav class="product-catalogue-chips" aria-label="Catalogue categories">
                @foreach($categories as $categoryName => $categoryProducts)
                    <a class="product-catalogue-chip" href="#catalogue-{{ \Illuminate\Support\Str::slug($categoryName) }}">{{ $categoryName }} · {{ $categoryProducts->count() }}</a>
                @endforeach
            </nav>

            @foreach($categories as $categoryName => $categoryProducts)
                <section class="product-catalogue-category" id="catalogue-{{ \Illuminate\Support\Str::slug($categoryName) }}">
                    <div class="product-catalogue-category-head"><h3>{{ $categoryName }}</h3><span></span></div>
                    <div class="product-catalogue-grid">
                        @foreach($categoryProducts as $product)
                            @php($image = $productMedia[$product->id] ?? null)
                            <article class="product-catalogue-card">
                                <a href="{{ route('product',['product'=>$product->slug]) }}">
                                    <div class="product-catalogue-media">
                                        @if($image)
                                            <img src="{{ $image['url'] }}" @if($image['srcset']) srcset="{{ $image['srcset'] }}" sizes="(max-width:560px) 100vw,(max-width:1020px) 50vw,25vw" @endif width="{{ $image['width'] ?: '' }}" height="{{ $image['height'] ?: '' }}" alt="{{ $image['alt'] }}" loading="lazy">
                                        @else
                                            <div class="product-catalogue-media-empty">Approved product image not configured.</div>
                                        @endif
                                    </div>
                                    <div class="product-catalogue-info">
                                        <span class="category">{{ $categoryName }}</span>
                                        <h4>{{ $product->name }}</h4>
                                        @if($product->description)<p>{{ \Illuminate\Support\Str::limit(strip_tags((string)$product->description),95) }}</p>@endif
                                        <div class="product-catalogue-card-foot"><small>SKU {{ $product->sku }}</small><strong>€{{ number_format((float)$product->price,2) }}</strong></div>
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @else
            <div class="product-catalogue-empty">There are no published products available in the catalogue yet.</div>
        @endif
    </section>
</div>
@endsection
