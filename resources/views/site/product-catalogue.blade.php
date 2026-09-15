@extends('layouts.site')
@section('title', $catalogue->title.' — Emerald Rozalia')
@section('body-class','product-catalogue-page')

@push('styles')
<style>
.product-catalogue-shell{max-width:1120px;margin:0 auto;padding:64px 24px 84px;display:grid;grid-template-columns:minmax(260px,420px) 1fr;gap:56px;align-items:center}.product-catalogue-cover{min-height:520px;border:1px solid var(--site-border);border-radius:var(--site-radius);background:linear-gradient(145deg,var(--site-brand-primary),var(--site-brand-secondary));display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 18px 45px rgba(0,0,0,.13)}.product-catalogue-cover img{width:100%;height:100%;min-height:520px;object-fit:cover}.product-catalogue-cover-fallback{padding:42px;text-align:center;color:#fff}.product-catalogue-cover-fallback strong{display:block;font-family:var(--site-heading-family);font-size:2rem;margin-bottom:12px}.product-catalogue-copy .eyebrow{font-size:.78rem;letter-spacing:.16em;font-weight:800;color:var(--site-brand-primary)}.product-catalogue-copy h1{font-family:var(--site-heading-family);font-size:clamp(2.2rem,5vw,4rem);line-height:1.05;margin:10px 0 16px}.product-catalogue-copy p{font-size:1.03rem;line-height:1.75;color:var(--site-text-muted);max-width:640px}.product-catalogue-meta{display:flex;flex-wrap:wrap;gap:10px;margin:22px 0}.product-catalogue-meta span{border:1px solid var(--site-border);border-radius:999px;padding:8px 13px;font-size:.82rem;background:var(--site-surface-muted)}.product-catalogue-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.product-catalogue-download{display:inline-flex;align-items:center;gap:9px;padding:13px 20px;border-radius:var(--site-button-radius);background:var(--site-brand-primary);color:#fff;text-decoration:none;font-weight:800;letter-spacing:.04em}.product-catalogue-back{display:inline-flex;align-items:center;padding:13px 20px;border:1px solid var(--site-border);border-radius:var(--site-button-radius);color:var(--site-text);text-decoration:none;font-weight:700}@media(max-width:760px){.product-catalogue-shell{grid-template-columns:1fr;padding-top:34px;gap:32px}.product-catalogue-cover,.product-catalogue-cover img{min-height:400px}}
</style>
@endpush

@section('content')
<section class="product-catalogue-shell">
    <div class="product-catalogue-cover">
        @if($catalogue->cover_path)
            <img src="{{ route('catalogue.cover') }}" alt="{{ $catalogue->title }} cover">
        @else
            <div class="product-catalogue-cover-fallback">
                <strong>EMERALD ROZALIA LIMITED</strong>
                <span>IRISH MANUFACTURER · LIMERICK, IRELAND</span>
            </div>
        @endif
    </div>
    <div class="product-catalogue-copy">
        <div class="eyebrow">PRODUCT CATALOGUE</div>
        <h1>{{ $catalogue->title }}</h1>
        @if($catalogue->description)<p>{{ $catalogue->description }}</p>@else<p>Browse the latest Emerald Rozalia hats and caps catalogue, including current collections and product ranges.</p>@endif
        <div class="product-catalogue-meta">
            @if($catalogue->version)<span>Version {{ $catalogue->version }}</span>@endif
            @if($catalogue->pdf_size)<span>{{ number_format($catalogue->pdf_size / 1048576, 1) }} MB PDF</span>@endif
            @if($catalogue->published_at)<span>Published {{ $catalogue->published_at->format('d M Y') }}</span>@endif
        </div>
        <div class="product-catalogue-actions">
            <a class="product-catalogue-download" href="{{ route('catalogue.download') }}"><x-icon name="download" size="17" /> DOWNLOAD PRODUCT CATALOGUE</a>
            <a class="product-catalogue-back" href="{{ route('shop') }}">VIEW PRODUCTS</a>
        </div>
    </div>
</section>
@endsection
