@extends('layouts.admin')

@section('title', 'Add New Product')

@php
    $selectedChannels = old('channels', ['website', 'franchise_portal', 'franchise_retail', 'corporate_bulk']);
    $selectedOrderCategories = old('order_categories', ['online', 'bulk', 'franchise', 'franchise_retail']);
    $selectedChannels = is_array($selectedChannels) ? $selectedChannels : [];
    $selectedOrderCategories = is_array($selectedOrderCategories) ? $selectedOrderCategories : [];
    $publishDate = old('publish_date', now()->format('Y-m-d\TH:i'));
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
                <strong>Add Product</strong>
            </nav>
            <p class="ap-eyebrow">ADMIN / OPERATIONS</p>
            <h1>Add New Product</h1>
            <p class="ap-intro">Create a new product and publish it to your website and sales channels.</p>
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

    <form action="{{ route('admin.add-product.store') }}" method="post" class="ap-form">
        @csrf

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
                                <input type="text" name="name" value="{{ old('name') }}" placeholder="Emerald Signature Cap" required>
                                @error('name')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                            <label class="ap-field ap-field-wide">
                                <span>Short Description <em>*</em></span>
                                <textarea name="short_description" rows="3" placeholder="Premium quality cap with embroidered Emerald Rozalia logo." required>{{ old('short_description') }}</textarea>
                                @error('short_description')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                            <label class="ap-field ap-field-wide">
                                <span>Short Name / Slug <em>*</em></span>
                                <input type="text" name="slug" value="{{ old('slug') }}" placeholder="emerald-signature-cap">
                                <small class="ap-field-help">URL: <x-icon name="globe" size="12" /> https://www.emeraldrozalia.ie/product/{{ old('slug', 'your-product-slug') }}</small>
                                @error('slug')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                            <label class="ap-field ap-field-wide">
                                <span>SKU / Barcode <em>*</em></span>
                                <div class="ap-input-with-icon"><input type="text" name="sku" value="{{ old('sku') }}" placeholder="ERCAP-GRN-001" required><x-icon name="tag" size="17" /></div>
                                @error('sku')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                            <div class="ap-field-row">
                                <label class="ap-field">
                                    <span>Category <em>*</em></span>
                                    <select name="category_id" required>
                                        <option value="">Select category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('category_id')<small class="ap-field-error">{{ $message }}</small>@enderror
                                </label>
                                <label class="ap-field">
                                    <span>Collection</span>
                                    <select disabled aria-describedby="collection-help"><option>No collection assigned</option></select>
                                    <small id="collection-help" class="ap-field-help">Manage collections after saving.</small>
                                </label>
                            </div>

                            <label class="ap-field ap-field-wide">
                                <span>Brand / Line</span>
                                <input type="text" name="brand" value="{{ old('brand', 'Emerald Rozalia') }}" placeholder="Emerald Rozalia">
                            </label>

                            <label class="ap-field ap-field-wide">
                                <span>Tags</span>
                                <input type="text" name="tags" value="{{ old('tags') }}" placeholder="Cap, Signature, Green, Premium">
                                <small class="ap-field-help">Separate tags with commas.</small>
                            </label>

                            <fieldset class="ap-fieldset ap-field-wide">
                                <legend>Product Type</legend>
                                <div class="ap-radio-row">
                                    <label><input type="radio" name="product_type" value="simple" @checked(old('product_type', 'simple') === 'simple')> <span>Simple</span></label>
                                    <label><input type="radio" name="product_type" value="variable" @checked(old('product_type') === 'variable')> <span>Variable (Variants)</span></label>
                                    <label><input type="radio" name="product_type" value="bundle" @checked(old('product_type') === 'bundle')> <span>Bundle / Kit</span></label>
                                </div>
                                @error('product_type')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </fieldset>

                            <div class="ap-field-row">
                                <label class="ap-field">
                                    <span>Tax Class</span>
                                    <select name="tax_class">
                                        <option value="standard" @selected(old('tax_class', 'standard') === 'standard')>Standard Rate (23%)</option>
                                        <option value="reduced" @selected(old('tax_class') === 'reduced')>Reduced Rate</option>
                                        <option value="zero" @selected(old('tax_class') === 'zero')>Zero Rate</option>
                                    </select>
                                </label>
                                <label class="ap-field">
                                    <span>HS Code</span>
                                    <input type="text" name="hs_code" value="{{ old('hs_code') }}" placeholder="6505.00.30">
                                </label>
                            </div>

                            <div class="ap-field-row ap-field-row-three">
                                <label class="ap-field"><span>Weight (kg)</span><input type="number" name="weight" value="{{ old('weight') }}" min="0" step="0.001" placeholder="0.25"></label>
                                <label class="ap-field"><span>Length (cm)</span><input type="number" name="length" value="{{ old('length') }}" min="0" step="0.01" placeholder="22"></label>
                                <label class="ap-field"><span>Width (cm)</span><input type="number" name="width" value="{{ old('width') }}" min="0" step="0.01" placeholder="18"></label>
                            </div>
                            <label class="ap-field ap-field-small"><span>Height (cm)</span><input type="number" name="height" value="{{ old('height') }}" min="0" step="0.01" placeholder="12"></label>
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
                                    <textarea name="description" rows="11" placeholder="Describe the product, materials, fit and care instructions." required>{{ old('description') }}</textarea>
                                </div>
                                @error('description')<small class="ap-field-error">{{ $message }}</small>@enderror
                            </label>

                            <section class="ap-inline-section">
                                <div class="ap-inline-heading"><h3>Pricing</h3><span>All amounts in your selected currency.</span></div>
                                <div class="ap-price-grid">
                                    <label class="ap-field"><span>Cost Price (EUR)</span><input type="number" name="cost_price" value="{{ old('cost_price') }}" min="0" step="0.01" placeholder="14.90"></label>
                                    <label class="ap-field"><span>Selling Price (EUR) <em>*</em></span><input type="number" name="price" value="{{ old('price') }}" min="0" step="0.01" placeholder="29.90" required>@error('price')<small class="ap-field-error">{{ $message }}</small>@enderror</label>
                                    <label class="ap-field"><span>Compare At Price (EUR)</span><input type="number" name="compare_price" value="{{ old('compare_price') }}" min="0" step="0.01" placeholder="39.90"></label>
                                </div>
                                <div class="ap-price-meta">
                                    <label class="ap-field"><span>Profit Margin</span><output class="ap-output">Calculated after prices are entered</output></label>
                                    <label class="ap-field"><span>VAT / Tax</span><input type="number" name="vat_rate" value="{{ old('vat_rate', '23') }}" min="0" max="100" step="0.01" required></label>
                                    <label class="ap-field"><span>Currency</span><select name="currency"><option value="EUR" @selected(old('currency', 'EUR') === 'EUR')>EUR — Euro (€)</option><option value="GBP" @selected(old('currency') === 'GBP')>GBP — Pound (£)</option><option value="USD" @selected(old('currency') === 'USD')>USD — Dollar ($)</option></select></label>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>

                <details class="ap-panel ap-additional" open>
                    <summary><span><strong>Additional Information (Optional)</strong><small>GTIN, MPN, warranty, country of origin and custom fields.</small></span><x-icon name="chevron-right" size="16" /></summary>
                    <div class="ap-additional-grid">
                        <label class="ap-field"><span>Material</span><input type="text" name="material" value="{{ old('material') }}" placeholder="Premium cotton twill"></label>
                        <label class="ap-field"><span>Care Instructions</span><input type="text" name="care" value="{{ old('care') }}" placeholder="Spot clean only"></label>
                        <label class="ap-field"><span>SEO Title</span><input type="text" name="meta_title" value="{{ old('meta_title') }}" placeholder="Emerald Signature Cap | Emerald Rozalia"></label>
                        <label class="ap-field"><span>SEO Description</span><textarea name="meta_description" rows="2" placeholder="A concise search description for this product.">{{ old('meta_description') }}</textarea></label>
                    </div>
                </details>
            </div>

            <aside class="ap-side-column">
                <section class="ap-rail-card">
                    <div class="ap-rail-heading"><h2>Product Status</h2><x-icon name="settings" size="16" /></div>
                    <label class="ap-field"><span>Status <em>*</em></span><select name="status"><option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option><option value="active" @selected(old('status') === 'active')>Active</option></select></label>
                    <label class="ap-field"><span>Publish Date</span><div class="ap-input-with-icon"><input type="datetime-local" name="publish_date" value="{{ $publishDate }}"><x-icon name="clock" size="16" /></div></label>
                    <div class="ap-toggle-list">
                        <label class="ap-toggle"><span>Published on Website</span><input type="hidden" name="published_website" value="0"><input type="checkbox" name="published_website" value="1" @checked(old('published_website', false))><i aria-hidden="true"><b></b></i><small aria-live="polite"></small></label>
                        <label class="ap-toggle"><span>Available for Sale</span><input type="hidden" name="available_for_sale" value="0"><input type="checkbox" name="available_for_sale" value="1" @checked(old('available_for_sale', false))><i aria-hidden="true"><b></b></i><small aria-live="polite"></small></label>
                    </div>
                </section>

                <section class="ap-rail-card">
                    <div class="ap-rail-heading"><h2>Visibility &amp; Channels</h2><x-icon name="globe" size="16" /></div>
                    <div class="ap-checklist">
                        @foreach(['website'=>'Website (Online Store)','franchise_portal'=>'Franchise Ordering Portal','franchise_retail'=>'Franchise Retail Stores','corporate_bulk'=>'Corporate / Bulk Ordering','buyer'=>'Buyer Ordering'] as $channel => $label)
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

                <label class="ap-featured-check"><input type="hidden" name="featured" value="0"><input type="checkbox" name="featured" value="1" @checked(old('featured', false))><span><x-icon name="star" size="15" /> Mark as featured product</span></label>
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
</div>
@endsection
