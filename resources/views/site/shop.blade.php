@extends('layouts.site')
@section('body-class','shop-reference-page')
@section('title', ($activeCategory?->name ? $activeCategory->name.' — ' : 'Shop Hats & Caps — ').'Emerald Rozalia')

@push('styles')
<link rel="stylesheet" href="/css/shop.css?v=20260910-reference">
@endpush

@php
    $productImageUrl = static function (?string $path): ?string {
        if (blank($path)) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) return $path;
        if (str_starts_with($path, 'assets/') || str_starts_with($path, 'images/') || str_starts_with($path, 'storage/')) return asset($path);
        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    };

    $swatchColour = static function ($value): string {
        $value = strtolower(trim((string) $value));
        $palette = [
            'black'=>'#121614','ivory'=>'#e7e0d2','stone'=>'#8d8878','brown'=>'#62482d','olive'=>'#676b30',
            'navy'=>'#1b2d43','camel'=>'#aa8455','green'=>'#28543a','forest green'=>'#173c27','grey'=>'#747875',
            'gray'=>'#747875','charcoal'=>'#363a37','cream'=>'#ded3b7','beige'=>'#b5a381','white'=>'#ecebe5','red'=>'#8b352d'
        ];
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)) return $value;
        return $palette[$value] ?? '#31412f';
    };

    $resetUrl = $activeCategory ? route('category', $activeCategory) : route('shop');
    $currentMax = request()->filled('max_price') ? (int) request('max_price') : $priceCeiling;
    $activeFilterCount = count($selectedCategories) + count($selectedMaterials) + count($selectedColours) + count($selectedSizes)
        + ($availability ? 1 : 0) + (request()->boolean('sale') ? 1 : 0)
        + (request()->filled('min_price') ? 1 : 0) + (request()->filled('max_price') ? 1 : 0);
@endphp

