@extends('layouts.admin')

@section('title', isset($product) && $product ? 'Edit Product' : 'Add New Product')

@php
    $isEditing = isset($product) && $product;
    $productMeta = $product?->product_metadata ?? [];
    $classification = is_array($productMeta['catalog_classification'] ?? null) ? $productMeta['catalog_classification'] : [];
    $selectedCategoryId = old('category_id', $product?->category_id ?? request()->integer('category_id'));
    $categoryById = $categories->keyBy('id');
    $selectedRootId = old('category_root_id', $classification['category_root_id'] ?? null);
    if (! $selectedRootId && $selectedCategoryId && $categoryById->has((int) $selectedCategoryId)) {
        $cursor = $categoryById->get((int) $selectedCategoryId);
        while ($cursor?->parent_id && $categoryById->has((int) $cursor->parent_id)) {
            $cursor = $categoryById->get((int) $cursor->parent_id);
        }
        $selectedRootId = $cursor?->id;
    }
    $selectedCountryId = old('catalog_country_id', $classification['catalog_country_id'] ?? '');
    $selectedCountyCode = old('catalog_county_code', $classification['catalog_county_code'] ?? '');
    $selectedClubId = old('catalog_club_id', $classification['catalog_club_id'] ?? '');
    $selectedStyle = old('catalog_style', $classification['style'] ?? '');
    $selectedChannels = old('channels', $productMeta['channels'] ?? ['website', 'franchise', 'franchise_retail', 'corporate_bulk']);
    $selectedOrderCategories = old('order_categories', $productMeta['order_categories'] ?? ['online', 'bulk', 'franchise', 'franchise_retail']);
    $selectedChannels = is_array($selectedChannels) ? $selectedChannels : [];
    $selectedOrderCategories = is_array($selectedOrderCategories) ? $selectedOrderCategories : [];
    $publishDate = old('publish_date', $product?->published_at?->format('Y-m-d\TH:i') ?: now()->format('Y-m-d\TH:i'));
    $productType = old('product_type', $productMeta['product_type'] ?? 'simple');
    $taxClass = old('tax_class', $productMeta['tax_class'] ?? 'standard');
    $publishedWebsite = filter_var(old('published_website', $productMeta['published_website'] ?? false), FILTER_VALIDATE_BOOLEAN);
    $availableForSale = filter_var(old('available_for_sale', $productMeta['available_for_sale'] ?? false), FILTER_VALIDATE_BOOLEAN);
    $newArrival = filter_var(old('is_new_arrival', $product?->is_new ?? old('featured', false)), FILTER_VALIDATE_BOOLEAN);
    $selectedCollectionIds = collect(old('collection_ids', $product?->collections?->pluck('id')->all() ?? []))
        ->map(static fn ($id): string => (string) $id)
        ->all();
    $placementCollections = $collections->reject(static fn ($collection): bool => $collection->slug === 'new-arrivals')->values();
@endphp

