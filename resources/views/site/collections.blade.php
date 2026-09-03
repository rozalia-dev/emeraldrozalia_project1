@extends('layouts.site')
@section('title','Our Collections — Emerald Rozalia')
@section('content')
<section class="catalog-hero catalog-hero-collections"><div class="catalog-breadcrumb"><a href="/">Home</a><span>›</span><span>Collections</span></div><p class="eyebrow">EMERALD ROZALIA LIMITED</p><h1>OUR <em>COLLECTIONS</em></h1><p>Timeless styles. Irish heritage. Explore signature collections crafted in Limerick with precision, passion and a commitment to quality.</p></section>
<section class="catalog-section"><div class="catalog-section-heading"><span></span><h2>EXPLORE OUR COLLECTIONS</h2><span></span></div><div class="collection-directory">
@foreach($categories as $category)<a class="directory-card" href="{{ route('category',$category) }}"><div class="catalog-image-placeholder" role="img" aria-label="{{ $category->name }} image pending approved source archive">IMAGE PENDING</div><div class="directory-card-copy"><h3>{{ $category->name }}</h3><p>{{ $category->description ?: 'Premium Emerald Rozalia headwear made in Limerick, Ireland.' }}</p><span>{{ $category->products_count }} styles <b>SHOP COLLECTION &rarr;</b></span></div></a>@endforeach
</div></section>
<section class="catalog-benefits"><div><b>IRISH MADE</b><span>Proudly crafted in Limerick</span></div><div><b>PREMIUM QUALITY</b><span>Finest materials, built to last</span></div><div><b>FAST DISPATCH</b><span>Worldwide delivery from Ireland</span></div><div><b>EASY RETURNS</b><span>30-day returns for peace of mind</span></div><div><b>SECURE PAYMENT</b><span>100% secure checkout and data protection</span></div></section>
@endsection
