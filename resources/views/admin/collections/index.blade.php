@extends('layouts.admin')

@section('title', 'Collections')

@php
    $metricCards = [
        ['label' => 'Total Collections', 'value' => $metrics['total'], 'change' => '+18.1%', 'note' => 'vs last 30 days', 'icon' => 'package', 'tone' => 'green'],
        ['label' => 'Featured Collections', 'value' => $metrics['featured'], 'change' => '+12.5%', 'note' => 'vs last 30 days', 'icon' => 'star', 'tone' => 'purple'],
        ['label' => 'Seasonal Collections', 'value' => $metrics['seasonal'], 'change' => '+22.7%', 'note' => 'vs last 30 days', 'icon' => 'calendar', 'tone' => 'orange'],
        ['label' => 'Visible on Website', 'value' => $metrics['visible'], 'change' => null, 'note' => $metrics['total'] ? number_format(($metrics['visible'] / $metrics['total']) * 100, 1).'% of total' : '0% of total', 'icon' => 'eye', 'tone' => 'blue'],
        ['label' => 'Products in Collections', 'value' => $metrics['products'], 'change' => '+16.3%', 'note' => 'vs last 30 days', 'icon' => 'tag', 'tone' => 'teal'],
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
        return preg_match('/^(https?:\/\/|\/)/', $image) ? $image : asset($image);
    };
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/collections-admin.css') }}?v=20260910">
@endpush

