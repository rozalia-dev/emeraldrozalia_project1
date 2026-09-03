@extends('layouts.admin')

@section('title','Product Manager')

@section('content')
@php
    $stockStatus=(string)request()->query('stock_status','');
    $productStatus=(string)request()->query('product_status','');
    $featured=request()->boolean('featured');
    $summaryTotal=max(1,$stats['total']);
    $publishedPercent=min(100,($stats['published']/$summaryTotal)*100);
    $draftPercent=min(100,($stats['draft']/$summaryTotal)*100);
    $hiddenPercent=min(100,($stats['hidden']/$summaryTotal)*100);
    $outOfStockPercent=min(100,($stats['out_of_stock']/$summaryTotal)*100);
    $money=fn($value)=>'€'.number_format((float)$value,2);
    $percent=fn($value)=>number_format(($value/$summaryTotal)*100,1).'%';
@endphp

<div class="pm-page">
    <div class="pm-page-head">
        <div>
            <p class="pm-eyebrow">ADMIN / OPERATIONS</p>
            <h1>Product Manager / {{ $tabs[$tab] }}</h1>
            <p class="pm-subtitle">Manage all products, inventory, pricing, visibility and performance across your franchise network.</p>
        </div>
        <div class="pm-head-meta">
            <nav class="pm-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Home</a>
                <x-icon name="chevron-right" size="12" />
                <span>Website &amp; Products</span>
                <x-icon name="chevron-right" size="12" />
                <strong>Product Manager</strong>
            </nav>
            <div class="pm-date-card">
                <x-icon name="clock" size="19" />
                <span><small>Today</small><strong>{{ now()->format('l, j F Y') }}</strong><b>{{ now()->format('H:i') }}</b></span>
            </div>
        </div>
    </div>

    <section class="pm-kpis" aria-label="Product summary metrics">
        <article class="pm-kpi">
            <span class="pm-kpi-icon pm-kpi-green"><x-icon name="package" size="20" /></span>
            <small>Total Products</small><strong>{{ number_format($stats['total']) }}</strong><em><b><x-icon name="arrow-up" size="10" /></b> Catalogue inventory</em>
        </article>
        <article class="pm-kpi">
            <span class="pm-kpi-icon pm-kpi-purple"><x-icon name="tag" size="20" /></span>
            <small>Published</small><strong>{{ number_format($stats['published']) }}</strong><em><b><x-icon name="arrow-up" size="10" /></b> Visible to customers</em>
        </article>
        <article class="pm-kpi">
            <span class="pm-kpi-icon pm-kpi-orange"><x-icon name="eye" size="20" /></span>
            <small>Hidden / Draft</small><strong>{{ number_format($stats['hidden_draft']) }}</strong><em><b><x-icon name="arrow-right" size="10" /></b> Needs review</em>
        </article>
        <article class="pm-kpi">
            <span class="pm-kpi-icon pm-kpi-blue"><x-icon name="package" size="20" /></span>
            <small>Out of Stock</small><strong>{{ number_format($stats['out_of_stock']) }}</strong><em class="pm-negative"><b><x-icon name="arrow-down" size="10" /></b> Stock alert</em>
        </article>
        <article class="pm-kpi">
            <span class="pm-kpi-icon pm-kpi-teal"><x-icon name="credit-card" size="20" /></span>
            <small>Total Value</small><strong>{{ $money($stats['total_value']) }}</strong><em><b><x-icon name="arrow-up" size="10" /></b> Retail value</em>
        </article>
        <article class="pm-kpi">
            <span class="pm-kpi-icon pm-kpi-violet"><x-icon name="star" size="20" /></span>
            <small>Avg. Rating</small><strong>{{ number_format($stats['average_rating'],1) }} / 5</strong><em>Approved reviews</em>
        </article>
    </section>

    <div class="pm-workspace">
        <section class="pm-catalog" aria-labelledby="catalogue-heading">
            <h2 class="sr-only" id="catalogue-heading">Product catalogue</h2>
            <div class="pm-catalog-toolbar">
                <nav class="pm-tabs" aria-label="Product views">
                    @foreach($tabs as $slug=>$label)
                        <a class="{{ $tab===$slug?'is-active':'' }}" href="{{ request()->fullUrlWithQuery(['tab'=>$slug,'page'=>null]) }}">{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="pm-toolbar-actions">
                    <form class="pm-inline-search" method="get" action="{{ route('admin.resource','product-manager') }}">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <label class="sr-only" for="product-manager-search">Search products</label>
                        <input id="product-manager-search" name="q" value="{{ $search }}" type="search" placeholder="Search by name, SKU, barcode...">
                        <button type="submit" aria-label="Search products"><x-icon name="search" size="16" /></button>
                    </form>
                    <a class="pm-filter-jump" href="#product-filters"><x-icon name="filter" size="15" /> <span>Filters</span></a>
                    <details class="pm-add-menu">
                        <summary><x-icon name="plus" size="15" /> Add Product <x-icon name="chevron-right" size="12" /></summary>
                        <div>
                            <a href="{{ route('admin.resource','add-product') }}"><x-icon name="plus" size="14" /> Add New Product</a>
                            <a href="{{ route('admin.bulk-upload') }}"><x-icon name="upload" size="14" /> Bulk Product Upload</a>
                            <a href="{{ route('admin.media.index') }}"><x-icon name="camera" size="14" /> Product Media Manager</a>
                        </div>
                    </details>
                </div>
            </div>

            <div class="pm-table-wrap">
                <table class="pm-table">
                    <caption class="sr-only">Product catalogue</caption>
                    <thead>
                        <tr>
                            <th class="pm-check-col"><label class="sr-only" for="select-all-products">Select all products</label><input id="select-all-products" type="checkbox"></th>
                            <th>Product</th>
                            <th>SKU / Barcode</th>
                            <th>Category</th>
                            <th>Price (EUR)</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $product)
                        @php
                            $isPublished=$product->is_active && in_array($product->status,['active','published'],true);
                            $isDraft=in_array($product->status,['draft','planned'],true);
                            $statusLabel=$isPublished?'Published':($isDraft?'Draft':'Hidden');
                            $statusClass=$isPublished?'published':($isDraft?'draft':'hidden');
                            $stock=(int)$product->stock;
                            $stockLabel=$stock<=0?'Out of Stock':($stock<=10?'Low Stock':'In Stock');
                            $stockClass=$stock<=0?'out':($stock<=10?'low':'in');
                            $rating=$product->reviews_avg_rating===null?null:(float)$product->reviews_avg_rating;
                            $filledStars=$rating===null?0:(int)round($rating);
                            $imageUrl=$product->image;
                            if($imageUrl && !preg_match('#^(https?:)?/#',$imageUrl))$imageUrl=\Illuminate\Support\Facades\Storage::url($imageUrl);
                        @endphp
                        <tr id="product-{{ $product->id }}">
                            <td class="pm-check-col"><input type="checkbox" name="products[]" value="{{ $product->id }}" aria-label="Select {{ $product->name }}"></td>
                            <td>
                                <div class="pm-product-cell">
                                    <span class="pm-product-thumb">
                                        @if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $product->name }}">@else<x-icon name="package" size="26" label="Product image unavailable" />@endif
                                    </span>
                                    <span class="pm-product-name"><strong>{{ $product->name }}</strong><small>{{ $product->brand ?: 'Emerald Rozalia collection' }}</small>@if($product->is_new)<em>Featured</em>@endif</span>
                                </div>
                            </td>
                            <td><span class="pm-code">{{ $product->sku }}</span><small class="pm-muted">Product ID #{{ $product->id }}</small></td>
                            <td><strong class="pm-category">{{ $product->category?->name ?: 'Uncategorised' }}</strong><small class="pm-muted">{{ $product->material ?: 'Product range' }}</small></td>
                            <td><strong class="pm-price">{{ $money($product->price) }}</strong>@if($product->compare_price && (float)$product->compare_price>(float)$product->price)<del>{{ $money($product->compare_price) }}</del>@endif</td>
                            <td><strong>{{ number_format($stock) }}</strong><small class="pm-stock {{ $stockClass }}">{{ $stockLabel }}</small></td>
                            <td><span class="pm-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td>
                                <span class="pm-rating">
                                    <span class="pm-stars" aria-label="{{ $rating===null?'Not rated':number_format($rating,1).' out of 5' }}">
                                        @for($star=1;$star<=5;$star++)<x-icon name="star" size="12" class="{{ $star<=$filledStars?'is-filled':'' }}" />@endfor
                                    </span>
                                    <small>{{ $rating===null?'Not rated':number_format($rating,1) }}</small>
                                </span>
                            </td>
                            <td>
                                <div class="pm-row-actions">
                                    <a href="{{ route('product',['product'=>$product->slug]) }}" title="View {{ $product->name }}" aria-label="View {{ $product->name }}"><x-icon name="eye" size="15" /></a>
                                    <a href="{{ route('admin.resource','add-product') }}" title="Open product workflow" aria-label="Open product workflow for {{ $product->name }}"><x-icon name="pencil" size="15" /></a>
                                    <button type="button" title="More product actions" aria-label="More actions for {{ $product->name }}"><x-icon name="dots" size="15" /></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="pm-empty"><x-icon name="package" size="28" /><strong>No products match these filters.</strong><a href="{{ route('admin.resource','product-manager') }}">Clear filters</a></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pm-table-footer">
                <span>Showing {{ $products->firstItem() ?: 0 }} to {{ $products->lastItem() ?: 0 }} of {{ number_format($products->total()) }} products</span>
                @if($products->hasPages())
                    <nav class="pm-pagination" aria-label="Product pages">
                        @if($products->onFirstPage())<span aria-disabled="true"><x-icon name="arrow-left" size="14" /></span>@else<a href="{{ $products->previousPageUrl() }}" aria-label="Previous page"><x-icon name="arrow-left" size="14" /></a>@endif
                        @for($page=1;$page<=$products->lastPage();$page++)
                            @if($page===1 || $page===$products->lastPage() || abs($page-$products->currentPage())<=1)
                                @if($page===$products->currentPage())<strong aria-current="page">{{ $page }}</strong>@else<a href="{{ $products->url($page) }}">{{ $page }}</a>@endif
                            @elseif(abs($page-$products->currentPage())===2)<span>…</span>@endif
                        @endfor
                        @if($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}" aria-label="Next page"><x-icon name="arrow-right" size="14" /></a>@else<span aria-disabled="true"><x-icon name="arrow-right" size="14" /></span>@endif
                    </nav>
                @endif
            </div>
        </section>

        <aside class="pm-rail">
            <section class="pm-rail-card pm-filter-card" id="product-filters">
                <div class="pm-rail-heading"><h2>Filters</h2><a href="{{ route('admin.resource','product-manager') }}">Reset</a></div>
                <form method="get" action="{{ route('admin.resource','product-manager') }}" class="pm-filter-form">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label>Search<input name="q" value="{{ $search }}" type="search" placeholder="Product name, SKU, barcode..."></label>
                    <label>Category<select name="category_id"><option value="">All Categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($categoryId===$category->id)>{{ $category->name }}</option>@endforeach</select></label>
                    <fieldset><legend>Price Range (EUR)</legend><div class="pm-price-fields"><input name="min_price" value="{{ $minPrice }}" type="number" min="0" step="0.01" placeholder="Min"><span>—</span><input name="max_price" value="{{ $maxPrice }}" type="number" min="0" step="0.01" placeholder="Max"></div></fieldset>
                    <label>Stock Status<select name="stock_status"><option value="">All Stock Statuses</option><option value="in_stock" @selected($stockStatus==='in_stock')>In Stock</option><option value="low_stock" @selected($stockStatus==='low_stock')>Low Stock</option><option value="out_of_stock" @selected($stockStatus==='out_of_stock')>Out of Stock</option></select></label>
                    <label>Product Status<select name="product_status"><option value="">All Statuses</option><option value="published" @selected($productStatus==='published')>Published</option><option value="draft" @selected($productStatus==='draft')>Draft</option><option value="hidden" @selected($productStatus==='hidden')>Hidden</option><option value="inactive" @selected($productStatus==='inactive')>Inactive</option></select></label>
                    <label>Rating<select name="rating"><option value="">All Ratings</option>@for($score=5;$score>=1;$score--)<option value="{{ $score }}_plus" @selected($rating===((string)$score.'_plus'))>{{ $score }}+ stars</option>@endfor</select></label>
                    <label class="pm-toggle-row"><span>Featured Products Only</span><input type="checkbox" name="featured" value="1" @checked($featured)><i aria-hidden="true"></i></label>
                    <button class="pm-apply-button" type="submit">Apply Filters <x-icon name="filter" size="14" /></button>
                </form>
            </section>

            <section class="pm-rail-card pm-summary-card">
                <div class="pm-rail-heading"><h2>Product Summary</h2><span>This catalogue</span></div>
                <div class="pm-summary-body">
                    <div class="pm-donut" style="--published:{{ $publishedPercent }}%;--draft:{{ $draftPercent }}%;--hidden:{{ $hiddenPercent }}%;--out:{{ $outOfStockPercent }}%"><span>{{ number_format($stats['total']) }}<small>Total</small></span></div>
                    <ul class="pm-summary-list">
                        <li><i class="published"></i><span>Published</span><strong>{{ number_format($stats['published']) }} <small>({{ $percent($stats['published']) }})</small></strong></li>
                        <li><i class="draft"></i><span>Draft</span><strong>{{ number_format($stats['draft']) }} <small>({{ $percent($stats['draft']) }})</small></strong></li>
                        <li><i class="hidden"></i><span>Hidden</span><strong>{{ number_format($stats['hidden']) }} <small>({{ $percent($stats['hidden']) }})</small></strong></li>
                        <li><i class="out"></i><span>Out of Stock</span><strong>{{ number_format($stats['out_of_stock']) }} <small>({{ $percent($stats['out_of_stock']) }})</small></strong></li>
                    </ul>
                </div>
            </section>

            <section class="pm-rail-card pm-quick-card">
                <div class="pm-rail-heading"><h2>Quick Actions</h2></div>
                <a href="{{ route('admin.resource','add-product') }}"><x-icon name="plus" size="15" /> Add New Product</a>
                <a href="{{ route('admin.bulk-upload') }}"><x-icon name="upload" size="15" /> Bulk Product Upload</a>
                <a href="{{ route('admin.media.index') }}"><x-icon name="camera" size="15" /> Product Media Manager</a>
                <a href="{{ route('admin.resource','categories') }}"><x-icon name="package" size="15" /> Manage Categories</a>
                <a href="{{ route('admin.resource','collections') }}"><x-icon name="clover" size="15" /> Manage Collections</a>
                <a href="{{ route('admin.resource','banners-sliders') }}"><x-icon name="image" size="15" /> Manage Banners / Sliders</a>
            </section>
        </aside>
    </div>

    <section class="pm-feature-strip" aria-label="Product management capabilities">
        <article><span><x-icon name="package" size="20" /></span><div><strong>Powerful Product Management</strong><small>Add, edit and manage unlimited products with ease and efficiency.</small></div></article>
        <article><span><x-icon name="camera" size="20" /></span><div><strong>Rich Media Support</strong><small>Images, videos, 360° views and virtual try-on to boost conversions.</small></div></article>
        <article><span><x-icon name="refresh" size="20" /></span><div><strong>Inventory Intelligence</strong><small>Real-time stock tracking with low stock and out of stock alerts.</small></div></article>
        <article><span><x-icon name="star" size="20" /></span><div><strong>SEO &amp; Visibility</strong><small>Optimise product SEO, content and visibility across all channels.</small></div></article>
        <article><span><x-icon name="chart" size="20" /></span><div><strong>Data Driven Decisions</strong><small>Track performance, ratings and sales to grow your business.</small></div></article>
    </section>
    <footer class="pm-footer">
        <span>© {{ now()->format('Y') }} Emerald Rozalia Ltd. All rights reserved.</span>
        <span><x-icon name="phone" size="13" /> {{ config('app.brand_contact.whatsapp') ?: 'Limerick, Ireland' }}</span>
        <span><x-icon name="mail" size="13" /> {{ config('app.brand_contact.email') ?: 'Emerald Rozalia support' }}</span>
        <span><x-icon name="globe" size="13" /> {{ config('app.brand_contact.website') ?: 'emeraldrozalia.com' }}</span>
        <span><x-icon name="globe" size="13" /> {{ config('app.brand_contact.location') }}</span>
    </footer>
</div>
@endsection