@section('content')
<div class="shop-page" data-shop-page>
    <section class="shop-hero">
        <div class="shop-hero-shade"></div>
        <div class="shop-hero-content">
            <nav class="shop-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a>
                <x-icon name="chevron-right" size="12" />
                @if($activeCategory)
                    <a href="{{ route('shop') }}">Shop</a>
                    <x-icon name="chevron-right" size="12" />
                    <span>{{ $activeCategory->name }}</span>
                @else
                    <span>Shop</span>
                @endif
            </nav>
            <p class="shop-eyebrow">EMERALD ROZALIA LIMITED</p>
            <h1>{{ $activeCategory?->name ? strtoupper($activeCategory->name) : 'SHOP ALL HATS & CAPS' }}</h1>
            <div class="shop-hero-rule" aria-hidden="true"><span>♣</span></div>
            <p class="shop-hero-lead">Irish made. Premium quality. Made in Limerick.</p>
            <p class="shop-hero-copy">Discover premium caps, hats and heritage headwear designed for everyday wear and crafted with Emerald Rozalia quality.</p>
        </div>
    </section>

    <section class="shop-assurances" aria-label="Shopping assurances">
        <div><x-icon name="clover" size="28" /><span><b>IRISH MADE</b><small>Proudly made in Limerick</small></span></div>
        <div><x-icon name="star" size="28" /><span><b>PREMIUM QUALITY</b><small>Crafted to last</small></span></div>
        <div><x-icon name="truck" size="28" /><span><b>WORLDWIDE DELIVERY</b><small>Fast tracked dispatch</small></span></div>
        <div><x-icon name="package" size="28" /><span><b>EASY RETURNS</b><small>30-day returns</small></span></div>
        <div><x-icon name="shopping-bag" size="28" /><span><b>SECURE CHECKOUT</b><small>Protected payment</small></span></div>
    </section>

    <section class="shop-category-strip" aria-label="Popular categories">
        <a class="{{ !$activeCategory && !$selectedCategories ? 'is-active' : '' }}" href="{{ route('shop') }}"><span>ALL</span>All Products</a>
        @foreach($categories->take(7) as $category)
            <a class="{{ in_array($category->slug, $selectedCategories, true) ? 'is-active' : '' }}" href="{{ route('category', $category) }}">
                <span>{{ str($category->name)->substr(0,1)->upper() }}</span>{{ $category->name }}
            </a>
        @endforeach
    </section>

    <section class="shop-catalog">
        <button type="button" class="shop-mobile-filter-button" data-shop-filter-toggle aria-expanded="false">
            <x-icon name="filter" size="16" /> Filters @if($activeFilterCount)<span>{{ $activeFilterCount }}</span>@endif
        </button>

        <aside class="shop-filters" data-shop-filters>
            <form method="get" action="{{ $activeCategory ? route('category', $activeCategory) : route('shop') }}" id="shop-filter-form">
                <div class="shop-filter-title">
                    <div><x-icon name="filter" size="15" /><h2>FILTERS</h2></div>
                    <a href="{{ $resetUrl }}">RESET ALL</a>
                </div>

                <div class="shop-search-box">
                    <x-icon name="search" size="15" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search products or SKU" aria-label="Search shop">
                </div>

                @unless($activeCategory)
                <details class="shop-filter-group" open>
                    <summary>CATEGORY <x-icon name="chevron-down" size="13" /></summary>
                    <div class="shop-filter-options">
                        @foreach($categories->take(12) as $category)
                            <label class="shop-check">
                                <input type="checkbox" name="category[]" value="{{ $category->slug }}" @checked(in_array($category->slug, $selectedCategories, true))>
                                <span>{{ $category->name }}</span><small>{{ number_format($category->products_count) }}</small>
                            </label>
                        @endforeach
                    </div>
                </details>
                @endunless

                <details class="shop-filter-group" open>
                    <summary>COLOUR <x-icon name="chevron-down" size="13" /></summary>
                    <div class="shop-colour-grid">
                        @foreach($colourOptions as $colour => $hex)
                            <label class="shop-colour-choice" title="{{ str($colour)->headline() }}">
                                <input type="checkbox" name="colour[]" value="{{ $colour }}" @checked(in_array($colour, $selectedColours, true))>
                                <span style="--shop-swatch:{{ $hex }}"></span><small>{{ str($colour)->headline() }}</small>
                            </label>
                        @endforeach
                    </div>
                </details>

                <details class="shop-filter-group" open>
                    <summary>MATERIAL <x-icon name="chevron-down" size="13" /></summary>
                    <div class="shop-filter-options">
                        @foreach($materialOptions as $material)
                            <label class="shop-check">
                                <input type="checkbox" name="material[]" value="{{ $material }}" @checked(in_array($material, $selectedMaterials, true))>
                                <span>{{ $material }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>

                <details class="shop-filter-group" open>
                    <summary>SIZE <x-icon name="chevron-down" size="13" /></summary>
                    <div class="shop-size-grid">
                        @foreach($sizeOptions as $size)
                            <label><input type="checkbox" name="size[]" value="{{ $size }}" @checked(in_array($size, $selectedSizes, true))><span>{{ $size }}</span></label>
                        @endforeach
                    </div>
                </details>

                <details class="shop-filter-group" open>
                    <summary>PRICE <x-icon name="chevron-down" size="13" /></summary>
                    <div class="shop-price-head"><span>€0</span><strong id="shop-price-value">€{{ $currentMax }}</strong></div>
                    <input class="shop-price-range" type="range" name="max_price" min="0" max="{{ $priceCeiling }}" step="1" value="{{ $currentMax }}" oninput="document.getElementById('shop-price-value').textContent='€'+this.value">
                    <div class="shop-price-inputs">
                        <label>Min €<input type="number" name="min_price" min="0" step="1" value="{{ request('min_price') }}" placeholder="0"></label>
                        <label>Max €<input type="number" min="0" step="1" value="{{ request('max_price') }}" placeholder="{{ $priceCeiling }}" data-shop-max-price-mirror></label>
                    </div>
                </details>

                <details class="shop-filter-group" open>
                    <summary>AVAILABILITY <x-icon name="chevron-down" size="13" /></summary>
                    <div class="shop-filter-options">
                        <label class="shop-radio"><input type="radio" name="availability" value="" @checked(!$availability)><span>All stock</span></label>
                        <label class="shop-radio"><input type="radio" name="availability" value="in_stock" @checked($availability==='in_stock')><span>In stock</span></label>
                        <label class="shop-radio"><input type="radio" name="availability" value="out_of_stock" @checked($availability==='out_of_stock')><span>Out of stock</span></label>
                        <label class="shop-check"><input type="checkbox" name="sale" value="1" @checked(request()->boolean('sale'))><span>On sale</span></label>
                    </div>
                </details>

                <input type="hidden" name="sort" value="{{ $sort }}" data-shop-sort-hidden>
                <input type="hidden" name="per_page" value="{{ $perPage }}" data-shop-per-page-hidden>
                <button class="shop-apply" type="submit">APPLY FILTERS <x-icon name="arrow-right" size="14" /></button>
            </form>
        </aside>

        <div class="shop-results">
            <div class="shop-results-top">
                <div>
                    <p class="shop-results-count">Showing <b>{{ $products->firstItem() ?: 0 }}–{{ $products->lastItem() ?: 0 }}</b> of <b>{{ number_format($products->total()) }}</b> products</p>
                    @if($activeFilterCount || request()->filled('q'))
                        <div class="shop-active-filters">
                            @if(request()->filled('q'))<span>“{{ request('q') }}”</span>@endif
                            @foreach($selectedCategories as $value)<span>{{ str($value)->replace('-',' ')->headline() }}</span>@endforeach
                            @foreach($selectedMaterials as $value)<span>{{ $value }}</span>@endforeach
                            @foreach($selectedColours as $value)<span>{{ str($value)->headline() }}</span>@endforeach
                            @foreach($selectedSizes as $value)<span>{{ $value }}</span>@endforeach
                            @if($availability)<span>{{ str($availability)->replace('_',' ')->headline() }}</span>@endif
                            @if(request()->boolean('sale'))<span>On Sale</span>@endif
                            <a href="{{ $resetUrl }}">Clear</a>
                        </div>
                    @endif
                </div>
                <div class="shop-view-tools">
                    <label>Sort by
                        <select aria-label="Sort products" data-shop-sort>
                            <option value="newest" @selected($sort==='newest')>Newest</option>
                            <option value="price_low" @selected($sort==='price_low')>Price: Low to High</option>
                            <option value="price_high" @selected($sort==='price_high')>Price: High to Low</option>
                            <option value="rating" @selected($sort==='rating')>Highest Rated</option>
                            <option value="popular" @selected($sort==='popular')>Most Reviewed</option>
                            <option value="name" @selected($sort==='name')>Name: A–Z</option>
                        </select>
                    </label>
                    <label>Show
                        <select aria-label="Products per page" data-shop-per-page>
                            <option value="12" @selected($perPage===12)>12</option>
                            <option value="24" @selected($perPage===24)>24</option>
                            <option value="36" @selected($perPage===36)>36</option>
                        </select>
                    </label>
                    <div class="shop-layout-switch" aria-label="Product layout">
                        <button type="button" class="is-active" data-shop-grid aria-label="Grid view"><x-icon name="grid" size="15" /></button>
                        <button type="button" data-shop-list aria-label="List view"><x-icon name="list" size="15" /></button>
                    </div>
                </div>
            </div>

            <div class="shop-product-grid" data-shop-products>
                @forelse($products as $product)
                    @php
                        $ratingValue = (float) ($product->reviews_avg_rating ?? 0);
                        $rating = max(0, min(5, (int) round($ratingValue)));
                        $productColours = collect($product->colours ?? [])->filter()->take(4);
                        $quickVariant = $product->variants->first(fn ($variant) => $variant->is_active && (int) $variant->stock > 0);
                        $canQuickAdd = $product->variants->isNotEmpty() ? (bool) $quickVariant : (int) $product->stock > 0;
                        $rawSpinFrames = json_decode((string) ($product->getRawOriginal('spin_images') ?? '[]'), true);
                        $hasManagedSpin = $product->spins->contains(fn ($spin) => $spin->status === 'published' && $spin->visibility === 'public' && count($spin->frames ?? []) >= 2);
                        $hasSpin = $hasManagedSpin || (is_array($rawSpinFrames) && count($rawSpinFrames) >= 2);
                        $hasTryOn = filled($product->try_on_asset) || $product->tryOnAssets->contains(fn ($asset) => $asset->status === 'published' && $asset->visibility === 'public');
                        $hasSale = filled($product->compare_price) && (float) $product->compare_price > (float) $product->price;
                        $discount = $hasSale ? max(1, (int) round((1 - ((float)$product->price / (float)$product->compare_price)) * 100)) : null;
                    @endphp
                    <article class="shop-product-card">
                        <div class="shop-product-media">
                            <a href="{{ route('product', $product) }}" aria-label="View {{ $product->name }}">
                                @if($image = $productImageUrl($product->image))
                                    <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy">
                                @else
                                    <span class="shop-product-placeholder"><x-icon name="clover" size="34" /><b>EMERALD ROZALIA</b><small>Product image coming soon</small></span>
                                @endif
                            </a>
                            <div class="shop-card-badges">
                                @if($product->is_new)<span class="shop-badge shop-badge--new">NEW</span>@endif
                                @if($discount)<span class="shop-badge shop-badge--sale">-{{ $discount }}%</span>@endif
                            </div>
                            <div class="shop-card-media-tools">
                                @if($hasSpin)<a href="{{ route('product', $product) }}#product-360" title="360° product view">360°</a>@endif
                                @if($hasTryOn)<a href="{{ route('virtual-tryon', ['product_id'=>$product->id]) }}" title="Virtual try-on"><x-icon name="camera" size="14" /></a>@endif
                            </div>
                            @auth
                                <form method="post" action="{{ route('wishlist.toggle', $product) }}" class="shop-wishlist-form">@csrf<button type="submit" aria-label="Toggle {{ $product->name }} wishlist"><x-icon name="heart" size="16" /></button></form>
                            @else
                                <a class="shop-wishlist-link" href="{{ route('login') }}" aria-label="Sign in to save {{ $product->name }}"><x-icon name="heart" size="16" /></a>
                            @endauth
                        </div>
                        <div class="shop-product-content">
                            <a href="{{ route('product', $product) }}" class="shop-product-main-link">
                                <small class="shop-product-category">{{ $product->category?->name ?: 'Emerald Rozalia' }}</small>
                                <h3>{{ $product->name }}</h3>
                                <div class="shop-rating" aria-label="Rated {{ number_format($ratingValue,1) }} out of 5">
                                    <span>{{ str_repeat('★', $rating) }}{{ str_repeat('☆', 5-$rating) }}</span><small>({{ number_format($product->reviews_count ?? 0) }})</small>
                                </div>
                                <div class="shop-price-row"><strong>€{{ number_format((float)$product->price, 2) }}</strong>@if($hasSale)<del>€{{ number_format((float)$product->compare_price,2) }}</del>@endif</div>
                            </a>
                            <div class="shop-card-footer">
                                <div class="shop-swatches" aria-label="Available colours">
                                    @forelse($productColours as $colour)<span title="{{ $colour }}" style="--shop-swatch:{{ $swatchColour($colour) }}"></span>@empty<span style="--shop-swatch:#2d4936"></span>@endforelse
                                    @if(collect($product->colours ?? [])->filter()->count() > 4)<small>+{{ collect($product->colours)->filter()->count()-4 }}</small>@endif
                                </div>
                                <span class="shop-stock {{ $canQuickAdd ? 'is-in' : 'is-out' }}">{{ $canQuickAdd ? 'In Stock' : 'Out of Stock' }}</span>
                            </div>
                            <div class="shop-product-actions">
                                <a href="{{ route('product', $product) }}">VIEW DETAILS</a>
                                <form method="post" action="{{ route('cart.add', $product) }}">
                                    @csrf
                                    <input type="hidden" name="quantity" value="1">
                                    @if($quickVariant)<input type="hidden" name="variant_id" value="{{ $quickVariant->id }}">@endif
                                    <button type="submit" @disabled(!$canQuickAdd)><x-icon name="shopping-bag" size="15" /> {{ $canQuickAdd ? 'ADD TO CART' : 'SOLD OUT' }}</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="shop-empty">
                        <x-icon name="search" size="32" />
                        <h2>No products match those filters</h2>
                        <p>Try removing a filter or search term to see more Emerald Rozalia products.</p>
                        <a href="{{ $resetUrl }}">RESET FILTERS</a>
                    </div>
                @endforelse
            </div>

            @if($products->hasPages())
                <div class="shop-pagination">{{ $products->onEachSide(1)->links() }}</div>
            @endif
        </div>
    </section>

    <section class="shop-experience">
        <div class="shop-tryon-callout">
            <span class="shop-experience-icon"><x-icon name="camera" size="32" /></span>
            <div><small>VIRTUAL STUDIO</small><h2>SEE IT ON YOU</h2><p>Upload your photo and preview selected Emerald Rozalia hats and caps before you buy.</p></div>
            <a href="{{ route('virtual-tryon') }}">TRY IT ON <x-icon name="arrow-right" size="14" /></a>
        </div>
        <div class="shop-service-callout">
            <span class="shop-experience-icon"><x-icon name="package" size="32" /></span>
            <div><small>SHOP WITH CONFIDENCE</small><h2>LOVE IT OR RETURN IT</h2><p>Simple 30-day returns and customer support through the Emerald Rozalia Communication Centre.</p></div>
            <a href="{{ route('contact') }}">CUSTOMER CARE <x-icon name="arrow-right" size="14" /></a>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="/js/shop.js?v=20260910-reference" defer></script>
@endpush