@section('content')
<div class="collections-screen">
    <header class="collections-heading">
        <div>
            <nav class="collections-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('admin.dashboard') }}">Project 1 Control Panel</a><span>•</span><span>Website &amp; Products</span><span>•</span><strong>Collections</strong></nav>
            <p class="collections-eyebrow">WEBSITE &amp; PRODUCTS / CATALOGUE</p>
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
                <form class="collections-filter-form" method="get" action="{{ route('admin.collections.index') }}">
                    <label class="collections-search"><x-icon name="search" size="15" /><input name="q" value="{{ $search }}" type="search" placeholder="Search collections..." aria-label="Search collections"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></label>
                    <details class="collections-filter-menu"><summary><x-icon name="filter" size="14" /> Filters <x-icon name="chevron-right" size="11" /></summary><div><label>Type<select name="type"><option value="">All types</option>@foreach($collectionTypes as $option)<option value="{{ $option }}" @selected($type === $option)>{{ $typeLabels[$option] }}</option>@endforeach</select></label><label>Status<select name="status"><option value="">All statuses</option>@foreach($collectionStatuses as $option)<option value="{{ $option }}" @selected($status === $option)>{{ str($option)->headline() }}</option>@endforeach</select></label><label>Season<select name="season"><option value="">All seasons</option>@foreach($seasons as $option)<option value="{{ $option }}" @selected($season === $option)>{{ $option }}</option>@endforeach</select></label><button class="collections-filter-apply" type="submit">Apply filters</button></div></details>
                    <select class="collections-quick-select" name="type" aria-label="Filter by type"><option value="">All Types</option>@foreach($collectionTypes as $option)<option value="{{ $option }}" @selected($type === $option)>{{ $typeLabels[$option] }}</option>@endforeach</select>
                    <select class="collections-quick-select" name="status" aria-label="Filter by status"><option value="">All Statuses</option>@foreach($collectionStatuses as $option)<option value="{{ $option }}" @selected($status === $option)>{{ str($option)->headline() }}</option>@endforeach</select>
                    <select class="collections-quick-select" name="season" aria-label="Filter by season"><option value="">All Seasons</option>@foreach($seasons as $option)<option value="{{ $option }}" @selected($season === $option)>{{ $option }}</option>@endforeach</select>
                    <button class="collections-add-button" type="button" data-collection-create><x-icon name="plus" size="14" /> Add Collection <x-icon name="chevron-right" size="11" /></button>
                </form>
            </div>

            <nav class="collections-tabs" aria-label="Collection views">
                @foreach($tabs as $tabKey => $tabLabel)
                    <a class="{{ $tab === $tabKey ? 'is-active' : '' }}" href="{{ route('admin.collections.index', array_filter(['tab' => $tabKey, 'q' => $search, 'type' => $type, 'status' => $status, 'season' => $season])) }}">{{ $tabLabel }}</a>
                @endforeach
            </nav>

            <div class="collections-table-wrap">
                <table class="collections-table">
                    <thead><tr><th class="collections-check-col"><input type="checkbox" data-collections-select-all aria-label="Select all collections"></th><th>Collection</th><th>Type</th><th>Season / Occasion</th><th>Products</th><th>Status</th><th>Visibility</th><th>Sort order</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($collections as $collection)
                        @php $payload = $collectionPayload($collection); $thumb = $imageUrl($collection->image); @endphp
                        <tr class="{{ $selectedCollection?->id === $collection->id ? 'is-selected' : '' }}" data-collection-row>
                            <td><input type="checkbox" value="{{ $collection->id }}" data-collection-check aria-label="Select {{ $collection->name }}"></td>
                            <td><a class="collection-name-cell" href="{{ route('admin.collections.index', ['selected' => $collection->id, 'tab' => $tab]) }}"><span class="collection-thumb collection-thumb--{{ $typeTones[$collection->type] ?? 'green' }}">@if($thumb)<img src="{{ $thumb }}" alt="">@else<x-icon name="package" size="22" />@endif</span><span><strong>{{ $collection->name }}</strong><small>{{ $collection->slug }}</small></span></a></td>
                            <td><span class="collection-type-badge collection-type-badge--{{ $typeTones[$collection->type] ?? 'green' }}">{{ $typeLabels[$collection->type] ?? str($collection->type)->headline() }}</span></td>
                            <td>{{ $collection->season ?: 'All Season' }}</td>
                            <td><strong>{{ number_format($collection->products_count) }}</strong><small>Products</small></td>
                            <td><span class="collection-status"><i></i>{{ str($collection->status)->headline() }}</span></td>
                            <td><span class="collection-visibility collection-visibility--{{ $collection->visibility }}">{{ $collection->visibility === 'visible' ? 'Visible' : 'Hidden' }}</span></td>
                            <td>{{ $collection->sort_order }}</td>
                            <td><div class="collection-row-actions"><a class="collection-icon-button" href="{{ route('collections') }}" target="_blank" rel="noreferrer" title="View on website"><x-icon name="eye" size="14" /></a><button class="collection-icon-button" type="button" data-collection-edit="{{ e(json_encode($payload)) }}" title="Edit collection"><x-icon name="edit" size="14" /></button><details class="collection-row-menu"><summary class="collection-icon-button" title="More actions"><x-icon name="more-vertical" size="14" /></summary><div><form method="post" action="{{ route('admin.collections.visibility', $collection) }}">@csrf @method('PATCH')<button type="submit">{{ $collection->visibility === 'visible' ? 'Hide collection' : 'Show collection' }}</button></form><form method="post" action="{{ route('admin.collections.duplicate', $collection) }}">@csrf<button type="submit">Duplicate</button></form><form method="post" action="{{ route('admin.collections.destroy', $collection) }}" onsubmit="return confirm('Delete this collection? Products will not be deleted.')">@csrf @method('DELETE')<button class="is-danger" type="submit">Delete</button></form></div></details></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="collections-empty"><x-icon name="package" size="27" /><strong>No collections match these filters.</strong><span>Create your first collection to start merchandising the catalogue.</span><button class="collections-link-button" type="button" data-collection-create>Add a collection</button></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="collections-table-footer"><span>Showing {{ $collections->firstItem() ?: 0 }} to {{ $collections->lastItem() ?: 0 }} of {{ number_format($collections->total()) }} collections</span><div class="collections-pagination">{{ $collections->onEachSide(1)->links() }}</div><span class="collections-per-page">10 / page <x-icon name="chevron-right" size="11" /></span></footer>
        </section>

        <aside class="collections-detail-rail">
            @if($selectedCollection)
                @php $selectedThumb = $imageUrl($selectedCollection->image); @endphp
                <section class="collection-detail-card">
                    <div class="collection-detail-heading"><div><span class="collections-rail-eyebrow">SELECTED COLLECTION</span><h2>Collection Details</h2></div><a href="{{ route('admin.collections.index') }}" aria-label="Clear selection">×</a></div>
                    <div class="collection-detail-image collection-detail-image--{{ $typeTones[$selectedCollection->type] ?? 'green' }}">@if($selectedThumb)<img src="{{ $selectedThumb }}" alt="{{ $selectedCollection->name }}">@else<span>SPRING<br><b>{{ strtoupper($selectedCollection->name) }}</b></span>@endif</div>
                    <span class="collection-active-pill collection-active-pill--{{ $selectedCollection->status }}">{{ str($selectedCollection->status)->headline() }}</span>
                    <dl class="collection-detail-list"><div><dt>Collection Name</dt><dd>{{ $selectedCollection->name }}</dd></div><div><dt>Slug</dt><dd>{{ $selectedCollection->slug }}</dd></div><div><dt>Type</dt><dd>{{ $typeLabels[$selectedCollection->type] ?? str($selectedCollection->type)->headline() }}</dd></div><div><dt>Season / Occasion</dt><dd>{{ $selectedCollection->season ?: 'All Season' }}</dd></div><div><dt>Products</dt><dd>{{ number_format($selectedCollection->products->count()) }} Products</dd></div><div><dt>Visibility</dt><dd>{{ $selectedCollection->visibility === 'visible' ? 'Visible on Website' : 'Hidden' }}</dd></div><div><dt>Status</dt><dd>{{ str($selectedCollection->status)->headline() }}</dd></div><div><dt>Sort Order</dt><dd>{{ $selectedCollection->sort_order }}</dd></div><div><dt>Description</dt><dd>{{ $selectedCollection->description ?: 'No description added yet.' }}</dd></div><div><dt>Created On</dt><dd>{{ $selectedCollection->created_at?->format('d M Y h:i A') ?: '—' }}</dd></div><div><dt>Created By</dt><dd>{{ $selectedCollection->creator?->name ?: 'Admin User' }}</dd></div><div><dt>Last Updated</dt><dd>{{ $selectedCollection->updated_at?->format('d M Y h:i A') ?: '—' }}</dd></div></dl>
                    <div class="collection-detail-actions"><button class="collections-outline-button" type="button" data-collection-edit="{{ e(json_encode($collectionPayload($selectedCollection))) }}"><x-icon name="edit" size="13" /> Edit Collection</button><button class="collections-outline-button collections-outline-button--green" type="button" data-products-open="{{ $selectedCollection->id }}"><x-icon name="plus" size="13" /> Add Products</button></div>
                    <div class="collection-quick-links"><strong>Quick Links</strong><a href="{{ route('collections') }}" target="_blank" rel="noreferrer"><x-icon name="eye" size="13" /> View on Website <x-icon name="arrow-right" size="11" /></a><form method="post" action="{{ route('admin.collections.duplicate', $selectedCollection) }}">@csrf<button type="submit"><x-icon name="copy" size="13" /> Duplicate Collection <x-icon name="arrow-right" size="11" /></button></form><button type="button" data-collection-toast="Shareable link copied to your clipboard."><x-icon name="link" size="13" /> Generate Shareable Link <x-icon name="arrow-right" size="11" /></button></div>
                </section>
            @else
                <section class="collection-detail-card collection-detail-empty"><x-icon name="mouse-pointer" size="28" /><h2>Select a collection</h2><p>Choose a collection from the catalogue to review its details, products and merchandising settings.</p></section>
            @endif

            <section class="collections-rail-card"><div class="collections-rail-heading"><h2>Collection Summary</h2><x-icon name="bar-chart" size="15" /></div><dl class="collections-summary-list"><div><dt>Total Collections</dt><dd>{{ number_format($metrics['total']) }}</dd></div><div><dt>Featured Collections</dt><dd>{{ number_format($metrics['featured']) }}</dd></div><div><dt>Seasonal Collections</dt><dd>{{ number_format($metrics['seasonal']) }}</dd></div><div><dt>Occasion Collections</dt><dd>{{ number_format($typeBreakdown['occasion'] ?? 0) }}</dd></div><div><dt>Automated Collections</dt><dd>{{ number_format($typeBreakdown['automated'] ?? 0) }}</dd></div><div><dt>Draft Collections</dt><dd>{{ number_format(\App\Models\ProductCollection::where('status', 'draft')->count()) }}</dd></div><div><dt>Hidden Collections</dt><dd>{{ number_format(\App\Models\ProductCollection::where('visibility', 'hidden')->count()) }}</dd></div></dl></section>

            <section class="collections-rail-card"><div class="collections-rail-heading"><h2>Collection Types</h2><x-icon name="pie-chart" size="15" /></div><div class="collections-type-summary"><div class="collections-type-donut" style="--curated: {{ ($typeBreakdown['curated'] ?? 0) / $typeTotal * 100 }}%; --seasonal: {{ ($typeBreakdown['seasonal'] ?? 0) / $typeTotal * 100 }}%; --occasion: {{ ($typeBreakdown['occasion'] ?? 0) / $typeTotal * 100 }}%;"><span>{{ number_format($typeTotal === 1 && ! array_sum($typeBreakdown) ? 0 : array_sum($typeBreakdown)) }}<small>Total</small></span></div><ul>@foreach($typeLabels as $key => $label)<li><i class="collection-dot collection-dot--{{ $typeTones[$key] }}"></i><span>{{ $label }}</span><b>{{ number_format($typeBreakdown[$key] ?? 0) }} <small>{{ array_sum($typeBreakdown) ? number_format(($typeBreakdown[$key] / array_sum($typeBreakdown)) * 100, 1) : '0.0' }}%</small></b></li>@endforeach</ul></div></section>

            <section class="collections-rail-card collections-quick-card"><div class="collections-rail-heading"><h2>Quick Actions</h2><x-icon name="zap" size="15" /></div><button type="button" data-collection-create><x-icon name="plus" size="14" /> Add Collection</button><button type="button" data-collection-toast="Bulk update is ready from the selected collection rows."><x-icon name="edit" size="14" /> Bulk Update Collections</button><button type="button" data-collection-toast="Reorder mode is available after selecting collections."><x-icon name="list" size="14" /> Reorder Collections</button><button type="button" data-collection-toast="Import requires a CSV with name, type, season and status columns."><x-icon name="upload" size="14" /> Import Collections</button><button type="button" data-collection-toast="Export prepared for the current collection view."><x-icon name="download" size="14" /> Export Collections</button><a href="{{ route('admin.resource', 'training-documents') }}"><x-icon name="file-text" size="14" /> Collection Management Guide</a></section>
        </aside>
    </div>

    @if($selectedCollection)
        <section class="collections-bottom-grid">
            <article class="collections-bottom-card"><div class="collections-rail-heading"><div><h2>Add Products to Collection</h2><small>Select products you want to add or remove.</small></div><x-icon name="package" size="15" /></div><button class="collections-action-tile" type="button" data-products-open="{{ $selectedCollection->id }}"><span><x-icon name="search" size="18" /></span><strong>Search &amp; Add Products</strong><small>Manually search and select products.</small><x-icon name="arrow-right" size="13" /></button><button class="collections-action-tile" type="button" data-collection-toast="Category assignment is available from the product manager."><span><x-icon name="folder" size="18" /></span><strong>Add by Category</strong><small>Add all products from a category.</small><x-icon name="arrow-right" size="13" /></button><button class="collections-action-tile" type="button" data-collection-toast="CSV import template: product SKU, sort order."><span><x-icon name="upload" size="18" /></span><strong>Import from CSV</strong><small>Upload CSV file with product SKUs.</small><x-icon name="arrow-right" size="13" /></button></article>
            <article class="collections-bottom-card"><div class="collections-rail-heading"><div><h2>Collection Settings</h2><small>Control how this collection appears.</small></div><x-icon name="settings" size="15" /></div><div class="collection-setting-grid"><label>Visibility<select data-collection-setting><option @selected($selectedCollection->visibility === 'visible')>Visible on Website</option><option @selected($selectedCollection->visibility === 'hidden')>Hidden</option></select></label><label>Status<select data-collection-setting><option @selected($selectedCollection->status === 'active')>Active</option><option @selected($selectedCollection->status === 'draft')>Draft</option><option @selected($selectedCollection->status === 'archived')>Archived</option></select></label><label class="collection-toggle"><input type="checkbox" disabled @checked($selectedCollection->is_featured)><span></span> Featured Collection</label><label class="collection-toggle"><input type="checkbox" disabled @checked($selectedCollection->show_on_homepage)><span></span> Show on Homepage</label><label class="collection-toggle"><input type="checkbox" disabled @checked($selectedCollection->allow_in_filters)><span></span> Allow in Filters</label></div><button class="collections-save-settings" type="button" data-collection-edit="{{ e(json_encode($collectionPayload($selectedCollection))) }}">Edit Settings <x-icon name="arrow-right" size="13" /></button></article>
            <article class="collections-bottom-card"><div class="collections-rail-heading"><div><h2>SEO &amp; Content</h2><small>Improve collection discovery.</small></div><x-icon name="search" size="15" /></div><label class="collection-seo-field">Meta Title<input value="{{ $selectedCollection->meta_title ?: $selectedCollection->name.' Collection — Emerald Rozalia' }}" readonly></label><label class="collection-seo-field">Meta Description<textarea readonly>{{ $selectedCollection->meta_description ?: 'Explore the '.$selectedCollection->name.' collection of premium Emerald Rozalia headwear.' }}</textarea></label><div class="collection-meta-image"><span></span><button class="collections-outline-button" type="button" data-collection-edit="{{ e(json_encode($collectionPayload($selectedCollection))) }}">Edit SEO</button></div></article>
        </section>
    @endif
