@extends('layouts.site')
@section('title', ($catalogue->title ?: 'Product Catalogue').' — Emerald Rozalia')
@section('body-class','product-catalogue-page')

@push('styles')
<style>
.product-catalogue-shell{width:100%;max-width:1320px;min-width:0;margin:0 auto;padding:clamp(32px,5vw,60px) clamp(16px,2.5vw,32px) 88px}
.product-catalogue-hero{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:clamp(24px,3.5vw,46px);align-items:center;min-width:0;padding:clamp(22px,3vw,38px);border:1px solid var(--site-border);border-radius:calc(var(--site-radius) + 4px);background:linear-gradient(135deg,var(--site-surface),var(--site-surface-muted));box-shadow:0 18px 48px rgba(6,48,32,.07)}
.product-catalogue-hero>*{min-width:0}
.product-catalogue-cover{width:100%;min-width:0;min-height:380px;border-radius:var(--site-radius);overflow:hidden;background:linear-gradient(150deg,var(--site-brand-primary),var(--site-brand-secondary));display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 16px 38px rgba(0,0,0,.13)}
.product-catalogue-cover img{width:100%;height:100%;min-height:380px;object-fit:cover}
.product-catalogue-cover-fallback{padding:clamp(20px,3vw,38px);text-align:center}
.product-catalogue-cover-fallback strong{display:block;font-family:var(--site-heading-family);font-size:clamp(1.6rem,3vw,2rem);line-height:1.15;margin-bottom:12px}
.product-catalogue-cover-fallback span{font-size:.78rem;letter-spacing:.12em}
.product-catalogue-copy{min-width:0}
.product-catalogue-copy .eyebrow{font-size:.76rem;letter-spacing:.16em;font-weight:900;color:var(--site-brand-primary)}
.product-catalogue-copy h1{font-family:var(--site-heading-family);font-size:clamp(2rem,4.5vw,4.1rem);line-height:1.04;margin:11px 0 18px;overflow-wrap:anywhere}
.product-catalogue-copy>p{font-size:1.03rem;line-height:1.7;color:var(--site-text-muted);max-width:720px}
.product-catalogue-live{display:inline-flex;align-items:center;gap:8px;margin-top:8px;padding:8px 11px;border-radius:999px;background:#e7f7ef;color:#16784a;font-size:.75rem;font-weight:900;letter-spacing:.04em}
.product-catalogue-live:before{content:'●';font-size:.72rem}
.product-catalogue-meta{display:flex;flex-wrap:wrap;gap:9px;margin:20px 0 0}
.product-catalogue-meta span{border:1px solid var(--site-border);border-radius:999px;padding:8px 12px;font-size:.82rem;background:var(--site-surface);color:var(--site-text)}
.product-catalogue-actions{display:flex;flex-wrap:wrap;gap:11px;margin-top:26px}
.product-catalogue-action{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:0 18px;border-radius:var(--site-button-radius);font-weight:850;text-decoration:none;letter-spacing:.025em}
.product-catalogue-action.primary{background:var(--site-brand-primary);color:#fff}
.product-catalogue-action.secondary{border:1px solid var(--site-border);background:var(--site-surface);color:var(--site-text)}
.product-catalogue-note{margin-top:18px;font-size:.9rem;line-height:1.55;color:var(--site-text-muted)}
.product-catalogue-section{min-width:0;margin-top:clamp(44px,6vw,68px)}
.product-catalogue-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:22px;padding-bottom:18px;border-bottom:1px solid rgba(196,218,202,.25)}
.product-catalogue-section-head h2{margin:0;font-family:var(--site-heading-family);font-size:clamp(2rem,3.3vw,2.8rem);line-height:1.08;color:#f4f7f3}
.product-catalogue-section-head p{margin:0;color:#c7d5cb;font-size:.95rem;line-height:1.5}
.product-catalogue-chips{display:flex;gap:9px;flex-wrap:wrap;margin:22px 0 0}
.product-catalogue-chip{display:inline-flex;align-items:center;padding:9px 13px;border:1px solid var(--site-border);border-radius:999px;color:var(--site-text);text-decoration:none;font-size:.84rem;font-weight:750;background:var(--site-surface);transition:transform .18s ease,border-color .18s ease}
.product-catalogue-chip:hover{transform:translateY(-1px);border-color:var(--site-brand-primary)}
.product-catalogue-category{min-width:0;scroll-margin-top:110px;margin-top:42px}
.product-catalogue-category-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
.product-catalogue-category-head h3{margin:0;color:#f4f7f3;font-family:var(--site-heading-family);font-size:clamp(1.35rem,2vw,1.65rem);line-height:1.2}
.product-catalogue-category-head span{height:1px;flex:1;background:rgba(196,218,202,.28)}
.product-catalogue-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));align-items:stretch;gap:clamp(12px,1.6vw,20px)}
.product-catalogue-card{height:100%;min-width:0;border:1px solid var(--site-border);border-radius:var(--site-radius);overflow:hidden;background:var(--site-surface);color:var(--site-text);transition:transform var(--site-motion-duration) var(--site-motion-easing),box-shadow var(--site-motion-duration) var(--site-motion-easing)}
.product-catalogue-card:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(6,48,32,.16)}
.product-catalogue-card>a{display:flex;height:100%;flex-direction:column;color:var(--site-text);text-decoration:none}
.product-catalogue-media{width:100%;aspect-ratio:1/1;background:var(--site-surface-muted);display:flex;align-items:center;justify-content:center;overflow:hidden}
.product-catalogue-media img{display:block;width:100%;height:100%;object-fit:cover}
.product-catalogue-media-empty{padding:18px;text-align:center;color:var(--site-text-muted);font-size:.9rem;line-height:1.45}
.product-catalogue-info{display:flex;flex:1;min-width:0;flex-direction:column;padding:clamp(14px,1.6vw,19px)}
.product-catalogue-info .category{display:block;margin-bottom:8px;color:var(--site-brand-primary);font-size:.76rem;text-transform:uppercase;letter-spacing:.09em;font-weight:850}
.product-catalogue-info h4{margin:0 0 9px;font-size:1.08rem;line-height:1.35}
.product-catalogue-info p{margin:0 0 14px;color:var(--site-text-muted);font-size:.92rem;line-height:1.55}
.product-catalogue-card-foot{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-top:auto;padding-top:12px;border-top:1px solid var(--site-border)}
.product-catalogue-card-foot small{color:var(--site-text-muted);font-size:.82rem}
.product-catalogue-card-foot strong{font-size:1.06rem}
.product-catalogue-empty{padding:42px;border:1px dashed var(--site-border);border-radius:var(--site-radius);background:var(--site-surface-muted);text-align:center;color:var(--site-text-muted)}
@media(max-width:1120px){.product-catalogue-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:820px){.product-catalogue-hero{grid-template-columns:minmax(0,1fr);gap:24px}.product-catalogue-cover,.product-catalogue-cover img{min-height:320px}.product-catalogue-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.product-catalogue-section-head{align-items:flex-start;flex-direction:column;gap:10px}}
@media(max-width:560px){.product-catalogue-shell{padding:32px 16px 64px}.product-catalogue-hero{padding:18px}.product-catalogue-cover,.product-catalogue-cover img{min-height:280px}.product-catalogue-copy h1{font-size:clamp(2rem,8vw,2.8rem)}.product-catalogue-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.product-catalogue-actions{flex-direction:column}.product-catalogue-action{width:100%;box-sizing:border-box}.product-catalogue-info{padding:13px}.product-catalogue-info h4{font-size:1rem}.product-catalogue-info p{font-size:.86rem}.product-catalogue-card-foot{align-items:flex-start;flex-direction:column;gap:4px}}
@media(max-width:380px){.product-catalogue-grid{grid-template-columns:minmax(0,1fr)}.product-catalogue-info{padding:16px}.product-catalogue-info p{font-size:.9rem}.product-catalogue-card-foot{align-items:center;flex-direction:row;gap:12px}}
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