@section('content')
<div class="ap-page">
    <div class="ap-page-heading">
        <div>
            <nav class="ap-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Home</a>
                <x-icon name="chevron-right" size="12" />
                <span>Website &amp; Products</span>
                <x-icon name="chevron-right" size="12" />
                <a href="{{ route('admin.resource', 'product-manager') }}">Product Manager</a>
                <x-icon name="chevron-right" size="12" />
                <strong>{{ $isEditing ? 'Edit Product' : 'Add Product' }}</strong>
            </nav>
            <p class="ap-eyebrow">ADMIN / OPERATIONS</p>
            <h1>{{ $isEditing ? 'Edit Product' : 'Add New Product' }}</h1>
            <p class="ap-intro">{{ $isEditing ? 'Update product details, visibility and sales-channel settings.' : 'Create a new product and publish it to your website and sales channels.' }}</p>
        </div>
        <div class="ap-date-card">
            <x-icon name="clock" size="22" />
            <div>
                <span>Today</span>
                <strong>{{ now()->format('l, j F Y') }}</strong>
                <time datetime="{{ now()->toIso8601String() }}">{{ now()->format('H:i') }}</time>
            </div>
        </div>
    </div>

    <form action="{{ $isEditing ? route('admin.product.update', $product) : route('admin.add-product.store') }}" method="post" class="ap-form">
        @csrf
        @if($isEditing) @method('PUT') @endif

        <ol class="ap-stepper" aria-label="Product creation steps">
            <li class="is-active"><span class="ap-step-number">1</span><span><strong>Basic Information</strong><small>Product details &amp; pricing</small></span></li>
            <li><span class="ap-step-number">2</span><span><strong>Media &amp; Gallery</strong><small>Images, videos &amp; 360°</small></span></li>
            <li><span class="ap-step-number">3</span><span><strong>Variants &amp; Inventory</strong><small>Options, SKU &amp; stock</small></span></li>
            <li><span class="ap-step-number">4</span><span><strong>SEO &amp; Content</strong><small>Description &amp; metadata</small></span></li>
            <li><span class="ap-step-number">5</span><span><strong>Publish &amp; Visibility</strong><small>Channels &amp; status</small></span></li>
        </ol>

        <div class="ap-actionbar">
            <div></div>
            <div class="ap-actions">
                <a class="ap-button ap-button-muted" href="{{ route('admin.resource', 'product-manager') }}">Cancel</a>
                <button class="ap-button ap-button-outline" type="submit" name="save_action" value="draft"><x-icon name="download" size="15" /> Save Draft</button>
                <button class="ap-button ap-button-primary" type="submit" name="save_action" value="media">Next: Media &amp; Gallery <x-icon name="arrow-right" size="16" /></button>
            </div>
        </div>

        <div class="ap-layout">
            <div class="ap-main-column">
                <section class="ap-panel">
                    <div class="ap-panel-heading">
                        <div><h2>Basic Information</h2><p>Enter the core details of your product.</p></div>
                        <x-icon name="package" size="22" />
                    </div>

                    <div class="ap-details-grid">
                        <div class="ap-field-column">
                            <label class="ap-field ap-field-wide">
                                <span>Product Name <em>*</em></span>
                                <input type="text" name="name" value="{{ old('name', $product?->name) }}" placeholder="Emerald Signature Cap" required>
                                @error('name')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                            <label class="ap-field ap-field-wide">
                                <span>Short Description <em>*</em></span>
                                <textarea name="short_description" rows="3" placeholder="Premium quality cap with embroidered Emerald Rozalia logo." required>{{ old('short_description', $productMeta['short_description'] ?? null) }}</textarea>
                                @error('short_description')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                            <label class="ap-field ap-field-wide">
                                <span>Short Name / Slug <em>*</em></span>
                                <input type="text" name="slug" value="{{ old('slug', $product?->slug) }}" placeholder="emerald-signature-cap">
                                <small class="ap-field-help">URL: <x-icon name="globe" size="12" /> https://www.emeraldrozalia.ie/product/{{ old('slug', 'your-product-slug') }}</small>
                                @error('slug')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                            <label class="ap-field ap-field-wide">
                                <span>SKU <small>(Auto)</small></span>
                                <div class="ap-input-with-icon"><input type="text" name="sku" value="{{ old('sku', $product?->sku) }}" placeholder="Auto-generated on save"><x-icon name="tag" size="17" /></div>
                                <small class="ap-field-help">Leave blank to generate automatically as ER-[category]-[family]-[product ID]. You can still enter your own SKU.</small>
                                @error('sku')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                            <section class="ap-inline-section ap-field-wide"
                                data-product-classification
                                data-club-options-url="{{ route('admin.categories.clubs.options') }}">
                                <div class="ap-inline-heading">
                                    <h3>Category &amp; Subcategory</h3>
                                    <span>Choose the main category, product family and optional catalogue filters.</span>
                                </div>

                                <div class="ap-field-row">
                                    <label class="ap-field ap-field-wide">
                                        <span>Category <em>*</em></span>
                                        <select name="category_root_id" data-category-root required>
                                            <option value="">Select category</option>
                                            @foreach($categoryRoots as $root)
                                                <option value="{{ $root->id }}"
                                                    data-taxonomy="{{ strtolower((string) ($root->taxonomy_type ?: $root->slug)) }}"
                                                    @selected((string) $selectedRootId === (string) $root->id)>
                                                    {{ $root->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="ap-field-help">For GAA / English / UEFA / FIFA the required order is Country → County → Club Name → Subcategory.</small>
                                        @error('category_root_id')<small class="ap-field-error">{{ $message }}</small>@enderror
                                    </label>
                                </div>

                                <div class="ap-field-row ap-field-row-three">
                                    <label class="ap-field" data-catalog-country-field>
                                        <span data-catalog-country-label>Country</span>
                                        <select name="catalog_country_id" data-catalog-country>
                                            <option value="">All / not applicable</option>
                                            @foreach($catalogCountries as $country)
                                                <option value="{{ $country->id }}" data-code="{{ $country->code }}" @selected((string) $selectedCountryId === (string) $country->id)>{{ $country->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('catalog_country_id')<small class="ap-field-error">{{ $message }}</small>@enderror
                                    </label>

                                    <label class="ap-field" data-catalog-county-field>
                                        <span data-catalog-county-label>County</span>
                                        <select name="catalog_county_code" data-catalog-county>
                                            <option value="">Select country first</option>
                                        </select>
                                        @error('catalog_county_code')<small class="ap-field-error">{{ $message }}</small>@enderror
                                    </label>
                                </div>

                                <label class="ap-field ap-field-wide" data-catalog-club-field>
                                    <span data-catalog-club-label>Club / City / Town</span>
                                    <select name="catalog_club_id" data-catalog-club>
                                        <option value="">Select category and country first</option>
                                        @foreach($catalogClubs as $club)
                                            <option value="{{ $club->id }}"
                                                data-country="{{ $club->catalog_country_id }}"
                                                data-county="{{ strtoupper((string) $club->catalog_county_code) }}"
                                                data-body="{{ strtolower((string) $club->governing_body) }}"
                                                @selected((string) $selectedClubId === (string) $club->id)>
                                                {{ $club->name }}@if($club->country) — {{ $club->country->name }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="ap-field-help" data-catalog-club-help>
                                        GAA, English, UEFA and FIFA products require a matching club / city / town. 
                                        <a href="{{ route('admin.categories.clubs') }}" target="_blank" rel="noopener">Manage Club Master</a>
                                    </small>
                                    @error('catalog_club_id')<small class="ap-field-error">{{ $message }}</small>@enderror
                                </label>

                                <label class="ap-field ap-field-wide">
                                    <span>Subcategory <em>*</em></span>
                                    <select name="category_id" data-subcategory required>
                                        <option value="">Select category first</option>
                                        @foreach($subcategoryOptionsByRoot as $rootId => $options)
                                            @foreach($options as $option)
                                                <option value="{{ $option['id'] }}"
                                                    data-root="{{ $rootId }}"
                                                    data-product-type="{{ strtolower((string) ($option['product_type'] ?? '')) }}"
                                                    @selected((string) $selectedCategoryId === (string) $option['id'])>
                                                    {{ $option['label'] }}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                    <small class="ap-field-help">Subcategory is selected after the geographic/club path for GAA, English, UEFA and FIFA. Canonical product families are Caps, Hats and Beanie.</small>
                                    @error('category_id')<small class="ap-field-error">{{ $message }}</small>@enderror
                                </label>

                                <label class="ap-field ap-field-wide">
                                    <span>Style / Range</span>
                                    <select name="catalog_style">
                                        <option value="">Select style</option>
                                        @foreach($catalogStyles as $styleValue => $styleLabel)
                                            <option value="{{ $styleValue }}" @selected((string) $selectedStyle === (string) $styleValue)>{{ $styleLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('catalog_style')<small class="ap-field-error">{{ $message }}</small>@enderror
                                </label>
                            </section>

                            <label class="ap-field ap-field-wide">
                                <span>Brand / Line</span>
                                <input type="text" name="brand" value="{{ old('brand', $product?->brand ?: 'Emerald Rozalia') }}" placeholder="Emerald Rozalia">
                            </label>

                            <label class="ap-field ap-field-wide">
                                <span>Tags</span>
                                <input type="text" name="tags" value="{{ old('tags', implode(', ', $productMeta['tags'] ?? [])) }}" placeholder="Cap, Signature, Green, Premium">
                                <small class="ap-field-help">Separate tags with commas.</small>
                            </label>

                            <fieldset class="ap-fieldset ap-field-wide">
                                <legend>Product Type</legend>
                                <div class="ap-radio-row">
                                    <label><input type="radio" name="product_type" value="simple" @checked($productType === 'simple')> <span>Simple</span></label>
                                    <label><input type="radio" name="product_type" value="variable" @checked($productType === 'variable')> <span>Variable (Variants)</span></label>
                                    <label><input type="radio" name="product_type" value="bundle" @checked($productType === 'bundle')> <span>Bundle / Kit</span></label>
                                </div>
                                @error('product_type')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </fieldset>

                            <div class="ap-field-row">
                                <label class="ap-field">
                                    <span>Tax Class</span>
                                    <select name="tax_class">
                                        <option value="standard" @selected($taxClass === 'standard')>Standard Rate (23%)</option>
                                        <option value="reduced" @selected($taxClass === 'reduced')>Reduced Rate</option>
                                        <option value="zero" @selected($taxClass === 'zero')>Zero Rate</option>
                                    </select>
                                </label>
                                <label class="ap-field">
                                    <span>HS Code <small>(Auto)</small></span>
                                    <input type="text" name="hs_code" value="{{ old('hs_code', $product?->hs_code) }}" placeholder="Auto-generated from product family" data-auto-hs-code>
                                    <small class="ap-field-help">Caps, Hats and Beanie default to HS 650500. Edit this value when the product construction requires a different customs classification.</small>
                                    @error('hs_code')<small class="ap-field-error">{{ $message }}</small>@enderror
                                </label>
                            </div>

                            <div class="ap-field-row ap-field-row-three">
                                <label class="ap-field"><span>Weight (kg)</span><input type="number" name="weight" value="{{ old('weight', $product?->weight) }}" min="0" step="0.001" placeholder="0.25"></label>
                                <label class="ap-field"><span>Length (cm)</span><input type="number" name="length" value="{{ old('length', $productMeta['dimensions']['length'] ?? null) }}" min="0" step="0.01" placeholder="22"></label>
                                <label class="ap-field"><span>Width (cm)</span><input type="number" name="width" value="{{ old('width', $productMeta['dimensions']['width'] ?? null) }}" min="0" step="0.01" placeholder="18"></label>
                            </div>
                            <label class="ap-field ap-field-small"><span>Height (cm)</span><input type="number" name="height" value="{{ old('height', $productMeta['dimensions']['height'] ?? null) }}" min="0" step="0.01" placeholder="12"></label>
                        </div>

                        <div class="ap-field-column">
                            <label class="ap-field ap-field-wide">
                                <span>Full Description <em>*</em></span>
                                <div class="ap-editor">
                                    <div class="ap-editor-toolbar" aria-label="Description formatting tools">
                                        <button type="button" aria-label="Bold"><strong>B</strong></button>
                                        <button type="button" aria-label="Italic"><em>I</em></button>
                                        <button type="button" aria-label="Underline"><u>U</u></button>
                                        <span></span>
                                        <button type="button" aria-label="Bulleted list"><x-icon name="file-text" size="14" /></button>
                                        <button type="button" aria-label="Insert image"><x-icon name="image" size="14" /></button>
                                        <button type="button" aria-label="Insert link"><x-icon name="globe" size="14" /></button>
                                    </div>
                                    <textarea name="description" rows="11" placeholder="Describe the product, materials, fit and care instructions." required>{{ old('description', $product?->description) }}</textarea>
                                </div>
                                @error('description')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                                <section class="ap-inline-section">
                                <div class="ap-inline-heading"><h3>Pricing</h3><span>All amounts in your selected currency.</span></div>
                                <div class="ap-price-grid">
                                    <label class="ap-field"><span>Cost Price (EUR)</span><input type="number" name="cost_price" value="{{ old('cost_price', $productMeta['cost_price'] ?? null) }}" min="0" step="0.01" placeholder="14.90"></label>
                                    <label class="ap-field"><span>Selling Price (EUR) <em>*</em></span><input type="number" name="price" value="{{ old('price', $product?->price) }}" min="0" step="0.01" placeholder="29.90" required>@error('price')<small class="ap-field-error">{{ $message }}</small>@enderror</label>
                                    <label class="ap-field"><span>Compare At Price (EUR)</span><input type="number" name="compare_price" value="{{ old('compare_price', $product?->compare_price) }}" min="0" step="0.01" placeholder="39.90"></label>
                                </div>
                                <div class="ap-price-meta">
                                    <label class="ap-field"><span>Profit Margin</span><output class="ap-output">Calculated after prices are entered</output></label>
                                    <label class="ap-field"><span>VAT / Tax</span><input type="number" name="vat_rate" value="{{ old('vat_rate', $productMeta['vat_rate'] ?? '23') }}" min="0" max="100" step="0.01" required></label>
                                    <label class="ap-field"><span>Currency</span><select name="currency"><option value="EUR" @selected(old('currency', $productMeta['currency'] ?? 'EUR') === 'EUR')>EUR — Euro (€)</option><option value="GBP" @selected(old('currency', $productMeta['currency'] ?? null) === 'GBP')>GBP — Pound (£)</option><option value="USD" @selected(old('currency', $productMeta['currency'] ?? null) === 'USD')>USD — Dollar ($)</option></select></label>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>

                <details class="ap-panel ap-additional" open>
                    <summary><span><strong>Additional Information (Optional)</strong><small>GTIN, MPN, warranty, country of origin and custom fields.</small></span><x-icon name="chevron-right" size="16" /></summary>
                    <div class="ap-additional-grid">
                        <label class="ap-field"><span>Material</span><input type="text" name="material" value="{{ old('material', $product?->material) }}" placeholder="Premium cotton twill"></label>
                        <label class="ap-field"><span>Care Instructions</span><input type="text" name="care" value="{{ old('care', $product?->care) }}" placeholder="Spot clean only"></label>
                        <label class="ap-field"><span>SEO Title</span><input type="text" name="meta_title" value="{{ old('meta_title', $product?->meta_title) }}" placeholder="Emerald Signature Cap | Emerald Rozalia"></label>
                        <label class="ap-field"><span>SEO Description</span><textarea name="meta_description" rows="2" placeholder="A concise search description for this product.">{{ old('meta_description', $product?->meta_description) }}</textarea></label>
                    </div>
                </details>
            </div>

            <aside class="ap-side-column">
                <section class="ap-rail-card">
                    <div class="ap-rail-heading"><h2>Product Status</h2><x-icon name="settings" size="16" /></div>
                    <label class="ap-field"><span>Status <em>*</em></span><select name="status"><option value="draft" @selected(old('status', $product?->status ?: 'draft') === 'draft')>Draft</option><option value="active" @selected(old('status', $product?->status) === 'active')>Active</option></select></label>
                    <label class="ap-field"><span>Stock Quantity <em>*</em></span><input type="number" name="stock" value="{{ old('stock', $product?->stock ?? 0) }}" min="0" step="1" required></label>
                    <label class="ap-field"><span>Publish Date</span><div class="ap-input-with-icon"><input type="datetime-local" name="publish_date" value="{{ $publishDate }}"><x-icon name="clock" size="16" /></div></label>
                    <div class="ap-toggle-list">
                        <label class="ap-toggle"><span>Published on Website</span><input type="hidden" name="published_website" value="0"><input type="checkbox" name="published_website" value="1" @checked((bool) $publishedWebsite)><i aria-hidden="true"><b></b></i><small aria-live="polite"></small></label>
                        <label class="ap-toggle"><span>Available for Sale</span><input type="hidden" name="available_for_sale" value="0"><input type="checkbox" name="available_for_sale" value="1" @checked((bool) $availableForSale)><i aria-hidden="true"><b></b></i><small aria-live="polite"></small></label>
                    </div>
                </section>

                <section class="ap-rail-card">
                    <div class="ap-rail-heading"><h2>Visibility &amp; Channels</h2><x-icon name="globe" size="16" /></div>
                    <div class="ap-checklist">
                        @foreach(['website'=>'Website (Online Store)','franchise'=>'Franchise Management','franchise_retail'=>'Franchise Retail Stores','corporate_bulk'=>'Corporate / Bulk Ordering','buyer'=>'Buyer Ordering'] as $channel => $label)
                            <label><input type="checkbox" name="channels[]" value="{{ $channel }}" @checked(in_array($channel, $selectedChannels, true))><span><x-icon name="check" size="13" />{{ $label }}</span></label>
                        @endforeach
                    </div>
                </section>

                <section class="ap-rail-card">
                    <div class="ap-rail-heading"><h2>Order Master Categories</h2><x-icon name="shopping-bag" size="16" /></div>
                    <p class="ap-rail-help">Select applicable order categories for this product.</p>
                    <div class="ap-checklist ap-checklist-orders">
                        @foreach(['online'=>'Online Orders','corporate'=>'Corporate Orders','bulk'=>'Bulk Orders','franchise'=>'Franchise Orders','franchise_retail'=>'Franchise Retail Orders','buyer'=>'Buyer Orders'] as $categoryKey => $label)
                            <label><input type="checkbox" name="order_categories[]" value="{{ $categoryKey }}" @checked(in_array($categoryKey, $selectedOrderCategories, true))><span><x-icon name="check" size="13" />{{ $label }}</span></label>
                        @endforeach
                    </div>
                    <div class="ap-info-note"><x-icon name="help" size="15" /><span>This product will be available in the selected order masters.</span></div>
                </section>

                <section class="ap-rail-card ap-public-placement">
                    <div class="ap-rail-heading"><h2>Public Placement</h2><x-icon name="globe" size="16" /></div>
                    <p class="ap-rail-help">Choose the public areas where this product should appear.</p>
                    <label class="ap-featured-check">
                        <input type="hidden" name="is_new_arrival" value="0">
                        <input type="checkbox" name="is_new_arrival" value="1" @checked((bool) $newArrival)>
                        <span><x-icon name="star" size="15" /> Show in New Arrivals</span>
                    </label>
                    <small class="ap-field-help ap-placement-help">Also controls the New Arrivals page and homepage New Arrivals products section.</small>

                    <fieldset class="ap-placement-collections ap-placement-category">
                        <legend>Shop by Category</legend>
                        <small class="ap-field-help">This product is placed automatically under the selected Main Category and Subcategory.</small>
                        <div class="ap-checklist">
                            <label>
                                <input type="checkbox" checked disabled>
                                <span>
                                    <x-icon name="check" size="13" />
                                    <span>
                                        <strong data-shop-category-main>Select a main category</strong>
                                        <small data-shop-category-sub>Select a subcategory above. Public category placement follows this classification automatically.</small>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="ap-placement-collections">
                        <legend>Shop by Collection</legend>
                        <small class="ap-field-help">Collections are filtered by the selected main category. Collections marked for all main categories remain available everywhere.</small>
                        <input type="hidden" name="collection_ids_present" value="1">
                        <div class="ap-checklist">
                            @forelse($placementCollections as $collection)
                                <label data-placement-collection data-main-category="{{ $collection->main_category_id ?: '' }}">
                                    <input type="checkbox" name="collection_ids[]" value="{{ $collection->id }}" @checked(in_array((string) $collection->id, $selectedCollectionIds, true))>
                                    <span>
                                        <x-icon name="check" size="13" />
                                        <span>
                                            <strong>{{ $collection->name }}</strong>
                                            @if($collection->slug === 'best-sellers')
                                                <small>Also appears in the homepage Bestsellers section.</small>
                                            @elseif($collection->slug === 'irish-heritage')
                                                <small>Appears in the Irish Heritage Collection.</small>
                                            @else
                                                <small>Appears on the {{ $collection->name }} collection page.</small>
                                            @endif
                                            @if($collection->status !== 'active' || $collection->visibility !== 'visible')
                                                <small>Currently {{ $collection->status }} / {{ $collection->visibility }}.</small>
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            @empty
                                <p class="ap-field-help">No collections are available yet.</p>
                            @endforelse
                        </div>
                        @error('collection_ids')<small class="ap-field-error">{{ $message }}</small>@enderror
                        @foreach($errors->getMessages() as $field => $messages)
                            @if(str_starts_with($field, 'collection_ids.'))
                                <small class="ap-field-error">{{ $messages[0] }}</small>
                            @endif
                        @endforeach
                        <a class="ap-field-help" href="{{ route('admin.collections.index') }}">Create or manage collections</a>
                    </fieldset>
                    <div class="ap-info-note"><x-icon name="help" size="15" /><span>Public display also requires the product to be active and published on the website. Only approved, active media is shown.</span></div>
                </section>
            </aside>
        </div>

        <div class="ap-actionbar ap-actionbar-bottom">
            <div></div>
            <div class="ap-actions">
                <a class="ap-button ap-button-muted" href="{{ route('admin.resource', 'product-manager') }}">Cancel</a>
                <button class="ap-button ap-button-outline" type="submit" name="save_action" value="draft"><x-icon name="download" size="15" /> Save Draft</button>
                <button class="ap-button ap-button-primary" type="submit" name="save_action" value="media">Next: Media &amp; Gallery <x-icon name="arrow-right" size="16" /></button>
            </div>
        </div>
    </form>
<script>
(() => {
    const root = document.querySelector('[data-product-classification]');
    if (!root) return;

    const category = root.querySelector('[data-category-root]');
    const subcategory = root.querySelector('[data-subcategory]');
    const country = root.querySelector('[data-catalog-country]');
    const countryLabel = root.querySelector('[data-catalog-country-label]');
    const county = root.querySelector('[data-catalog-county]');
    const countyField = root.querySelector('[data-catalog-county-field]');
    const countyLabel = root.querySelector('[data-catalog-county-label]');
    const club = root.querySelector('[data-catalog-club]');
    const clubField = root.querySelector('[data-catalog-club-field]');
    const clubLabel = root.querySelector('[data-catalog-club-label]');
    const clubHelp = root.querySelector('[data-catalog-club-help]');
    const counties = @json($catalogCountyOptionsByCountry);
    const clubRequired = @json(array_values($clubRequiredTaxonomies));
    const countyRequired = @json(array_values($countyRequiredTaxonomies));
    const initialCounty = @json((string) $selectedCountyCode);
    const initialClub = @json((string) $selectedClubId);
    const initialSubcategory = @json((string) $selectedCategoryId);
    const hsCode = document.querySelector('[data-auto-hs-code]');
    const placementCollections = Array.from(document.querySelectorAll('[data-placement-collection]'));
    const shopCategoryMain = document.querySelector('[data-shop-category-main]');
    const shopCategorySub = document.querySelector('[data-shop-category-sub]');
    let hsCodeManuallyEdited = Boolean(hsCode?.value.trim());
    const clubOptionsUrl = root.dataset.clubOptionsUrl || '';
    let clubRequestSerial = 0;

    const syncCategoryPlacement = () => {
        const mainLabel = category?.selectedOptions[0]?.textContent?.trim() || 'Select a main category';
        const subLabel = subcategory?.selectedOptions[0]?.textContent?.trim() || 'Select a subcategory';
        if (shopCategoryMain) shopCategoryMain.textContent = mainLabel;
        if (shopCategorySub) {
            shopCategorySub.textContent = category?.value && subcategory?.value
                ? 'Public placement: ' + mainLabel + ' → ' + subLabel
                : 'Select the Main Category and Subcategory above.';
        }
    };

    const syncCollections = () => {
        const selectedRoot = String(category?.value || '');
        placementCollections.forEach(label => {
            const mainCategory = String(label.dataset.mainCategory || '');
            const visible = mainCategory === '' || (selectedRoot !== '' && mainCategory === selectedRoot);
            label.hidden = !visible;

            const checkbox = label.querySelector('input[type="checkbox"]');
            if (!checkbox) return;
            checkbox.disabled = !visible;
            if (!visible) checkbox.checked = false;
        });
    };

    const syncSubcategories = () => {
        const selectedRoot = category?.value || '';
        let firstVisible = '';
        let selectedStillVisible = false;

        [...(subcategory?.options || [])].forEach((option, index) => {
            if (index === 0) return;
            const visible = selectedRoot !== '' && option.dataset.root === selectedRoot;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && !firstVisible) firstVisible = option.value;
            if (visible && option.value === subcategory.value) selectedStillVisible = true;
        });

        if (!selectedStillVisible) {
            subcategory.value = '';
        }
        if (!subcategory.value && selectedRoot && firstVisible && initialSubcategory === '') {
            subcategory.value = firstVisible;
        }
        if (subcategory?.options[0]) {
            subcategory.options[0].textContent = selectedRoot ? 'Select subcategory' : 'Select category first';
        }
        syncHsCode();
        syncCategoryPlacement();
        syncCollections();
    };

    const syncHsCode = () => {
        if (!hsCode || hsCodeManuallyEdited) return;

        const productType = subcategory?.selectedOptions[0]?.dataset.productType || '';
        hsCode.value = ['caps', 'hats', 'beanies', 'beanie'].includes(productType) ? '650500' : '';
    };

    hsCode?.addEventListener('input', () => {
        hsCodeManuallyEdited = hsCode.value.trim() !== '';
    });

    const syncCounties = (preserve = false) => {
        if (!country || !county) return;
        const taxonomy = selectedTaxonomy();
        const requiresCounty = countyRequired.includes(taxonomy);
        const countryCode = country.selectedOptions[0]?.dataset.code || '';
        const selected = preserve ? (county.value || initialCounty) : '';

        if (countyField) countyField.hidden = !requiresCounty;
        county.required = requiresCounty;
        county.disabled = !requiresCounty;

        if (countyLabel) {
            countyLabel.innerHTML = requiresCounty ? 'County <em>*</em>' : 'County';
        }

        county.replaceChildren(new Option(
            !requiresCounty ? 'Not required for this category' : (countryCode ? 'Select county' : 'Select country first'),
            ''
        ));

        if (requiresCounty && countryCode) {
            for (const row of (counties[countryCode] || [])) {
                county.add(new Option(row.name, row.code, false, row.code === selected));
            }
        }
        if (selected && [...county.options].some(option => option.value === selected)) {
            county.value = selected;
        }
    };

    const selectedTaxonomy = () => category?.selectedOptions[0]?.dataset.taxonomy || '';

    const syncClubs = async (preserve = false) => {
        if (!country || !club) return;

        const taxonomy = selectedTaxonomy();
        const requiresClub = clubRequired.includes(taxonomy);
        const selectedCountry = country.value;
        const selectedCounty = county.value;
        const selected = preserve ? (club.value || initialClub) : '';
        const organizationLabel = taxonomy ? taxonomy.toUpperCase() : '';
        const requestSerial = ++clubRequestSerial;

        if (clubField) clubField.hidden = !requiresClub;
        club.required = requiresClub;
        country.required = requiresClub;

        if (countryLabel) {
            countryLabel.innerHTML = requiresClub ? 'Country <em>*</em>' : 'Country';
        }

        if (clubLabel) {
            clubLabel.innerHTML = requiresClub
                ? `${organizationLabel} Club / City / Town <em>*</em>`
                : 'Club / City / Town';
        }

        const placeholderText = !requiresClub
            ? 'Not required for this category'
            : !selectedCountry
                ? `Select country before ${organizationLabel} club`
                : !selectedCounty
                    ? `Select county before ${organizationLabel} club`
                    : `Loading ${organizationLabel} clubs...`;

        club.replaceChildren(new Option(placeholderText, ''));
        club.disabled = !requiresClub || !selectedCountry || !selectedCounty;

        if (clubHelp) {
            clubHelp.firstChild.textContent = requiresClub
                ? `Select a ${organizationLabel} club matching the chosen country and county. `
                : 'GAA, English, UEFA and FIFA products require a matching club / city / town. ';
        }

        if (!requiresClub || !selectedCountry || !selectedCounty || !clubOptionsUrl) {
            return;
        }

        try {
            const params = new URLSearchParams({
                governing_body: taxonomy,
                catalog_country_id: selectedCountry,
                catalog_county_code: selectedCounty,
            });
            const response = await fetch(`${clubOptionsUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Unable to load club options.');
            const payload = await response.json();
            if (requestSerial !== clubRequestSerial) return;

            const rows = Array.isArray(payload.clubs) ? payload.clubs : [];
            club.replaceChildren(new Option(
                rows.length ? `Select ${organizationLabel} club / city / town` : `No ${organizationLabel} clubs found — add in Club Master`,
                ''
            ));

            for (const row of rows) {
                const option = new Option(row.name, String(row.id), false, String(row.id) === String(selected));
                option.dataset.scope = row.scope || '';
                club.add(option);
            }

            if (selected && [...club.options].some(option => option.value === String(selected))) {
                club.value = String(selected);
            }
            club.disabled = false;
        } catch (error) {
            if (requestSerial !== clubRequestSerial) return;
            club.replaceChildren(new Option(`Unable to load ${organizationLabel} clubs`, ''));
            club.disabled = false;
        }
    };

    category?.addEventListener('change', () => {
        syncSubcategories();
        syncCounties(false);
        void syncClubs(false);
    });
    country?.addEventListener('change', () => {
        syncCounties(false);
        void syncClubs(false);
    });
    county?.addEventListener('change', () => void syncClubs(false));
    subcategory?.addEventListener('change', () => {
        syncHsCode();
        syncCategoryPlacement();
    });

    syncSubcategories();
    syncCategoryPlacement();
    syncCollections();
    syncCounties(true);
    void syncClubs(true);
})();
</script>
</div>
@endsection