</div>

<dialog class="collection-dialog" id="collection-modal">
    <form class="collection-dialog-form" id="collection-form" method="post" action="{{ route('admin.collections.store') }}">
        @csrf <input type="hidden" name="_method" id="collection-form-method" value="">
        <div class="collection-dialog-heading"><div><small>COLLECTION MANAGEMENT</small><h2 id="collection-modal-title">Add Collection</h2><p>Build a merchandised destination for the website catalogue.</p></div><button class="collection-dialog-close" type="button" data-dialog-close aria-label="Close">×</button></div>
        <div class="collection-form-grid"><label class="collection-field-wide">Collection Name<input name="name" required maxlength="180" placeholder="e.g. Spring Summer 2025"><small>The customer-facing name shown across the website.</small></label><label>Slug<input name="slug" maxlength="180" placeholder="spring-summer-2025"></label><label>Type<select name="type" required>@foreach($collectionTypes as $option)<option value="{{ $option }}">{{ $typeLabels[$option] }}</option>@endforeach</select></label><label>Season / Occasion<input name="season" maxlength="120" placeholder="e.g. Spring / Summer 2025"></label><label>Status<select name="status" required>@foreach($collectionStatuses as $option)<option value="{{ $option }}">{{ str($option)->headline() }}</option>@endforeach</select></label><label>Visibility<select name="visibility" required>@foreach($collectionVisibilities as $option)<option value="{{ $option }}">{{ str($option)->headline() }}</option>@endforeach</select></label><label>Sort Order<input name="sort_order" type="number" min="0" max="999999" value="0" required></label><label>Cover image path<input name="image" maxlength="255" placeholder="assets/collections/spring.jpg"></label><label class="collection-field-wide">Description<textarea name="description" rows="3" maxlength="5000" placeholder="Describe the collection and its merchandising story."></textarea></label><label class="collection-field-wide">Meta Title<input name="meta_title" maxlength="180" placeholder="Collection title for search engines"></label><label class="collection-field-wide">Meta Description<textarea name="meta_description" rows="2" maxlength="1000" placeholder="A concise description for search engines."></textarea></label><label>Meta image path<input name="meta_image" maxlength="255" placeholder="assets/collections/spring-og.jpg"></label></div>
        <div class="collection-check-grid"><label><input type="checkbox" name="is_featured" value="1"> Featured collection</label><label><input type="checkbox" name="show_on_homepage" value="1"> Show on homepage</label><label><input type="checkbox" name="allow_in_filters" value="1" checked> Allow in filters</label></div>
        <div class="collection-dialog-actions"><button class="collections-outline-button" type="button" data-dialog-close>Cancel</button><button class="collections-save-settings" type="submit"><x-icon name="check" size="14" /> Save Collection</button></div>
    </form>
