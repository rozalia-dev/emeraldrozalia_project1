@extends('layouts.admin')

@section('title', 'Collections')

@php
    $metricCards = [
        ['label' => 'Total Collections', 'value' => $metrics['total'], 'change' => '+18.1%', 'note' => 'vs last 30 days', 'icon' => 'package', 'tone' => 'green'],
        ['label' => 'Featured Collections', 'value' => $metrics['featured'], 'change' => '+12.5%', 'note' => 'vs last 30 days', 'icon' => 'star', 'tone' => 'purple'],
        ['label' => 'Seasonal Collections', 'value' => $metrics['seasonal'], 'change' => '+22.7%', 'note' => 'vs last 30 days', 'icon' => 'calendar', 'tone' => 'orange'],
        ['label' => 'Visible on Website', 'value' => $metrics['visible'], 'change' => null, 'note' => $metrics['total'] ? number_format(($metrics['visible'] / $metrics['total']) * 100, 1).'% of total' : '0% of total', 'icon' => 'eye', 'tone' => 'blue'],
        ['label' => 'Products in Collections', 'value' => $metrics['products'], 'change' => '+16.3%', 'note' => 'collection placements', 'icon' => 'tag', 'tone' => 'teal'],
    ];
    $typeLabels = ['curated' => 'Curated', 'seasonal' => 'Seasonal', 'occasion' => 'Occasion', 'automated' => 'Automated'];
    $typeTones = ['curated' => 'purple', 'seasonal' => 'blue', 'occasion' => 'red', 'automated' => 'teal'];
    $typeTotal = max(1, array_sum($typeBreakdown));
    $collectionPayload = function ($collection): array {
        return [
            'id' => $collection->id,
            'name' => $collection->name,
            'slug' => $collection->slug,
            'type' => $collection->type,
            'season' => $collection->season,
            'description' => $collection->description,
            'image' => $collection->image,
            'status' => $collection->status,
            'visibility' => $collection->visibility,
            'is_featured' => (bool) $collection->is_featured,
            'show_on_homepage' => (bool) $collection->show_on_homepage,
            'allow_in_filters' => (bool) $collection->allow_in_filters,
            'sort_order' => $collection->sort_order,
            'meta_title' => $collection->meta_title,
            'meta_description' => $collection->meta_description,
            'meta_image' => $collection->meta_image,
        ];
    };
    $imageUrl = function (?string $image): ?string {
        if (! $image) return null;
        if (preg_match('/^https?:\/\//', $image) || str_starts_with($image, '/')) return $image;
        if (str_starts_with($image, 'assets/') || str_starts_with($image, 'images/') || str_starts_with($image, 'storage/')) return asset($image);
        return \Illuminate\Support\Facades\Storage::disk('public')->url($image);
    };
    $exportQuery = array_filter([
        'tab' => $tab,
        'q' => $search,
        'type' => $type,
        'status' => $status,
        'season' => $season,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/collections-admin.css') }}?v=20260910">
<link rel="stylesheet" href="{{ asset('css/collections-admin-fixes.css') }}?v=20260910-2">
@endpush

@section('content')
<div class="collections-screen">
    @if(session('success'))<div class="collection-flash collection-flash--success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="collection-flash collection-flash--error"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <header class="collections-heading">
        <div>
            <nav class="collections-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('admin.dashboard') }}">Project 1 Control Panel</a><span>•</span><span>Website &amp; Products</span><span>•</span><strong>Collections</strong></nav>
            <h1>Collections</h1>
            <p>Create and manage curated collections to showcase products for every occasion and theme.</p>
        </div>
        <div class="collections-date-card"><x-icon name="calendar" size="25" /><span><small>Today</small><b>{{ now()->format('l, j F Y') }}</b><strong>{{ now()->format('h:i A') }}</strong></span></div>
    </header>

    <section class="collections-kpis" aria-label="Collection metrics">
        @foreach($metricCards as $card)
            <article class="collections-kpi collections-kpi--{{ $card['tone'] }}">
                <span class="collections-kpi-icon"><x-icon name="{{ $card['icon'] }}" size="20" /></span>
                <span class="collections-kpi-body"><small>{{ $card['label'] }}</small><strong>{{ number_format($card['value']) }}</strong><em>@if($card['change'])<b>{{ $card['change'] }}</b> @endif{{ $card['note'] }}</em></span>
            </article>
        @endforeach
    </section>

    <div class="collections-layout">
        <section class="collections-catalog">
            <div class="collections-toolbar">
                <form class="collections-filter-form" method="get" action="{{ route('admin.collections.index') }}" id="collection-filter-form">
                    <label class="collections-search"><x-icon name="search" size="15" /><input name="q" value="{{ $search }}" type="search" placeholder="Search collections..." aria-label="Search collections"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></label>
                    <details class="collections-filter-menu"><summary><x-icon name="filter" size="14" /> Filters <x-icon name="chevron-right" size="11" /></summary><div class="collection-filter-help"><span>Use the type, status and season controls beside this button. Filters are combined with search and tabs.</span><a href="{{ route('admin.collections.index') }}">Reset all filters</a></div></details>
                    <select class="collections-quick-select" name="type" aria-label="Filter by type" data-auto-filter><option value="">All Types</option>@foreach($collectionTypes as $option)<option value="{{ $option }}" @selected($type === $option)>{{ $typeLabels[$option] }}</option>@endforeach</select>
                    <select class="collections-quick-select" name="status" aria-label="Filter by status" data-auto-filter><option value="">All Statuses</option>@foreach($collectionStatuses as $option)<option value="{{ $option }}" @selected($status === $option)>{{ str($option)->headline() }}</option>@endforeach</select>
                    <select class="collections-quick-select" name="season" aria-label="Filter by season" data-auto-filter><option value="">All Seasons</option>@foreach($seasons as $option)<option value="{{ $option }}" @selected($season === $option)>{{ $option }}</option>@endforeach</select>
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <button class="collections-add-button" type="button" data-collection-create><x-icon name="plus" size="14" /> Add Collection <x-icon name="chevron-right" size="11" /></button>
                </form>
            </div>

            <div class="collections-bulk-bar" data-bulk-bar hidden><strong><span data-selected-count>0</span> selected</strong><button type="button" class="collections-outline-button" data-bulk-open>Bulk Update</button><button type="button" class="collections-outline-button" data-clear-selection>Clear</button></div>

            <nav class="collections-tabs" aria-label="Collection views">
                @foreach($tabs as $tabKey => $tabLabel)
                    <a class="{{ $tab === $tabKey ? 'is-active' : '' }}" href="{{ route('admin.collections.index', array_filter(['tab' => $tabKey, 'q' => $search, 'type' => $type, 'status' => $status, 'season' => $season, 'per_page' => $perPage])) }}">{{ $tabLabel }}</a>
                @endforeach
            </nav>

            <div class="collections-table-wrap">
                <table class="collections-table">
                    <thead><tr><th class="collections-check-col"><input type="checkbox" data-collections-select-all aria-label="Select all collections"></th><th>Collection</th><th>Type</th><th>Season / Occasion</th><th>Products</th><th>Status</th><th>Visibility</th><th>Sort Order</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($collections as $collection)
                        @php $payload = $collectionPayload($collection); $thumb = $imageUrl($collection->image); @endphp
                        <tr class="{{ $selectedCollection?->id === $collection->id ? 'is-selected' : '' }}" data-collection-row>
                            <td><input type="checkbox" value="{{ $collection->id }}" data-collection-check aria-label="Select {{ $collection->name }}"></td>
                            <td><a class="collection-name-cell" href="{{ route('admin.collections.index', array_filter(['selected' => $collection->id, 'tab' => $tab, 'q' => $search, 'type' => $type, 'status' => $status, 'season' => $season, 'per_page' => $perPage])) }}"><span class="collection-thumb collection-thumb--{{ $typeTones[$collection->type] ?? 'green' }}">@if($thumb)<img src="{{ $thumb }}" alt="">@else<x-icon name="package" size="22" />@endif</span><span><strong>{{ $collection->name }}</strong><small>{{ $collection->slug }}</small></span></a></td>
                            <td><span class="collection-type-badge collection-type-badge--{{ $typeTones[$collection->type] ?? 'green' }}">{{ $typeLabels[$collection->type] ?? str($collection->type)->headline() }}</span></td>
                            <td>{{ $collection->season ?: 'All Season' }}</td>
                            <td><strong>{{ number_format($collection->products_count) }}</strong><small>Products</small></td>
                            <td><span class="collection-status collection-status--{{ $collection->status }}"><i></i>{{ str($collection->status)->headline() }}</span></td>
                            <td><span class="collection-visibility collection-visibility--{{ $collection->visibility }}">{{ $collection->visibility === 'visible' ? 'Visible' : 'Hidden' }}</span></td>
                            <td>{{ $collection->sort_order }}</td>
                            <td><div class="collection-row-actions"><a class="collection-icon-button" href="{{ route('collections', ['collection' => $collection->slug]) }}" target="_blank" rel="noreferrer" title="View on website"><x-icon name="eye" size="14" /></a><button class="collection-icon-button" type="button" data-collection-edit="{{ e(json_encode($payload)) }}" title="Edit collection"><x-icon name="edit" size="14" /></button><details class="collection-row-menu"><summary class="collection-icon-button" title="More actions"><x-icon name="more-vertical" size="14" /></summary><div><form method="post" action="{{ route('admin.collections.visibility', $collection) }}">@csrf @method('PATCH')<button type="submit">{{ $collection->visibility === 'visible' ? 'Hide collection' : 'Show collection' }}</button></form><form method="post" action="{{ route('admin.collections.duplicate', $collection) }}">@csrf<button type="submit">Duplicate</button></form><a href="{{ route('admin.collections.audit', $collection) }}">Audit log</a><form method="post" action="{{ route('admin.collections.destroy', $collection) }}" onsubmit="return confirm('Delete this collection? Products will not be deleted.')">@csrf @method('DELETE')<button class="is-danger" type="submit">Delete</button></form></div></details></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="collections-empty"><x-icon name="package" size="27" /><strong>No collections match these filters.</strong><span>Create your first collection to start merchandising the catalogue.</span><button class="collections-link-button" type="button" data-collection-create>Add a collection</button></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="collections-table-footer">
                <span>Showing {{ $collections->firstItem() ?: 0 }} to {{ $collections->lastItem() ?: 0 }} of {{ number_format($collections->total()) }} collections</span>
                <div class="collections-pagination">{{ $collections->onEachSide(1)->links() }}</div>
                <form method="get" action="{{ route('admin.collections.index') }}" class="collection-per-page-form">
                    @foreach(['tab'=>$tab,'q'=>$search,'type'=>$type,'status'=>$status,'season'=>$season] as $key=>$value)@if($value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                    <select name="per_page" onchange="this.form.submit()"><option value="10" @selected($perPage===10)>10 / page</option><option value="25" @selected($perPage===25)>25 / page</option><option value="50" @selected($perPage===50)>50 / page</option></select>
                </form>
            </footer>
        </section>

        <aside class="collections-detail-rail">
            @if($selectedCollection)
                @php $selectedThumb = $imageUrl($selectedCollection->image); $shareUrl = route('collections', ['collection' => $selectedCollection->slug]); @endphp
                <section class="collection-detail-card">
                    <div class="collection-detail-heading"><div><span class="collections-rail-eyebrow">SELECTED COLLECTION</span><h2>Collection Details</h2></div><a href="{{ route('admin.collections.index') }}" aria-label="Clear selection">×</a></div>
                    <div class="collection-detail-image collection-detail-image--{{ $typeTones[$selectedCollection->type] ?? 'green' }}">@if($selectedThumb)<img src="{{ $selectedThumb }}" alt="{{ $selectedCollection->name }}">@else<span>EMERALD<br><b>{{ strtoupper($selectedCollection->name) }}</b></span>@endif</div>
                    <span class="collection-active-pill collection-active-pill--{{ $selectedCollection->status }}">{{ str($selectedCollection->status)->headline() }}</span>
                    <dl class="collection-detail-list">
                        <div><dt>Collection Name</dt><dd>{{ $selectedCollection->name }}</dd></div><div><dt>Slug</dt><dd>{{ $selectedCollection->slug }}</dd></div><div><dt>Type</dt><dd>{{ $typeLabels[$selectedCollection->type] ?? str($selectedCollection->type)->headline() }}</dd></div><div><dt>Season / Occasion</dt><dd>{{ $selectedCollection->season ?: 'All Season' }}</dd></div><div><dt>Products</dt><dd>{{ number_format($selectedCollection->products->count()) }} Products</dd></div><div><dt>Visibility</dt><dd>{{ $selectedCollection->visibility === 'visible' ? 'Visible on Website' : 'Hidden' }}</dd></div><div><dt>Status</dt><dd>{{ str($selectedCollection->status)->headline() }}</dd></div><div><dt>Sort Order</dt><dd>{{ $selectedCollection->sort_order }}</dd></div><div class="collection-detail-description"><dt>Description</dt><dd>{{ $selectedCollection->description ?: 'No description added yet.' }}</dd></div><div><dt>Created On</dt><dd>{{ $selectedCollection->created_at?->format('d M Y h:i A') ?: '—' }}</dd></div><div><dt>Created By</dt><dd>{{ $selectedCollection->creator?->name ?: 'Admin User' }}</dd></div><div><dt>Last Updated</dt><dd>{{ $selectedCollection->updated_at?->format('d M Y h:i A') ?: '—' }}</dd></div><div><dt>Last Updated By</dt><dd>{{ $selectedCollection->updater?->name ?: 'Admin User' }}</dd></div>
                    </dl>
                    <div class="collection-detail-actions"><button class="collections-outline-button" type="button" data-collection-edit="{{ e(json_encode($collectionPayload($selectedCollection))) }}"><x-icon name="edit" size="13" /> Edit Collection</button><button class="collections-outline-button collections-outline-button--green" type="button" data-products-open><x-icon name="plus" size="13" /> Add Products</button></div>
                    <div class="collection-quick-links"><strong>Quick Links</strong><a href="{{ $shareUrl }}" target="_blank" rel="noreferrer"><x-icon name="eye" size="13" /> View on Website <x-icon name="arrow-right" size="11" /></a><form method="post" action="{{ route('admin.collections.duplicate', $selectedCollection) }}">@csrf<button type="submit"><x-icon name="copy" size="13" /> Duplicate Collection <x-icon name="arrow-right" size="11" /></button></form><button type="button" data-share-url="{{ $shareUrl }}"><x-icon name="link" size="13" /> Generate Shareable Link <x-icon name="arrow-right" size="11" /></button></div>
                </section>
            @else
                <section class="collection-detail-card collection-detail-empty"><x-icon name="mouse-pointer" size="28" /><h2>Select a collection</h2><p>Choose a collection from the catalogue to review its details, products and merchandising settings.</p></section>
            @endif

            <section class="collections-rail-card"><div class="collections-rail-heading"><h2>Collection Summary</h2><x-icon name="bar-chart" size="15" /></div><dl class="collections-summary-list"><div><dt>Total Collections</dt><dd>{{ number_format($metrics['total']) }}</dd></div><div><dt>Featured Collections</dt><dd>{{ number_format($metrics['featured']) }}</dd></div><div><dt>Seasonal Collections</dt><dd>{{ number_format($metrics['seasonal']) }}</dd></div><div><dt>Occasion Collections</dt><dd>{{ number_format($typeBreakdown['occasion'] ?? 0) }}</dd></div><div><dt>Automated Collections</dt><dd>{{ number_format($typeBreakdown['automated'] ?? 0) }}</dd></div><div><dt>Draft Collections</dt><dd>{{ number_format($metrics['draft']) }}</dd></div><div><dt>Hidden Collections</dt><dd>{{ number_format($metrics['hidden']) }}</dd></div></dl></section>

            <section class="collections-rail-card"><div class="collections-rail-heading"><h2>Collection Types</h2><x-icon name="pie-chart" size="15" /></div><div class="collections-type-summary"><div class="collections-type-donut" style="--curated: {{ ($typeBreakdown['curated'] ?? 0) / $typeTotal * 100 }}%; --seasonal: {{ ($typeBreakdown['seasonal'] ?? 0) / $typeTotal * 100 }}%; --occasion: {{ ($typeBreakdown['occasion'] ?? 0) / $typeTotal * 100 }}%;"><span>{{ number_format(array_sum($typeBreakdown)) }}<small>Total</small></span></div><ul>@foreach($typeLabels as $key => $label)<li><i class="collection-dot collection-dot--{{ $typeTones[$key] }}"></i><span>{{ $label }}</span><b>{{ number_format($typeBreakdown[$key] ?? 0) }} <small>{{ array_sum($typeBreakdown) ? number_format(($typeBreakdown[$key] / array_sum($typeBreakdown)) * 100, 1) : '0.0' }}%</small></b></li>@endforeach</ul></div></section>

            <section class="collections-rail-card collections-quick-card"><div class="collections-rail-heading"><h2>Quick Actions</h2><x-icon name="zap" size="15" /></div><button type="button" data-collection-create><x-icon name="plus" size="14" /> Add Collection</button><button type="button" data-bulk-open><x-icon name="edit" size="14" /> Bulk Update Collections</button><button type="button" data-reorder-open><x-icon name="list" size="14" /> Reorder Collections</button><button type="button" data-import-open><x-icon name="upload" size="14" /> Import Collections</button><a href="{{ route('admin.collections.export', $exportQuery) }}"><x-icon name="download" size="14" /> Export Collections</a><a href="{{ route('admin.resource', 'training-documents') }}"><x-icon name="file-text" size="14" /> Collection Management Guide</a></section>

            <section class="collections-rail-card collection-uuid-card"><div class="collections-rail-heading"><h2>UUID Traceability</h2><x-icon name="link" size="15" /></div><p>Every collection is assigned a unique UUID for full traceability.</p>@if($selectedCollection)<small>Selected Collection UUID</small><div class="collection-uuid-value"><code>{{ $selectedCollection->public_uuid }}</code><button type="button" data-copy-value="{{ $selectedCollection->public_uuid }}" title="Copy UUID"><x-icon name="copy" size="13" /></button></div><a class="collection-audit-link" href="{{ route('admin.collections.audit', $selectedCollection) }}">View Collection Audit Log <x-icon name="chevron-right" size="12" /></a>@else<small>Select a collection to view its UUID and audit log.</small>@endif</section>
        </aside>
    </div>

    @if($selectedCollection)
        <section class="collections-bottom-grid">
            <article class="collections-bottom-card"><div class="collections-rail-heading"><div><h2>Add Products to Collection</h2><small>Select how you want to add products.</small></div><x-icon name="package" size="15" /></div><button class="collections-action-tile" type="button" data-products-open><span><x-icon name="search" size="18" /></span><strong>Search &amp; Add Products</strong><small>Manually search and select products.</small><x-icon name="arrow-right" size="13" /></button><button class="collections-action-tile" type="button" data-category-open><span><x-icon name="folder" size="18" /></span><strong>Add by Category</strong><small>Add active products from a category.</small><x-icon name="arrow-right" size="13" /></button><button class="collections-action-tile" type="button" data-product-import-open><span><x-icon name="upload" size="18" /></span><strong>Import from CSV</strong><small>Upload CSV with product SKU and sort order.</small><x-icon name="arrow-right" size="13" /></button></article>

            <article class="collections-bottom-card"><div class="collections-rail-heading"><div><h2>Collection Settings</h2><small>Changes save directly to this collection.</small></div><x-icon name="settings" size="15" /></div><form method="post" action="{{ route('admin.collections.settings', $selectedCollection) }}" class="collection-settings-form">@csrf @method('PATCH')<div class="collection-setting-grid"><label>Visibility<select name="visibility"><option value="visible" @selected($selectedCollection->visibility === 'visible')>Visible on Website</option><option value="hidden" @selected($selectedCollection->visibility === 'hidden')>Hidden</option></select></label><label>Status<select name="status"><option value="active" @selected($selectedCollection->status === 'active')>Active</option><option value="draft" @selected($selectedCollection->status === 'draft')>Draft</option><option value="archived" @selected($selectedCollection->status === 'archived')>Archived</option></select></label><label class="collection-toggle"><input type="checkbox" name="is_featured" value="1" @checked($selectedCollection->is_featured)><span></span> Featured Collection</label><label class="collection-toggle"><input type="checkbox" name="show_on_homepage" value="1" @checked($selectedCollection->show_on_homepage)><span></span> Show on Homepage</label><label class="collection-toggle"><input type="checkbox" name="allow_in_filters" value="1" @checked($selectedCollection->allow_in_filters)><span></span> Allow in Filters</label></div><button class="collections-save-settings" type="submit">Save Settings <x-icon name="arrow-right" size="13" /></button></form></article>

            <article class="collections-bottom-card"><div class="collections-rail-heading"><div><h2>SEO &amp; Content</h2><small>Improve collection discovery.</small></div><x-icon name="search" size="15" /></div><label class="collection-seo-field">Meta Title<input value="{{ $selectedCollection->meta_title ?: $selectedCollection->name.' Collection — Emerald Rozalia' }}" readonly></label><label class="collection-seo-field">Meta Description<textarea readonly>{{ $selectedCollection->meta_description ?: 'Explore the '.$selectedCollection->name.' collection of premium Emerald Rozalia headwear.' }}</textarea></label><div class="collection-meta-image">@if($imageUrl($selectedCollection->meta_image))<img src="{{ $imageUrl($selectedCollection->meta_image) }}" alt="">@else<span></span>@endif<button class="collections-outline-button" type="button" data-collection-edit="{{ e(json_encode($collectionPayload($selectedCollection))) }}">Change Image / Edit SEO</button></div></article>
        </section>
    @endif
</div>

<dialog class="collection-dialog" id="collection-modal">
    <form class="collection-dialog-form" id="collection-form" method="post" action="{{ route('admin.collections.store') }}" enctype="multipart/form-data">
        @csrf <input type="hidden" name="_method" id="collection-form-method" value="">
        <div class="collection-dialog-heading"><div><small>COLLECTION MANAGEMENT</small><h2 id="collection-modal-title">Add Collection</h2><p>Build a merchandised destination for the website catalogue.</p></div><button class="collection-dialog-close" type="button" data-dialog-close aria-label="Close">×</button></div>
        <div class="collection-form-grid"><label class="collection-field-wide">Collection Name<input name="name" required maxlength="180" placeholder="e.g. Spring Summer 2025"></label><label>Slug<input name="slug" maxlength="180" placeholder="spring-summer-2025"></label><label>Type<select name="type" required>@foreach($collectionTypes as $option)<option value="{{ $option }}">{{ $typeLabels[$option] }}</option>@endforeach</select></label><label>Season / Occasion<input name="season" maxlength="120" placeholder="e.g. Spring / Summer 2025"></label><label>Status<select name="status" required>@foreach($collectionStatuses as $option)<option value="{{ $option }}">{{ str($option)->headline() }}</option>@endforeach</select></label><label>Visibility<select name="visibility" required>@foreach($collectionVisibilities as $option)<option value="{{ $option }}">{{ str($option)->headline() }}</option>@endforeach</select></label><label>Sort Order<input name="sort_order" type="number" min="0" max="999999" value="0" required></label><label>Cover image path<input name="image" maxlength="255" placeholder="assets/collections/spring.jpg"></label><label>Or upload cover image<input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/avif"></label><label class="collection-field-wide">Description<textarea name="description" rows="3" maxlength="5000" placeholder="Describe the collection and its merchandising story."></textarea></label><label class="collection-field-wide">Meta Title<input name="meta_title" maxlength="180" placeholder="Collection title for search engines"></label><label class="collection-field-wide">Meta Description<textarea name="meta_description" rows="2" maxlength="1000" placeholder="A concise description for search engines."></textarea></label><label>Meta image path<input name="meta_image" maxlength="255" placeholder="assets/collections/spring-og.jpg"></label><label>Or upload meta image<input type="file" name="meta_image_file" accept="image/jpeg,image/png,image/webp,image/avif"></label></div>
        <div class="collection-check-grid"><label><input type="checkbox" name="is_featured" value="1"> Featured collection</label><label><input type="checkbox" name="show_on_homepage" value="1"> Show on homepage</label><label><input type="checkbox" name="allow_in_filters" value="1" checked> Allow in filters</label></div>
        <div class="collection-dialog-actions"><button class="collections-outline-button" type="button" data-dialog-close>Cancel</button><button class="collections-save-settings" type="submit"><x-icon name="check" size="14" /> Save Collection</button></div>
    </form>
</dialog>

@if($selectedCollection)
<dialog class="collection-dialog collection-products-dialog" id="collection-products-modal"><form class="collection-dialog-form" method="post" action="{{ route('admin.collections.products.sync', $selectedCollection) }}">@csrf<div class="collection-dialog-heading"><div><small>PRODUCT ASSIGNMENT</small><h2>Add Products to Collection</h2><p>Selected products are saved in merchandising order.</p></div><button class="collection-dialog-close" type="button" data-dialog-close aria-label="Close">×</button></div><div class="collection-products-toolbar"><label><x-icon name="search" size="14" /><input type="search" placeholder="Search products..." data-product-search></label><span data-product-count>0 selected</span></div><div class="collection-product-picker">@forelse($products as $product)<label class="collection-product-option" data-product-option data-product-name="{{ strtolower($product->name.' '.$product->sku) }}"><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked($selectedCollection->products->contains($product->id))><span class="collection-product-mini-thumb"><x-icon name="package" size="15" /></span><span><strong>{{ $product->name }}</strong><small>{{ $product->sku }} · {{ $product->category?->name ?: 'Uncategorised' }}</small></span></label>@empty<p class="collections-empty">No products are available.</p>@endforelse</div><div class="collection-dialog-actions"><button class="collections-outline-button" type="button" data-dialog-close>Cancel</button><button class="collections-save-settings" type="submit"><x-icon name="check" size="14" /> Save Products</button></div></form></dialog>

<dialog class="collection-dialog collection-dialog--small" id="collection-category-modal"><form class="collection-dialog-form" method="post" action="{{ route('admin.collections.products.category', $selectedCollection) }}">@csrf<div class="collection-dialog-heading"><div><small>ADD BY CATEGORY</small><h2>Add Products by Category</h2><p>All active products in the selected category will be added without removing existing products.</p></div><button class="collection-dialog-close" type="button" data-dialog-close>×</button></div><label class="collection-dialog-field">Category<select name="category_id" required><option value="">Choose a category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label><div class="collection-dialog-actions"><button class="collections-outline-button" type="button" data-dialog-close>Cancel</button><button class="collections-save-settings" type="submit">Add Category Products</button></div></form></dialog>

<dialog class="collection-dialog collection-dialog--small" id="collection-product-import-modal"><form class="collection-dialog-form" method="post" action="{{ route('admin.collections.products.import', $selectedCollection) }}" enctype="multipart/form-data">@csrf<div class="collection-dialog-heading"><div><small>PRODUCT CSV IMPORT</small><h2>Import Products</h2><p>CSV headers: <code>sku,sort_order</code>. Sort order is optional.</p></div><button class="collection-dialog-close" type="button" data-dialog-close>×</button></div><label class="collection-dialog-field">CSV file<input type="file" name="file" accept=".csv,text/csv" required></label><div class="collection-dialog-actions"><button class="collections-outline-button" type="button" data-dialog-close>Cancel</button><button class="collections-save-settings" type="submit">Import Products</button></div></form></dialog>
@endif

<dialog class="collection-dialog collection-dialog--small" id="collection-bulk-modal"><form class="collection-dialog-form" method="post" action="{{ route('admin.collections.bulk') }}" id="collection-bulk-form">@csrf<div class="collection-dialog-heading"><div><small>BULK ACTIONS</small><h2>Bulk Update Collections</h2><p>Only fields you choose will be changed.</p></div><button class="collection-dialog-close" type="button" data-dialog-close>×</button></div><div data-bulk-selected-inputs></div><div class="collection-form-grid"><label>Status<select name="status"><option value="">Keep current</option>@foreach($collectionStatuses as $option)<option value="{{ $option }}">{{ str($option)->headline() }}</option>@endforeach</select></label><label>Visibility<select name="visibility"><option value="">Keep current</option>@foreach($collectionVisibilities as $option)<option value="{{ $option }}">{{ str($option)->headline() }}</option>@endforeach</select></label><label>Featured<select name="featured"><option value="keep">Keep current</option><option value="yes">Set featured</option><option value="no">Remove featured</option></select></label></div><div class="collection-dialog-actions"><button class="collections-outline-button" type="button" data-dialog-close>Cancel</button><button class="collections-save-settings" type="submit">Apply Bulk Update</button></div></form></dialog>

<dialog class="collection-dialog collection-dialog--small" id="collection-import-modal"><form class="collection-dialog-form" method="post" action="{{ route('admin.collections.import') }}" enctype="multipart/form-data">@csrf<div class="collection-dialog-heading"><div><small>COLLECTION CSV IMPORT</small><h2>Import Collections</h2><p>Required headers: <code>name,type</code>. Optional: slug, season, status, visibility, sort_order and SEO fields.</p></div><button class="collection-dialog-close" type="button" data-dialog-close>×</button></div><label class="collection-dialog-field">CSV file<input type="file" name="file" accept=".csv,text/csv" required></label><div class="collection-dialog-actions"><button class="collections-outline-button" type="button" data-dialog-close>Cancel</button><button class="collections-save-settings" type="submit">Import Collections</button></div></form></dialog>

<dialog class="collection-dialog" id="collection-reorder-modal"><form class="collection-dialog-form" method="post" action="{{ route('admin.collections.reorder') }}">@csrf<div class="collection-dialog-heading"><div><small>MERCHANDISING ORDER</small><h2>Reorder Collections</h2><p>Move collections up or down, then save. The saved order becomes the collection sort order.</p></div><button class="collection-dialog-close" type="button" data-dialog-close>×</button></div><div class="collection-reorder-list" data-reorder-list>@foreach($reorderCollections as $orderCollection)<div class="collection-reorder-row" data-reorder-row><input type="hidden" name="order[]" value="{{ $orderCollection->id }}"><span class="collection-reorder-handle">⋮⋮</span><strong>{{ $orderCollection->name }}</strong><small>#{{ $orderCollection->sort_order }}</small><button type="button" data-move-up aria-label="Move up">↑</button><button type="button" data-move-down aria-label="Move down">↓</button></div>@endforeach</div><div class="collection-dialog-actions"><button class="collections-outline-button" type="button" data-dialog-close>Cancel</button><button class="collections-save-settings" type="submit">Save Order</button></div></form></dialog>
@endsection

@push('scripts')
<script>
(() => {
    const byId = id => document.getElementById(id);
    const dialogs = [...document.querySelectorAll('.collection-dialog')];
    const modal = byId('collection-modal');
    const productsModal = byId('collection-products-modal');
    const collectionForm = byId('collection-form');
    const collectionMethod = byId('collection-form-method');
    const collectionBase = @json(url('/admin/resource/collections'));
    const fields = ['name','slug','type','season','description','image','status','visibility','sort_order','meta_title','meta_description','meta_image'];
    const toast = message => { let el = document.querySelector('.collection-toast'); if (!el) { el = document.createElement('div'); el.className = 'collection-toast'; document.body.appendChild(el); } el.textContent = message; el.classList.add('is-visible'); window.setTimeout(() => el.classList.remove('is-visible'), 2800); };
    const openDialog = dialog => { if (!dialog) return; if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open',''); };
    const closeDialogs = () => dialogs.forEach(dialog => { if (dialog.open && dialog.close) dialog.close(); else dialog.removeAttribute('open'); });
    const setField = (name, value) => { const input = collectionForm?.elements[name]; if (input) input.value = value ?? ''; };

    document.querySelectorAll('[data-auto-filter]').forEach(select => select.addEventListener('change', () => byId('collection-filter-form')?.submit()));

    document.querySelectorAll('[data-collection-create]').forEach(button => button.addEventListener('click', () => {
        if (!collectionForm) return;
        collectionForm.action = collectionBase;
        collectionMethod.value = '';
        byId('collection-modal-title').textContent = 'Add Collection';
        fields.forEach(name => setField(name, name === 'sort_order' ? 0 : ''));
        collectionForm.elements.type.value = 'curated';
        collectionForm.elements.status.value = 'active';
        collectionForm.elements.visibility.value = 'visible';
        collectionForm.elements.allow_in_filters.checked = true;
        collectionForm.elements.is_featured.checked = false;
        collectionForm.elements.show_on_homepage.checked = false;
        if (collectionForm.elements.image_file) collectionForm.elements.image_file.value = '';
        if (collectionForm.elements.meta_image_file) collectionForm.elements.meta_image_file.value = '';
        openDialog(modal);
    }));

    document.querySelectorAll('[data-collection-edit]').forEach(button => button.addEventListener('click', () => {
        if (!collectionForm) return;
        const data = JSON.parse(button.dataset.collectionEdit || button.dataset.collection || '{}');
        collectionForm.action = `${collectionBase}/${data.id}`;
        collectionMethod.value = 'PUT';
        byId('collection-modal-title').textContent = 'Edit Collection';
        fields.forEach(name => setField(name, data[name]));
        ['is_featured','show_on_homepage','allow_in_filters'].forEach(name => collectionForm.elements[name].checked = Boolean(data[name]));
        if (collectionForm.elements.image_file) collectionForm.elements.image_file.value = '';
        if (collectionForm.elements.meta_image_file) collectionForm.elements.meta_image_file.value = '';
        openDialog(modal);
    }));

    document.querySelectorAll('[data-products-open]').forEach(button => button.addEventListener('click', () => openDialog(productsModal)));
    document.querySelectorAll('[data-category-open]').forEach(button => button.addEventListener('click', () => openDialog(byId('collection-category-modal'))));
    document.querySelectorAll('[data-product-import-open]').forEach(button => button.addEventListener('click', () => openDialog(byId('collection-product-import-modal'))));
    document.querySelectorAll('[data-import-open]').forEach(button => button.addEventListener('click', () => openDialog(byId('collection-import-modal'))));
    document.querySelectorAll('[data-reorder-open]').forEach(button => button.addEventListener('click', () => openDialog(byId('collection-reorder-modal'))));
    document.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', closeDialogs));
    dialogs.forEach(dialog => dialog.addEventListener('click', event => { if (event.target === dialog && dialog.close) dialog.close(); }));

    const updateProductCount = () => { if (!productsModal) return; const count = productsModal.querySelectorAll('[name="product_ids[]"]:checked').length; const target = productsModal.querySelector('[data-product-count]'); if (target) target.textContent = `${count} selected`; };
    productsModal?.querySelectorAll('[name="product_ids[]"]').forEach(input => input.addEventListener('change', updateProductCount));
    productsModal?.querySelector('[data-product-search]')?.addEventListener('input', event => { const query = event.target.value.toLowerCase(); productsModal.querySelectorAll('[data-product-option]').forEach(option => option.hidden = !option.dataset.productName.includes(query)); });
    updateProductCount();

    const selectAll = document.querySelector('[data-collections-select-all]');
    const checks = [...document.querySelectorAll('[data-collection-check]')];
    const bulkBar = document.querySelector('[data-bulk-bar]');
    const selectedCount = document.querySelector('[data-selected-count]');
    const selectedIds = () => checks.filter(input => input.checked).map(input => input.value);
    const refreshSelection = () => { const ids = selectedIds(); if (bulkBar) bulkBar.hidden = ids.length === 0; if (selectedCount) selectedCount.textContent = ids.length; if (selectAll) { selectAll.checked = checks.length > 0 && ids.length === checks.length; selectAll.indeterminate = ids.length > 0 && ids.length < checks.length; } };
    selectAll?.addEventListener('change', () => { checks.forEach(input => input.checked = selectAll.checked); refreshSelection(); });
    checks.forEach(input => input.addEventListener('change', refreshSelection));
    document.querySelector('[data-clear-selection]')?.addEventListener('click', () => { checks.forEach(input => input.checked = false); refreshSelection(); });
    document.querySelectorAll('[data-bulk-open]').forEach(button => button.addEventListener('click', () => {
        const ids = selectedIds();
        if (!ids.length) { toast('Select one or more collection rows first.'); return; }
        const target = document.querySelector('[data-bulk-selected-inputs]');
        target.innerHTML = ids.map(id => `<input type="hidden" name="collections[]" value="${id}">`).join('');
        openDialog(byId('collection-bulk-modal'));
    }));
    refreshSelection();

    document.querySelectorAll('[data-share-url]').forEach(button => button.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(button.dataset.shareUrl); toast('Shareable collection link copied.'); }
        catch (_) { window.prompt('Copy this collection link:', button.dataset.shareUrl); }
    }));
    document.querySelectorAll('[data-copy-value]').forEach(button => button.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(button.dataset.copyValue); toast('UUID copied.'); }
        catch (_) { window.prompt('Copy UUID:', button.dataset.copyValue); }
    }));

    const reorderList = document.querySelector('[data-reorder-list]');
    reorderList?.addEventListener('click', event => {
        const row = event.target.closest('[data-reorder-row]');
        if (!row) return;
        if (event.target.closest('[data-move-up]') && row.previousElementSibling) reorderList.insertBefore(row, row.previousElementSibling);
        if (event.target.closest('[data-move-down]') && row.nextElementSibling) reorderList.insertBefore(row.nextElementSibling, row);
    });
})();
</script>
@endpush
