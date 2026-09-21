@extends('layouts.site')
@section('body-class','shop-reference-page')
@section('title', ($activeCategory?->name ? $activeCategory->name.' — ' : 'Shop Hats & Caps — ').'Emerald Rozalia')

@push('styles')
<link rel="stylesheet" href="/css/shop.css?v=20260922-category-icons">
<style>
.shop-view-tools{flex-wrap:wrap;justify-content:flex-end}.shop-toolbar-filter{display:grid;gap:4px;min-width:142px}.shop-toolbar-filter>span{font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#5b685f}.shop-toolbar-filter select{min-width:142px;max-width:210px}.shop-toolbar-filter--county select{min-width:180px;max-width:250px}.shop-toolbar-filter--club select{min-width:200px;max-width:280px}.shop-toolbar-filter--subcategory select{min-width:180px;max-width:250px}.shop-toolbar-filter small{font-size:11px;line-height:1.25;color:#718078;max-width:210px}.shop-view-tools .shop-layout-switch{align-self:end}.shop-hero.has-category-banner:before{z-index:1;background-image:linear-gradient(90deg,#03100b 0%,rgba(3,16,11,.98) 29%,rgba(3,16,11,.82) 36%,rgba(3,16,11,.24) 46%,rgba(3,16,11,0) 58%)}.shop-hero.has-category-banner .shop-hero-shade{z-index:1}.shop-hero.has-category-banner .shop-hero-content{width:36%;max-width:640px;box-sizing:border-box}.shop-category-hero-media{position:absolute;z-index:0;inset:0 0 0 36%;display:block;overflow:hidden;background:#07130d}.shop-category-hero-media img{display:block;width:100%;height:100%;object-fit:cover;object-position:center}.shop-category-hero-media:hover img{transform:scale(1.01)}.shop-category-hero-media img{transition:transform .25s ease}@media(max-width:1050px){.shop-results-top{align-items:flex-start}.shop-view-tools{width:100%;justify-content:flex-start}.shop-toolbar-filter{flex:1 1 145px}.shop-toolbar-filter select{width:100%;max-width:none}.shop-hero.has-category-banner .shop-hero-content{width:44%}.shop-category-hero-media{left:44%}.shop-hero.has-category-banner:before{background-image:linear-gradient(90deg,#03100b 0%,rgba(3,16,11,.98) 35%,rgba(3,16,11,.72) 44%,rgba(3,16,11,.12) 58%,rgba(3,16,11,0) 70%)}}@media(max-width:700px){.shop-hero.has-category-banner .shop-hero-content{width:100%;max-width:none}.shop-category-hero-media{inset:0;opacity:.42}.shop-hero.has-category-banner:before{background-image:linear-gradient(90deg,rgba(3,16,11,.94),rgba(3,16,11,.72))}}@media(max-width:650px){.shop-view-tools{display:grid!important;grid-template-columns:1fr 1fr}.shop-toolbar-filter,.shop-view-tools>label{min-width:0!important}.shop-toolbar-filter select,.shop-view-tools>label select{width:100%;min-width:0!important;max-width:none}.shop-layout-switch{grid-column:1/-1;justify-self:end}}.shop-empty-actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:16px}.shop-empty-actions a{display:inline-flex;min-height:42px;align-items:center;justify-content:center;padding:10px 18px;border:1px solid #075b2f;border-radius:8px;font-weight:800;text-decoration:none}.shop-empty-actions .shop-empty-contact{background:#075b2f;color:#fff}.shop-empty-actions .shop-empty-contact:hover,.shop-empty-actions .shop-empty-contact:focus-visible{background:#064a27}.shop-empty-actions .shop-empty-reset{background:transparent;color:#075b2f}
.shop-product-card--coming-soon{display:flex;height:100%;flex-direction:column;cursor:default;border-style:dashed;border-color:rgba(143,191,89,.62);background:linear-gradient(180deg,#0b1912,#07110c)}
.shop-product-card--coming-soon:hover{transform:none;border-color:rgba(143,191,89,.62);box-shadow:none}
.shop-product-card--coming-soon .shop-product-media{display:grid;flex:0 0 auto;place-items:center;background:radial-gradient(circle at 50% 42%,rgba(46,133,74,.3),transparent 53%),linear-gradient(145deg,#0b2418,#07150e)}
.shop-coming-soon-art{display:grid;justify-items:center;gap:9px;padding:16px;text-align:center;color:#e8f1e9}
.shop-coming-soon-mark{display:grid;place-items:center;width:44px;height:44px;border:1px solid rgba(143,191,89,.72);border-radius:50%;color:#a8d94a;font-size:27px;line-height:1}
.shop-coming-soon-art strong{font-family:Georgia,'Times New Roman',serif;font-size:13px;letter-spacing:.1em}
.shop-coming-soon-art small{color:#c7d5cb;font-size:10px;letter-spacing:.08em;text-transform:uppercase}
.shop-product-card--coming-soon .shop-product-content{display:flex;flex:1;flex-direction:column}
.shop-product-card--coming-soon .shop-product-category{color:#a8d94a}
.shop-product-card--coming-soon .shop-product-content h3{color:#f4f7f3}
.shop-coming-soon-copy{margin:0 0 12px;color:#c7d5cb;font-size:12px;line-height:1.5}
.shop-product-card--coming-soon .shop-card-footer{margin-top:auto;padding-top:10px;border-top:1px solid rgba(196,218,202,.2)}
.shop-coming-soon-status{color:#c7d5cb;font-size:9px;font-weight:700;letter-spacing:.08em}
</style>
@endpush

@php
    $productImage = static function ($product): ?array {
        $media = $product->media?->firstWhere('type', 'image');

        return $media ? app(\App\Services\PublicMediaResolver::class)->forProductMedia($media, $product->name) : null;
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

    $mainCategories = $categories->filter(fn ($category) => is_null($category->parent_id))->values();
    $hasCategoryContext = (bool) $activeCategory || count($selectedCategories) === 1;
    $hierarchicalLocation = (bool) ($catalogCountyEnabled ?? false);
    $hierarchicalClub = (bool) ($catalogClubEnabled ?? false);
    $subcategoryReady = $hasCategoryContext && (! $hierarchicalClub || filled($selectedClub ?? ''));
    $displaySubcategoryOptions = collect($catalogSubcategoryOptions ?? [])->unique('value')->values();

    $resetUrl = $activeCategory ? route('category', $activeCategory) : route('shop');
    $currentMax = request()->filled('max_price') ? (int) request('max_price') : $priceCeiling;
    $activeFilterCount = count($selectedCategories) + count($selectedMaterials) + count($selectedColours) + count($selectedSizes)
        + (filled($selectedCountry ?? '') ? 1 : 0)
        + (filled($selectedCounty ?? '') ? 1 : 0)
        + (filled($selectedClub ?? '') ? 1 : 0)
        + (filled($selectedSubcategory ?? '') ? 1 : 0)
        + ($availability ? 1 : 0) + (request()->boolean('sale') ? 1 : 0)
        + (request()->filled('min_price') ? 1 : 0) + (request()->filled('max_price') ? 1 : 0);
    $selectedCountyLabel = filled($selectedCounty ?? '')
        ? (data_get(collect($catalogFilterCounties ?? [])->firstWhere('code', $selectedCounty), 'name') ?: $selectedCounty)
        : null;
    $selectedClubLabel = filled($selectedClub ?? '')
        ? (optional(($catalogFilterClubs ?? collect())->firstWhere('slug', $selectedClub))->name ?: $selectedClub)
        : null;
    $selectedSubcategoryLabel = filled($selectedSubcategory ?? '')
        ? (data_get(collect($catalogSubcategoryOptions ?? [])->firstWhere('value', $selectedSubcategory), 'label') ?: $selectedSubcategory)
        : null;

    $categoryHeroBanner = null;
    $categoryHeroMedia = null;
    if ($activeCategory) {
        $categoryHeroBanner = \App\Models\Banner::query()
            ->with('media')
            ->where('position', 'Category Hero')
            ->where('status', 'published')
            ->whereJsonContains('specific_pages', 'category:'.$activeCategory->slug)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();

        if ($categoryHeroBanner?->media_uuid) {
            $categoryHeroMedia = app(\App\Services\PublicMediaResolver::class)
                ->forUuid($categoryHeroBanner->media_uuid, $categoryHeroBanner->alt_text ?: $categoryHeroBanner->title);
        }
    }
@endphp

@section('content')
<div class="shop-page" data-shop-page>
    <section class="shop-hero {{ $categoryHeroMedia ? 'has-category-banner' : '' }}">
        <div class="shop-hero-shade"></div>
        @if($categoryHeroMedia)
            @if(filled($categoryHeroBanner->target_url) && $categoryHeroBanner->target_url !== '/')
                <a class="shop-category-hero-media" href="{{ $categoryHeroBanner->target_url }}" @if($categoryHeroBanner->target_type === 'external') target="_blank" rel="noopener" @endif aria-label="{{ $categoryHeroBanner->aria_label ?: $categoryHeroBanner->title }}">
                    <img src="{{ $categoryHeroMedia['url'] }}" @if($categoryHeroMedia['srcset']) srcset="{{ $categoryHeroMedia['srcset'] }}" sizes="(max-width:700px) 100vw, 64vw" @endif width="{{ $categoryHeroMedia['width'] ?: '' }}" height="{{ $categoryHeroMedia['height'] ?: '' }}" alt="{{ $categoryHeroMedia['alt'] }}" loading="eager" fetchpriority="high">
                </a>
            @else
                <div class="shop-category-hero-media" aria-label="{{ $categoryHeroBanner->aria_label ?: $categoryHeroBanner->title }}">
                    <img src="{{ $categoryHeroMedia['url'] }}" @if($categoryHeroMedia['srcset']) srcset="{{ $categoryHeroMedia['srcset'] }}" sizes="(max-width:700px) 100vw, 64vw" @endif width="{{ $categoryHeroMedia['width'] ?: '' }}" height="{{ $categoryHeroMedia['height'] ?: '' }}" alt="{{ $categoryHeroMedia['alt'] }}" loading="eager" fetchpriority="high">
                </div>
            @endif
        @endif
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

    <section class="shop-category-strip" aria-label="Main product categories">
        <a class="{{ !$activeCategory && !$selectedCategories ? 'is-active' : '' }}" href="{{ route('shop') }}"><span class="shop-category-icon" data-category-icon="grid"><x-icon name="grid" size="19" /></span>All Products</a>
        @foreach($mainCategories as $category)
            <a class="{{ in_array($category->slug, $selectedCategories, true) ? 'is-active' : '' }}" href="{{ route('category', $category) }}">
                @php($resolvedCategoryIcon = \App\Support\CategoryIcons::resolve($category->icon, $category->slug, $category->name))
                <span class="shop-category-icon" data-category-icon="{{ $resolvedCategoryIcon }}"><x-icon name="{{ $resolvedCategoryIcon }}" size="19" /></span>{{ $category->name }}
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
                        @foreach($mainCategories as $category)
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
                    <input class="shop-price-range" type="range" name="max_price" aria-label="Maximum price" min="0" max="{{ $priceCeiling }}" step="1" value="{{ $currentMax }}" oninput="document.getElementById('shop-price-value').textContent='€'+this.value">
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
                            @if(filled($selectedCountry ?? ''))<span>{{ optional(($catalogFilterCountries ?? collect())->firstWhere('code', $selectedCountry))->name ?: $selectedCountry }}</span>@endif
                            @if($selectedCountyLabel)<span>{{ $selectedCountyLabel }}</span>@endif
                            @if($selectedClubLabel)<span>{{ $selectedClubLabel }}</span>@endif
                            @if($selectedSubcategoryLabel)<span>{{ $selectedSubcategoryLabel }}</span>@endif
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
                    @if($hierarchicalLocation)
                    <label class="shop-toolbar-filter">
                        <span>Country</span>
                        <select name="country" form="shop-filter-form" aria-label="Select country" data-shop-country>
                            <option value="">Select Country</option>
                            @foreach($catalogFilterCountries as $country)
                                <option value="{{ $country->code }}" @selected($selectedCountry === $country->code)>{{ $country->name }}</option>
                            @endforeach
                        </select>
                        <small>
                            @if(in_array($catalogCountryScope, ['traditional', 'heritage'], true))
                                EU countries only for {{ str($catalogCountryScope)->headline() }}. Choose country first.
                            @else
                                Choose country first.
                            @endif
                        </small>
                    </label>
                    <label class="shop-toolbar-filter shop-toolbar-filter--county">
                        <span>County</span>
                        <select name="county" form="shop-filter-form" aria-label="Select county" data-shop-county @disabled(!$selectedCountry)>
                            @if(!$selectedCountry)
                                <option value="">Select Country First</option>
                            @else
                                <option value="">Select County</option>
                                @foreach($catalogFilterCounties as $county)
                                    <option value="{{ $county['code'] }}" @selected($selectedCounty === $county['code'])>{{ $county['name'] }}</option>
                                @endforeach
                            @endif
                        </select>
                        <small>Then choose county.</small>
                    </label>
                    @endif
                    @if($hierarchicalClub)
                    <label class="shop-toolbar-filter shop-toolbar-filter--club">
                        <span>{{ strtoupper((string) $catalogCountryScope) }} Club</span>
                        <select name="club" form="shop-filter-form" aria-label="Select {{ strtoupper((string) $catalogCountryScope) }} club" data-shop-club @disabled(!$selectedCountry || !$selectedCounty)>
                            @if(!$selectedCountry)
                                <option value="">Select Country First</option>
                            @elseif(!$selectedCounty)
                                <option value="">Select County First</option>
                            @elseif(($catalogFilterClubs ?? collect())->isEmpty())
                                <option value="">No Clubs Available</option>
                            @else
                                <option value="">Select Club</option>
                                @foreach($catalogFilterClubs as $club)
                                    <option value="{{ $club->slug }}" @selected($selectedClub === $club->slug)>{{ $club->name }}</option>
                                @endforeach
                            @endif
                        </select>
                        <small>Then choose club / team.</small>
                    </label>
                    @endif
                    <label class="shop-toolbar-filter shop-toolbar-filter--subcategory">
                        <span>Subcategory</span>
                        <select name="subcategory" form="shop-filter-form" aria-label="Select subcategory" data-shop-subcategory @disabled(!$subcategoryReady)>
                            @if(!$hasCategoryContext)
                                <option value="">Select Main Category First</option>
                            @elseif($hierarchicalClub && !filled($selectedClub ?? ''))
                                <option value="">Select Club First</option>
                            @else
                                <option value="">All Subcategories</option>
                                @foreach($displaySubcategoryOptions as $option)
                                    <option value="{{ $option['value'] }}" @selected($selectedSubcategory === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            @endif
                        </select>
                        @if($hasCategoryContext && $displaySubcategoryOptions->isEmpty())
                            <small>No subcategories are configured for this main category.</small>
                        @elseif($hierarchicalClub)
                            <small>Choose after country, county and club.</small>
                        @elseif($hierarchicalLocation)
                            <small>Independent of country and county.</small>
                        @endif
                    </label>
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
                        $hasManagedSpin = $product->spins->contains(fn ($spin) => $spin->status === 'published' && $spin->visibility === 'public' && count($spin->frames ?? []) >= 2);
                        $hasApprovedSpinMedia = $product->media->where('type', 'spin_360')->count() >= 2;
                        $hasSpin = $hasManagedSpin || $hasApprovedSpinMedia;
                        $hasTryOn = $product->media->where('type', 'try_on')->isNotEmpty() || $product->tryOnAssets->contains(fn ($asset) => $asset->status === 'published' && $asset->visibility === 'public');
                        $hasSale = filled($product->compare_price) && (float) $product->compare_price > (float) $product->price;
                        $discount = $hasSale ? max(1, (int) round((1 - ((float)$product->price / (float)$product->compare_price)) * 100)) : null;
                    @endphp
                    <article class="shop-product-card">
                        <div class="shop-product-media">
                            <a href="{{ route('product', $product) }}" aria-label="View {{ $product->name }}">
                                @if($image = $productImage($product))
                                    <img src="{{ $image['url'] }}" @if($image['srcset']) srcset="{{ $image['srcset'] }}" sizes="{{ $image['sizes'] }}" @endif width="{{ $image['width'] ?: '' }}" height="{{ $image['height'] ?: '' }}" alt="{{ $image['alt'] }}" loading="lazy">
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
                                @if($hasTryOn)<a href="{{ route('virtual-tryon', ['product_id'=>$product->id]) }}" title="Virtual try-on" aria-label="Open virtual try-on for {{ $product->name }}"><x-icon name="camera" size="14" /></a>@endif
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
                        <p>Try removing a filter or contact us and we can help you find the right Emerald Rozalia product.</p>
                        <div class="shop-empty-actions">
                            <a class="shop-empty-contact" href="{{ route('contact') }}">CONTACT US</a>
                            <a class="shop-empty-reset" href="{{ $resetUrl }}">RESET FILTERS</a>
                        </div>
                    </div>
                @endforelse

                @if($products->count() > 0)
                    @for($shopPlaceholder = $products->count(); $shopPlaceholder < 4; $shopPlaceholder++)
                        <article class="shop-product-card shop-product-card--coming-soon" aria-label="More products coming soon. This card is not available to purchase.">
                            <div class="shop-product-media shop-coming-soon-media" aria-hidden="true">
                                <div class="shop-coming-soon-art">
                                    <span class="shop-coming-soon-mark">+</span>
                                    <strong>MORE STYLES</strong>
                                    <small>Coming soon</small>
                                </div>
                            </div>
                            <div class="shop-product-content">
                                <small class="shop-product-category">COMING SOON</small>
                                <h3>More styles are on the way</h3>
                                <p class="shop-coming-soon-copy">New products will appear here when they are published.</p>
                                <div class="shop-card-footer"><span class="shop-coming-soon-status">NOT AVAILABLE YET</span></div>
                            </div>
                        </article>
                    @endfor
                @endif
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
<script src="/js/shop.js?v=20260921-public-geo-club-filters" defer></script>
@endpush