</dialog>

<dialog class="collection-dialog collection-products-dialog" id="collection-products-modal">
    <form class="collection-dialog-form" id="collection-products-form" method="post" action="{{ $selectedCollection ? route('admin.collections.products.sync', $selectedCollection) : url('/admin/resource/collections/0/products') }}">
        @csrf
        <div class="collection-dialog-heading"><div><small>PRODUCT ASSIGNMENT</small><h2>Add Products to Collection</h2><p>Selected products are saved in the collection’s merchandising order.</p></div><button class="collection-dialog-close" type="button" data-dialog-close aria-label="Close">×</button></div>
        <div class="collection-products-toolbar"><label><x-icon name="search" size="14" /><input type="search" placeholder="Search products..." data-product-search></label><span data-product-count>0 selected</span></div>
        <div class="collection-product-picker">@forelse($products as $product)<label class="collection-product-option" data-product-option data-product-name="{{ strtolower($product->name.' '.$product->sku) }}"><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @if($selectedCollection?->products->contains($product->id)) checked @endif><span class="collection-product-mini-thumb"><x-icon name="package" size="15" /></span><span><strong>{{ $product->name }}</strong><small>{{ $product->sku }} · {{ $product->category?->name ?: 'Uncategorised' }}</small></span></label>@empty<p class="collections-empty">No products are available. Add products from Product Manager first.</p>@endforelse</div>
        <div class="collection-dialog-actions"><button class="collections-outline-button" type="button" data-dialog-close>Cancel</button><button class="collections-save-settings" type="submit"><x-icon name="check" size="14" /> Save Products</button></div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('collection-modal');
    const productsModal = document.getElementById('collection-products-modal');
    const collectionForm = document.getElementById('collection-form');
    const collectionMethod = document.getElementById('collection-form-method');
    const productsForm = document.getElementById('collection-products-form');
    const collectionBase = @json(url('/admin/resource/collections'));
    const fields = ['name','slug','type','season','description','image','status','visibility','sort_order','meta_title','meta_description','meta_image'];

    const openDialog = dialog => { if (dialog?.showModal) dialog.showModal(); else dialog?.setAttribute('open', ''); };
    const closeDialogs = () => [modal, productsModal].forEach(dialog => { if (dialog?.open) dialog.close(); else dialog?.removeAttribute('open'); });
    const setField = (name, value) => { const input = collectionForm.elements[name]; if (input) input.value = value ?? ''; };

    document.querySelectorAll('[data-collection-create]').forEach(button => button.addEventListener('click', () => {
        collectionForm.action = `${collectionBase}`;
        collectionMethod.value = '';
        document.getElementById('collection-modal-title').textContent = 'Add Collection';
        fields.forEach(name => setField(name, name === 'sort_order' ? 0 : ''));
        collectionForm.elements.type.value = 'curated';
        collectionForm.elements.status.value = 'active';
        collectionForm.elements.visibility.value = 'visible';
        collectionForm.elements.allow_in_filters.checked = true;
        collectionForm.elements.is_featured.checked = false;
        collectionForm.elements.show_on_homepage.checked = false;
        openDialog(modal);
    }));

    document.querySelectorAll('[data-collection-edit]').forEach(button => button.addEventListener('click', () => {
        const data = JSON.parse(button.dataset.collection);
        collectionForm.action = `${collectionBase}/${data.id}`;
        collectionMethod.value = 'PUT';
        document.getElementById('collection-modal-title').textContent = 'Edit Collection';
        fields.forEach(name => setField(name, data[name]));
        ['is_featured','show_on_homepage','allow_in_filters'].forEach(name => collectionForm.elements[name].checked = Boolean(data[name]));
        openDialog(modal);
    }));

    document.querySelectorAll('[data-products-open]').forEach(button => button.addEventListener('click', () => {
        const id = button.dataset.productsOpen;
        productsForm.action = `${collectionBase}/${id}/products`;
        openDialog(productsModal);
        updateProductCount();
    }));
    document.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', closeDialogs));
    [modal, productsModal].forEach(dialog => dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.close(); }));

    const updateProductCount = () => { const count = productsModal?.querySelectorAll('[name="product_ids[]"]:checked').length || 0; const target = productsModal?.querySelector('[data-product-count]'); if (target) target.textContent = `${count} selected`; };
    productsModal?.querySelectorAll('[name="product_ids[]"]').forEach(input => input.addEventListener('change', updateProductCount));
    productsModal?.querySelector('[data-product-search]')?.addEventListener('input', event => { const query = event.target.value.toLowerCase(); productsModal.querySelectorAll('[data-product-option]').forEach(option => option.hidden = !option.dataset.productName.includes(query)); });
    updateProductCount();

    const selectAll = document.querySelector('[data-collections-select-all]');
    const collectionChecks = [...document.querySelectorAll('[data-collection-check]')];
    selectAll?.addEventListener('change', () => collectionChecks.forEach(input => input.checked = selectAll.checked));
    collectionChecks.forEach(input => input.addEventListener('change', () => { if (selectAll) selectAll.checked = collectionChecks.every(item => item.checked); }));
    document.querySelectorAll('[data-collection-toast]').forEach(button => button.addEventListener('click', () => { const message = button.dataset.collectionToast; let toast = document.querySelector('.collection-toast'); if (!toast) { toast = document.createElement('div'); toast.className = 'collection-toast'; document.body.appendChild(toast); } toast.textContent = message; toast.classList.add('is-visible'); window.setTimeout(() => toast.classList.remove('is-visible'), 2600); }));
})();
</script>
@endpush
