@extends('layouts.admin')

@section('title','Product Manager')

@section('content')

@push('styles')
<style>
.pm-category-browser{margin:0 0 18px;padding:16px;background:#fff;border:1px solid #dce5df;border-radius:12px}
.pm-category-browser-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:12px;flex-wrap:wrap}
.pm-category-browser-head h2{margin:0;font-size:18px}
.pm-category-browser-head p{margin:4px 0 0;color:#6a786f;font-size:12px}
.pm-category-browser-head>a{font-weight:700;color:#087c3f;text-decoration:none}
.pm-category-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:8px}
.pm-category-card{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:54px;padding:10px 12px;border:1px solid #d7e0da;border-radius:9px;background:#fbfdfb;color:#183024;text-decoration:none}
.pm-category-card span{font-weight:700}
.pm-category-card strong{display:inline-flex;min-width:30px;height:30px;padding:0 8px;align-items:center;justify-content:center;border-radius:999px;background:#eaf5ee;color:#087c3f}
.pm-category-card:hover,.pm-category-card.is-active{border-color:#087c3f;background:#eef8f1}
.pm-category-card.is-active span{color:#087c3f}
.pm-essential-bar{display:flex;align-items:end;gap:8px;flex-wrap:wrap;padding:12px 14px;border-top:1px solid #edf1ee;border-bottom:1px solid #edf1ee;background:#fbfcfb}
.pm-essential-bar label{display:grid;gap:3px;min-width:150px;flex:1 1 165px}
.pm-essential-bar label>span{font-size:10px;font-weight:800;color:#66756c;text-transform:uppercase;letter-spacing:.03em}
.pm-essential-bar select{height:38px;width:100%;min-width:0;border:1px solid #d4ddd7;border-radius:7px;background:#fff;color:#17231b;padding:0 30px 0 10px;font-weight:600}
.pm-essential-reset{height:38px;display:inline-flex;align-items:center;padding:0 12px;border:1px solid #d4ddd7;border-radius:7px;background:#fff;color:#17231b;text-decoration:none;font-weight:700}
.pm-toolbar-direct{display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 11px;border:1px solid #d5ded8;border-radius:7px;background:#fff;color:#17231b;text-decoration:none;font-weight:700;white-space:nowrap}
.pm-toolbar-direct.primary{background:#087c3f;border-color:#087c3f;color:#fff}
.pm-row-actions{flex-wrap:wrap}
.pm-row-actions .pm-text-action{width:auto!important;padding:0 7px!important;font-size:11px;font-weight:700}
.pm-row-actions form{display:inline-flex;margin:0}
.pm-row-actions .pm-action-dropdown{display:inline-flex;align-items:center;justify-content:center;gap:4px;width:auto;min-width:68px;height:30px;padding:0 8px;font:inherit;font-size:11px;font-weight:700;white-space:nowrap}
.pm-row-actions.has-dropdown>a,.pm-row-actions.has-dropdown>form{display:none}
.pm-action-dropdown .ui-icon{transform:rotate(90deg)}
.pm-row-actions .danger{color:#a12620!important;border-color:#efc9c7!important}
@media(max-width:900px){.pm-essential-bar label{flex:1 1 45%}}
.pm-hierarchy-filter{margin:0 0 18px;padding:16px;background:#fff;border:1px solid #dce5df;border-radius:12px}
.pm-hierarchy-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px}
.pm-hierarchy-head h2{margin:0;font-size:18px}
.pm-hierarchy-head p{margin:4px 0 0;color:#6a786f;font-size:12px}
.pm-hierarchy-head>a{font-weight:700;color:#087c3f;text-decoration:none}
.pm-hierarchy-form{display:grid;grid-template-columns:repeat(4,minmax(170px,1fr));gap:10px;align-items:end}
.pm-hierarchy-form label{display:grid;gap:4px;min-width:0}
.pm-hierarchy-form label>span{font-size:10px;font-weight:800;color:#66756c;text-transform:uppercase;letter-spacing:.03em}
.pm-hierarchy-form select{width:100%;height:40px;min-width:0;border:1px solid #d5ded8;border-radius:7px;background:#fff;color:#17231b;padding:0 30px 0 10px;font-weight:600}
.pm-hierarchy-form input[type=date]{width:100%;height:40px;min-width:0;border:1px solid #d5ded8;border-radius:7px;background:#fff;color:#17231b;padding:0 10px;font:inherit;font-weight:600}
.pm-hierarchy-apply{height:40px;border:0;border-radius:7px;background:#087c3f;color:#fff;font-weight:800;display:inline-flex;align-items:center;justify-content:center;gap:7px;cursor:pointer}
@media(max-width:1300px){.pm-hierarchy-form{grid-template-columns:repeat(3,minmax(160px,1fr))}}
@media(max-width:900px){.pm-hierarchy-form{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.pm-hierarchy-form{grid-template-columns:1fr}}
.pm-category-dropdowns{display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap}
.pm-category-dropdowns label{display:grid;gap:3px}
.pm-category-dropdowns label>span{font-size:10px;font-weight:800;color:#66756c;text-transform:uppercase;letter-spacing:.03em}
.pm-category-dropdowns select{height:38px;min-width:165px;max-width:220px;border:1px solid #d5ded8;border-radius:7px;background:#fff;color:#17231b;padding:0 30px 0 10px;font-weight:600}
@media(max-width:900px){.pm-category-dropdowns{width:100%}.pm-category-dropdowns label{flex:1 1 180px}.pm-category-dropdowns select{width:100%;max-width:none}}
</style>
@endpush

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
    $activeRootCategory = collect($rootCategorySummaries)->firstWhere('id', $rootCategoryId);
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

    <section class="pm-category-browser" aria-label="Products by main category">
        <div class="pm-category-browser-head">
            <div>
                <h2>Products by Category</h2>
                <p>View every added product grouped by its main category, then add, edit, publish or delete within that category.</p>
            </div>
            @if($activeRootCategory)
                <a href="{{ request()->fullUrlWithQuery(['root_category_id'=>null,'category_id'=>null,'page'=>null]) }}">Show all products</a>
            @endif
        </div>
        <div class="pm-category-grid">
            <a class="pm-category-card {{ $rootCategoryId===0?'is-active':'' }}"
               href="{{ request()->fullUrlWithQuery(['root_category_id'=>null,'category_id'=>null,'page'=>null]) }}">
                <span>All Products</span>
                <strong>{{ number_format($stats['total']) }}</strong>
            </a>
            @foreach($rootCategorySummaries as $categorySummary)
                <a class="pm-category-card {{ $rootCategoryId===$categorySummary['id']?'is-active':'' }}"
                   href="{{ request()->fullUrlWithQuery(['root_category_id'=>$categorySummary['id'],'category_id'=>null,'page'=>null]) }}">
                    <span>{{ $categorySummary['name'] }}</span>
                    <strong>{{ number_format($categorySummary['count']) }}</strong>
                </a>
            @endforeach
        </div>
    </section>

    @php
        $activeRootModel = $rootCategoryId ? $categories->firstWhere('id', $rootCategoryId) : null;
        $activeTaxonomy = strtolower((string) ($activeRootModel?->taxonomy_type ?? $activeRootModel?->slug ?? ''));
    @endphp
    <section class="pm-hierarchy-filter" aria-label="Catalogue hierarchy filters">
        <div class="pm-hierarchy-head">
            <div>
                <h2>Find Products</h2>
                <p>Main Category → Subcategory → Country → County/Region → Club/National Team → Collection → Status → Stock Status</p>
            </div>
            <a href="{{ route('admin.resource','product-manager') }}">Clear all filters</a>
        </div>
        <form method="get" action="{{ route('admin.resource','product-manager') }}" class="pm-hierarchy-form" data-product-hierarchy-filter>
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if($search!=='')<input type="hidden" name="q" value="{{ $search }}">@endif

            <label>
                <span>1. Main Category</span>
                <select name="root_category_id" data-h-main>
                    <option value="">All Main Categories</option>
                    @foreach($rootCategorySummaries as $categorySummary)
                        @php($rootModel = $categories->firstWhere('id', $categorySummary['id']))
                        <option value="{{ $categorySummary['id'] }}"
                                data-taxonomy="{{ strtolower((string)($rootModel?->taxonomy_type ?? $rootModel?->slug ?? '')) }}"
                                @selected($rootCategoryId===$categorySummary['id'])>
                            {{ $categorySummary['name'] }} ({{ number_format($categorySummary['count']) }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>2. Subcategory</span>
                <select name="category_id" data-h-sub>
                    <option value="">All Subcategories</option>
                    @foreach($categories as $category)
                        @php($rootForOption = $categoryRootMap[$category->id] ?? null)
                        @if($category->parent_id && $rootForOption)
                            <option value="{{ $category->id }}"
                                    data-root="{{ $rootForOption['id'] }}"
                                    @selected($categoryId===$category->id)>
                                {{ $category->name }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </label>

            <label>
                <span>3. Country</span>
                <select name="catalog_country_id" data-h-country>
                    <option value="">All Countries</option>
                    @foreach($catalogCountries as $country)
                        <option value="{{ $country->id }}" data-code="{{ $country->code }}" @selected($countryId===$country->id)>
                            {{ $country->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>4. County / Region</span>
                <select name="catalog_county_code" data-h-county>
                    <option value="">All Counties / Regions</option>
                    @foreach($catalogCounties as $county)
                        <option value="{{ $county->code }}"
                                data-country="{{ $county->catalog_country_id }}"
                                @selected($countyCode===$county->code)>
                            {{ $county->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>5. Club / National Team</span>
                <select name="catalog_club_id" data-h-club>
                    <option value="">All Clubs / National Teams</option>
                    @foreach($catalogClubs as $club)
                        @php($clubOrgs = $club->organizations->pluck('taxonomy_type')->push($club->governing_body)->filter()->unique()->implode(','))
                        <option value="{{ $club->id }}"
                                data-country="{{ $club->catalog_country_id }}"
                                data-county="{{ strtoupper((string)$club->catalog_county_code) }}"
                                data-orgs="{{ $clubOrgs }}"
                                @selected($clubId===$club->id)>
                            {{ $club->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>6. Collection</span>
                <select name="collection_id" data-h-collection>
                    <option value="">All Collections</option>
                    @foreach($collections as $collection)
                        <option value="{{ $collection->id }}"
                                data-root="{{ (int)($collection->main_category_id ?? 0) }}"
                                @selected($collectionId===$collection->id)>
                            {{ $collection->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>7. Published / Hidden / Draft</span>
                <select name="product_status">
                    <option value="">All Statuses</option>
                    <option value="published" @selected($productStatus==='published')>Published</option>
                    <option value="hidden" @selected($productStatus==='hidden')>Hidden</option>
                    <option value="draft" @selected($productStatus==='draft')>Draft</option>
                    <option value="inactive" @selected($productStatus==='inactive')>Inactive</option>
                </select>
            </label>

            <label>
                <span>8. Stock Status</span>
                <select name="stock_status">
                    <option value="">All Stock Statuses</option>
                    <option value="in_stock" @selected($stockStatus==='in_stock')>In Stock</option>
                    <option value="low_stock" @selected($stockStatus==='low_stock')>Low Stock</option>
                    <option value="out_of_stock" @selected($stockStatus==='out_of_stock')>Out of Stock</option>
                </select>
            </label>

            <label>
                <span>From</span>
                <input type="date" name="from" value="{{ $from }}" max="{{ $to }}" autocomplete="off">
            </label>

            <label>
                <span>To</span>
                <input type="date" name="to" value="{{ $to }}" min="{{ $from }}" autocomplete="off">
            </label>

            <button class="pm-hierarchy-apply" type="submit">
                <x-icon name="filter" size="15" /> Find Products
            </button>
        </form>
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
                        @if($from !== '')<input type="hidden" name="from" value="{{ $from }}">@endif
                        @if($to !== '')<input type="hidden" name="to" value="{{ $to }}">@endif
                        <label class="sr-only" for="product-manager-search">Search products</label>
                        <input id="product-manager-search" name="q" value="{{ $search }}" type="search" placeholder="Search by name, SKU, barcode...">
                        <button type="submit" aria-label="Search products"><x-icon name="search" size="16" /></button>
                    </form>
                    <a class="pm-toolbar-direct primary" href="{{ route('admin.resource','add-product') }}">+ Add Product</a>
                    <a class="pm-toolbar-direct" target="_blank" href="{{ route('admin.product-manager.print', request()->query()) }}">Print</a>
                    <a class="pm-toolbar-direct" href="{{ route('admin.product-manager.download', request()->query()) }}">Download CSV</a>

                    @if($tab !== 'trash')
                        <div class="pm-publish-controls" data-product-bulk-publish>
                            <label class="sr-only" for="product-publish-action">Bulk product publishing action</label>
                            <select id="product-publish-action" data-publish-action>
                                <option value="">Bulk Actions</option>
                                <option value="publish">Publish selected</option>
                                <option value="unpublish">Hide / Remove selected</option>
                            </select>
                            <button type="button" data-publish-selected disabled>Apply <span data-publish-selected-count></span></button>
                        </div>
                    @endif
                    @if($tab !== 'trash')
                        <details class="pm-delete-menu" data-product-bulk-delete>
                            <summary>Delete <span aria-hidden="true">▾</span></summary>
                            <div>
                                <button type="button" data-delete-selected disabled>Delete Selected <span data-selected-count>(0)</span></button>
                                <button type="button" class="pm-delete-all" data-delete-all data-total-products="{{ $stats['total'] }}">Delete All Products</button>
                            </div>
                        </details>
                    @endif
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

            <form class="pm-essential-bar" method="get" action="{{ route('admin.product-manager.index') }}" data-pm-essential-filter>
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if($search!=='')<input type="hidden" name="q" value="{{ $search }}">@endif
                @if($from !== '')<input type="hidden" name="from" value="{{ $from }}">@endif
                @if($to !== '')<input type="hidden" name="to" value="{{ $to }}">@endif
                <label>
                    <span>Main Category</span>
                    <select name="root_category_id" data-essential-main>
                        <option value="">All Main Categories</option>
                        @foreach($rootCategorySummaries as $categorySummary)
                            <option value="{{ $categorySummary['id'] }}" @selected($rootCategoryId===$categorySummary['id'])>{{ $categorySummary['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Subcategory</span>
                    <select name="category_id" data-essential-sub>
                        <option value="">{{ $rootCategoryId ? 'All Subcategories' : 'Select Main Category First' }}</option>
                        @foreach($categories as $category)
                            @php($rootForEssential = $categoryRootMap[$category->id] ?? null)
                            @if($category->parent_id && $rootForEssential)
                                <option value="{{ $category->id }}"
                                        data-root="{{ $rootForEssential['id'] }}"
                                        @selected($categoryId===$category->id)>
                                    {{ $category->name }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Published / Hidden / Draft</span>
                    <select name="product_status" data-essential-auto>
                        <option value="">All Statuses</option>
                        <option value="published" @selected($productStatus==='published')>Published</option>
                        <option value="hidden" @selected($productStatus==='hidden')>Hidden</option>
                        <option value="draft" @selected($productStatus==='draft')>Draft</option>
                    </select>
                </label>
                <label>
                    <span>Stock Status</span>
                    <select name="stock_status" data-essential-auto>
                        <option value="">All Stock Statuses</option>
                        <option value="in_stock" @selected($stockStatus==='in_stock')>In Stock</option>
                        <option value="low_stock" @selected($stockStatus==='low_stock')>Low Stock</option>
                        <option value="out_of_stock" @selected($stockStatus==='out_of_stock')>Out of Stock</option>
                    </select>
                </label>
                <button class="pm-hierarchy-apply" type="submit"><x-icon name="filter" size="14" /> Apply</button>
                <a class="pm-essential-reset" href="{{ route('admin.product-manager.index') }}">Reset</a>
            </form>

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
                    @if($products->isEmpty())
                        <tr><td colspan="9" class="pm-empty"><x-icon name="package" size="28" /><strong>No products match these filters.</strong><a href="{{ route('admin.resource','product-manager') }}">Clear filters</a></td></tr>
                    @endif
                    @foreach($products as $product)
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
                            $imageUrl=$product->image ?: optional($product->previewMedia->first())->path;
                            if($imageUrl && !preg_match('#^(https?:)?/#',$imageUrl))$imageUrl=\Illuminate\Support\Facades\Storage::url($imageUrl);
                        @endphp
                        <tr id="product-{{ $product->id }}" data-product-published="{{ $isPublished ? '1' : '0' }}">
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
                            @php($rootCategory = $categoryRootMap[$product->category_id] ?? null)
                            <td>
                                <strong class="pm-category">{{ $rootCategory['name'] ?? ($product->category?->name ?: 'Uncategorised') }}</strong>
                                <small class="pm-muted">
                                    @if($rootCategory && $product->category && $product->category->name !== $rootCategory['name'])
                                        {{ $product->category->name }}
                                    @else
                                        {{ $product->material ?: 'Product range' }}
                                    @endif
                                </small>
                            </td>
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
                                    <a class="pm-text-action" href="{{ route('admin.product.edit', $product) }}" title="Edit {{ $product->name }}">Edit</a>
                                    <button class="pm-action-dropdown" type="button" title="More product actions" aria-label="More actions for {{ $product->name }}" aria-haspopup="menu" aria-expanded="false">
                                        <span>Actions</span><x-icon name="chevron-right" size="12" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
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
                    @if($rootCategoryId>0)<input type="hidden" name="root_category_id" value="{{ $rootCategoryId }}">@endif
                    @if($from !== '')<input type="hidden" name="from" value="{{ $from }}">@endif
                    @if($to !== '')<input type="hidden" name="to" value="{{ $to }}">@endif
                    <label>Search<input name="q" value="{{ $search }}" type="search" placeholder="Product name, SKU, barcode..."></label>
                    <label>Subcategory / Product Category
                        <select name="category_id">
                            <option value="">{{ $activeRootCategory ? 'All '.$activeRootCategory['name'].' products' : 'All Categories' }}</option>
                            @foreach($categories as $category)
                                @php($rootForOption = $categoryRootMap[$category->id] ?? null)
                                @if(!$rootCategoryId || (($rootForOption['id'] ?? 0) === $rootCategoryId))
                                    <option value="{{ $category->id }}" @selected($categoryId===$category->id)>
                                        {{ $rootForOption && $category->name !== $rootForOption['name'] ? $rootForOption['name'].' → '.$category->name : $category->name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </label>
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

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-product-hierarchy-filter]');
    if (!form) return;

    const main = form.querySelector('[data-h-main]');
    const sub = form.querySelector('[data-h-sub]');
    const country = form.querySelector('[data-h-country]');
    const county = form.querySelector('[data-h-county]');
    const club = form.querySelector('[data-h-club]');
    const collection = form.querySelector('[data-h-collection]');

    const filterOptions = (select, predicate, placeholder) => {
        if (!select) return;
        const current = select.value;
        [...select.options].forEach((option, index) => {
            if (index === 0) return;
            option.hidden = !predicate(option);
        });
        const selected = select.selectedOptions[0];
        if (selected?.hidden) select.value = '';
        if (select.options[0]) select.options[0].textContent = placeholder;
        if (current && [...select.options].some(o => o.value === current && !o.hidden)) {
            select.value = current;
        }
    };

    const sync = (changed = '') => {
        const rootId = main?.value || '';
        const taxonomy = main?.selectedOptions[0]?.dataset.taxonomy || '';
        const countryId = country?.value || '';
        const countyCode = county?.value || '';

        if (changed === 'main' && sub) sub.value = '';
        if ((changed === 'main' || changed === 'country') && county) county.value = '';
        if (['main','country','county'].includes(changed) && club) club.value = '';
        if (changed === 'main' && collection) collection.value = '';

        filterOptions(
            sub,
            option => !rootId || option.dataset.root === rootId,
            rootId ? 'All Subcategories' : 'Select main category first'
        );

        filterOptions(
            county,
            option => !countryId || option.dataset.country === countryId,
            countryId ? 'All Counties / Regions' : 'Select country first'
        );

        filterOptions(
            club,
            option => {
                if (countryId && option.dataset.country !== countryId) return false;
                if (taxonomy) {
                    const orgs = (option.dataset.orgs || '').split(',').filter(Boolean);
                    if (orgs.length && !orgs.includes(taxonomy)) return false;
                }
                if (countyCode) return option.dataset.county === countyCode;
                return !countryId || option.dataset.county === '' || taxonomy !== 'fifa';
            },
            countryId
                ? (countyCode ? 'All matching clubs' : (taxonomy === 'fifa' ? 'National team / country-wide club' : 'All clubs'))
                : 'Select country first'
        );

        filterOptions(
            collection,
            option => !rootId || option.dataset.root === '0' || option.dataset.root === rootId,
            rootId ? 'All matching collections' : 'All Collections'
        );
    };

    main?.addEventListener('change', () => sync('main'));
    country?.addEventListener('change', () => sync('country'));
    county?.addEventListener('change', () => sync('county'));
    sync();

    const essential = document.querySelector('[data-pm-essential-filter]');
    if (essential) {
        const essentialMain = essential.querySelector('[data-essential-main]');
        const essentialSub = essential.querySelector('[data-essential-sub]');

        const syncEssentialSubcategories = (clearSelection = false) => {
            if (!essentialMain || !essentialSub) return;
            const rootId = essentialMain.value || '';
            if (clearSelection) essentialSub.value = '';

            [...essentialSub.options].forEach((option, index) => {
                if (index === 0) return;
                option.hidden = !rootId || option.dataset.root !== rootId;
            });

            essentialSub.disabled = !rootId;
            essentialSub.options[0].textContent = rootId ? 'All Subcategories' : 'Select Main Category First';

            const selected = essentialSub.selectedOptions[0];
            if (selected?.hidden) essentialSub.value = '';
        };

        essentialMain?.addEventListener('change', () => syncEssentialSubcategories(true));
        syncEssentialSubcategories(false);
    }
})();
</script>
@endpush

@include('admin.partials.product-manager-delete-actions')
@endsection
