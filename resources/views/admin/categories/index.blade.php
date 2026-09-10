@extends('layouts.admin')

@section('title', 'Categories')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-categories.css') }}?v=20260910-1">
@endpush

@section('content')
@php
    $totalForPercent = max(1, $stats['total']);
    $visiblePercent = round(($stats['visible'] / $totalForPercent) * 100, 1);
    $selectedLevel = 1;
    if ($selected) {
        $cursor = $selected->parent;
        while ($cursor) {
            $selectedLevel++;
            $cursor = $cursor->parent()->first();
        }
    }
@endphp

<div class="cat-page" data-category-page data-reorder-url="{{ route('admin.categories.reorder') }}" data-csrf="{{ csrf_token() }}">
    <div class="cat-page-heading">
        <div>
            <nav class="cat-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Project 1 Control Panel (cPanel)</a>
                <span>•</span><span>Website &amp; Products</span><span>•</span><strong>Categories</strong>
            </nav>
            <h1>Categories</h1>
            <p>Organize your product catalog with intuitive categories and sub-categories.</p>
        </div>
        <div class="cat-date-card">
            <x-icon name="calendar" size="22" />
            <span><small>Today</small><b>{{ now()->format('l, j F Y') }}</b><strong>{{ now()->format('g:i A') }}</strong></span>
        </div>
    </div>

    @if(session('success'))
        <div class="cat-alert cat-alert--success"><x-icon name="check" size="16" /> {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="cat-alert cat-alert--error"><x-icon name="alert" size="16" /><div><strong>Please check the category form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <section class="cat-stat-grid" aria-label="Category statistics">
        <article class="cat-stat-card"><span class="cat-stat-icon cat-stat-icon--green"><x-icon name="package" size="22" /></span><div><small>Total Categories</small><strong>{{ number_format($stats['total']) }}</strong><em>Live database total</em></div></article>
        <article class="cat-stat-card"><span class="cat-stat-icon cat-stat-icon--purple"><x-icon name="users" size="22" /></span><div><small>Level 1 Categories</small><strong>{{ number_format($stats['level_one']) }}</strong><em>Top-level catalog groups</em></div></article>
        <article class="cat-stat-card"><span class="cat-stat-icon cat-stat-icon--orange"><x-icon name="refresh" size="22" /></span><div><small>Total Sub-Categories</small><strong>{{ number_format($stats['subcategories']) }}</strong><em>Nested catalog groups</em></div></article>
        <article class="cat-stat-card"><span class="cat-stat-icon cat-stat-icon--blue"><x-icon name="shopping-bag" size="22" /></span><div><small>Products Mapped</small><strong>{{ number_format($stats['products_mapped']) }}</strong><em>Products assigned to a category</em></div></article>
        <article class="cat-stat-card"><span class="cat-stat-icon cat-stat-icon--teal"><x-icon name="eye" size="22" /></span><div><small>Visible on Website</small><strong>{{ number_format($stats['visible']) }}</strong><em>{{ number_format($visiblePercent, 1) }}% of total</em></div></article>
    </section>

    <div class="cat-layout">
        <main class="cat-main">
            <section class="cat-toolbar-card">
                <form method="GET" action="{{ route('admin.categories.index') }}" class="cat-filter-form">
                    <label class="cat-search"><x-icon name="search" size="15" /><input type="search" name="q" value="{{ $search }}" placeholder="Search categories..."></label>
                    <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected($status==='active')>Active</option><option value="draft" @selected($status==='draft')>Draft</option><option value="inactive" @selected($status==='inactive')>Inactive</option></select>
                    <select name="visibility" aria-label="Filter by visibility"><option value="">All visibility</option><option value="visible" @selected($visibility==='visible')>Visible</option><option value="hidden" @selected($visibility==='hidden')>Hidden</option></select>
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <button type="submit" class="cat-btn"><x-icon name="filter" size="14" /> Filters</button>
                    @if($search || $status || $visibility)<a class="cat-btn cat-btn--ghost" href="{{ route('admin.categories.index') }}">Clear</a>@endif
                </form>
                <div class="cat-toolbar-actions">
                    <button class="cat-btn" type="button" data-expand-all><span>↕</span> Expand All</button>
                    <button class="cat-btn" type="button" data-collapse-all><span>↕</span> Collapse All</button>
                    <button class="cat-btn cat-btn--primary js-add-category" type="button"><x-icon name="plus" size="15" /> Add Category</button>
                </div>
            </section>

            <form id="category-bulk-form" method="POST" action="{{ route('admin.categories.bulk') }}" class="cat-bulk-bar" hidden>
                @csrf
                <strong><span data-selected-count>0</span> selected</strong>
                <select name="status"><option value="">Keep status</option><option value="active">Set Active</option><option value="draft">Set Draft</option><option value="inactive">Set Inactive</option></select>
                <select name="visibility"><option value="">Keep visibility</option><option value="visible">Show on Website</option><option value="hidden">Hide from Website</option></select>
                <button type="submit" class="cat-btn cat-btn--primary">Apply Bulk Update</button>
            </form>

            <section class="cat-tree-card">
                <div class="cat-card-title"><h2>Category Tree</h2><small>Drag sibling rows to reorder. Changes save automatically.</small></div>
                <div class="cat-table-wrap">
                    <table class="cat-table">
                        <thead><tr><th class="cat-check-cell"><input type="checkbox" data-check-all aria-label="Select all visible categories"></th><th>Category Name</th><th>Products</th><th>Status</th><th>Visibility</th><th>Sort Order</th><th>Actions</th></tr></thead>
                        <tbody>
                        @forelse($roots as $category)
                            @include('admin.categories._row', [
                                'category' => $category,
                                'level' => 0,
                                'expanded' => $loop->first,
                                'selected' => $selected,
                                'parentUuid' => '',
                                'loopIndex' => $loop->iteration + (($roots->currentPage() - 1) * $roots->perPage()),
                            ])
                        @empty
                            <tr><td colspan="7" class="cat-empty"><x-icon name="package" size="24" /><strong>No categories found.</strong><span>Add your first category or change the filters.</span></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="cat-pagination-row">
                    <span>Showing {{ number_format($roots->firstItem() ?? 0) }} to {{ number_format($roots->lastItem() ?? 0) }} of {{ number_format($roots->total()) }} top-level categories</span>
                    <div class="cat-pagination">{{ $roots->onEachSide(1)->links() }}</div>
                    <form method="GET" action="{{ route('admin.categories.index') }}"><input type="hidden" name="q" value="{{ $search }}"><input type="hidden" name="status" value="{{ $status }}"><input type="hidden" name="visibility" value="{{ $visibility }}"><select name="per_page" onchange="this.form.submit()"><option value="10" @selected($perPage===10)>10 / page</option><option value="25" @selected($perPage===25)>25 / page</option><option value="50" @selected($perPage===50)>50 / page</option></select></form>
                </div>
            </section>
        </main>

        <aside class="cat-detail-column">
            <section class="cat-detail-card">
                <div class="cat-card-title"><h2>Category Details</h2>@if($selected)<span class="cat-status-pill cat-status-pill--{{ $selected->status }}">{{ str($selected->status)->headline() }}</span>@endif</div>
                @if($selected)
                    <div class="cat-detail-hero"><span><x-icon name="package" size="28" /></span><div><strong>{{ $selected->name }}</strong><small>{{ $selected->slug }}</small></div></div>
                    <dl class="cat-detail-list">
                        <div><dt>Category Name</dt><dd>{{ $selected->name }}</dd></div>
                        <div><dt>Slug</dt><dd>{{ $selected->slug }}</dd></div>
                        <div><dt>Parent Category</dt><dd>{{ $selected->parent?->name ?? '— (Top Level)' }}</dd></div>
                        <div><dt>Level</dt><dd>{{ $selectedLevel }}</dd></div>
                        <div><dt>Products</dt><dd>{{ number_format($selected->products_count) }}</dd></div>
                        <div class="wide"><dt>Description</dt><dd>{{ $selected->description ?: 'No description added.' }}</dd></div>
                        <div class="wide"><dt>Meta Title</dt><dd>{{ $selected->meta_title ?: 'Not set' }}</dd></div>
                        <div class="wide"><dt>Meta Description</dt><dd>{{ $selected->meta_description ?: 'Not set' }}</dd></div>
                        <div><dt>Sort Order</dt><dd>{{ $selected->sort_order }}</dd></div>
                        <div><dt>Visibility</dt><dd><span class="cat-inline-visibility"><x-icon name="eye" size="13" /> {{ $selected->is_visible ? 'Visible' : 'Hidden' }}</span></dd></div>
                        <div><dt>Created On</dt><dd>{{ $selected->created_at?->format('d M Y h:i A') }}</dd></div>
                        <div><dt>Created By</dt><dd>{{ $selected->creator?->name ?? 'System' }}</dd></div>
                        <div><dt>Last Updated</dt><dd>{{ $selected->updated_at?->format('d M Y h:i A') }}</dd></div>
                        <div><dt>Last Updated By</dt><dd>{{ $selected->updater?->name ?? 'System' }}</dd></div>
                    </dl>
                    <div class="cat-detail-actions">
                        <button type="button" class="cat-btn js-edit-category"
                            data-id="{{ $selected->id }}" data-name="{{ $selected->name }}" data-slug="{{ $selected->slug }}" data-parent-id="{{ $selected->parent_id }}" data-status="{{ $selected->status }}" data-visible="{{ $selected->is_visible ? '1' : '0' }}" data-sort-order="{{ $selected->sort_order }}" data-description="{{ $selected->description }}" data-meta-title="{{ $selected->meta_title }}" data-meta-description="{{ $selected->meta_description }}" data-action="{{ route('admin.categories.update', $selected) }}"><x-icon name="pencil" size="14" /> Edit Category</button>
                        <button type="button" class="cat-btn cat-btn--outline js-add-subcategory" data-parent-id="{{ $selected->id }}" data-parent-name="{{ $selected->name }}"><x-icon name="plus" size="14" /> Add Sub-Category</button>
                    </div>
                @else
                    <div class="cat-empty cat-empty--detail"><x-icon name="package" size="28" /><strong>No category selected</strong><span>Create a category to start building the catalog.</span></div>
                @endif
            </section>
        </aside>

        <aside class="cat-right-rail">
            <section class="cat-rail-card">
                <h3>Category Summary</h3>
                <dl><div><dt>Total Categories</dt><dd>{{ number_format($stats['total']) }}</dd></div><div><dt>Top Level Categories</dt><dd>{{ number_format($stats['level_one']) }}</dd></div><div><dt>Sub-Categories</dt><dd>{{ number_format($stats['subcategories']) }}</dd></div><div><dt>Products Mapped</dt><dd>{{ number_format($stats['products_mapped']) }}</dd></div><div><dt>Visible on Website</dt><dd>{{ number_format($stats['visible']) }}</dd></div><div><dt>Hidden from Website</dt><dd>{{ number_format($stats['hidden']) }}</dd></div><div><dt>Draft Categories</dt><dd>{{ number_format($stats['draft']) }}</dd></div><div><dt>Inactive Categories</dt><dd>{{ number_format($stats['inactive']) }}</dd></div></dl>
            </section>

            <section class="cat-rail-card cat-quick-actions">
                <h3>Quick Actions</h3>
                <button type="button" class="js-add-category"><x-icon name="plus" size="14" /> Add Category</button>
                <button type="button" class="js-add-subcategory" @disabled(!$selected) data-parent-id="{{ $selected?->id }}" data-parent-name="{{ $selected?->name }}"><x-icon name="plus" size="14" /> Add Sub-Category</button>
                <button type="button" data-focus-bulk><x-icon name="refresh" size="14" /> Bulk Update Categories</button>
                <button type="button" data-expand-all><x-icon name="refresh" size="14" /> Reorder Categories</button>
                <button type="button" data-import-trigger><x-icon name="upload" size="14" /> Import Categories</button>
                <a href="{{ route('admin.categories.export') }}"><x-icon name="download" size="14" /> Export Categories</a>
                <button type="button" data-guide-trigger><x-icon name="help" size="14" /> Category Management Guide</button>
                <form method="POST" action="{{ route('admin.categories.import') }}" enctype="multipart/form-data" class="cat-import-form" data-import-form>@csrf<input type="file" name="file" accept=".csv,text/csv" hidden data-import-input></form>
            </section>

            <section class="cat-rail-card">
                <h3>Category Visibility</h3>
                <div class="cat-visibility-chart">
                    <div class="cat-donut" style="--visible: {{ $visiblePercent }}"><span><strong>{{ number_format($stats['total']) }}</strong><small>Total</small></span></div>
                    <ul><li><i class="visible"></i><span>Visible</span><strong>{{ number_format($stats['visible']) }} ({{ number_format($visiblePercent,1) }}%)</strong></li><li><i class="hidden"></i><span>Hidden</span><strong>{{ number_format($stats['hidden']) }} ({{ number_format(100-$visiblePercent,1) }}%)</strong></li></ul>
                </div>
            </section>

            <section class="cat-rail-card cat-uuid-card">
                <h3>UUID Traceability</h3>
                <p>Every category is assigned a unique UUID for full traceability.</p>
                @if($selected)
                    <small>Selected Category UUID</small>
                    <div class="cat-uuid"><code>{{ $selected->public_uuid }}</code><button type="button" data-copy="{{ $selected->public_uuid }}" aria-label="Copy category UUID"><x-icon name="copy" size="14" /></button></div>
                    <a class="cat-audit-link" href="{{ route('admin.categories.audit', $selected) }}">View Category Audit Log <span>›</span></a>
                @endif
            </section>
        </aside>
    </div>

    <section class="cat-feature-strip" aria-label="Category management capabilities">
        <article><span><x-icon name="users" size="19" /></span><div><strong>Intuitive Hierarchy</strong><small>Create unlimited levels of categories and sub-categories.</small></div></article>
        <article><span><x-icon name="refresh" size="19" /></span><div><strong>Smart Organization</strong><small>Keep your catalog structured for easy navigation.</small></div></article>
        <article><span><x-icon name="search" size="19" /></span><div><strong>SEO Optimized</strong><small>Manage meta titles, descriptions and website visibility.</small></div></article>
        <article><span><x-icon name="arrow-up" size="19" /></span><div><strong>Drag &amp; Drop Reorder</strong><small>Reorder sibling categories with automatic saving.</small></div></article>
        <article><span><x-icon name="check" size="19" /></span><div><strong>Traceable &amp; Secure</strong><small>UUID-based tracing and audit history for every change.</small></div></article>
    </section>

    <footer class="cat-footer">
        <span>© {{ now()->year }} Emerald Rozalia Ltd. All rights reserved.</span>
        @if(config('app.brand_contact.phone'))<span><x-icon name="phone" size="13" /> {{ config('app.brand_contact.phone') }}</span>@endif
        @if(config('app.brand_contact.email'))<span><x-icon name="mail" size="13" /> {{ config('app.brand_contact.email') }}</span>@endif
        @if(config('app.brand_contact.website'))<span><x-icon name="globe" size="13" /> {{ config('app.brand_contact.website') }}</span>@endif
        <span><x-icon name="globe" size="13" /> {{ config('app.brand_contact.location') ?: 'Limerick, Ireland' }}</span>
    </footer>

    <dialog class="cat-dialog" data-category-dialog>
        <form method="POST" action="{{ route('admin.categories.store') }}" data-category-form>
            @csrf
            <input type="hidden" name="_method" value="POST" data-method-field>
            <div class="cat-dialog-head"><div><small data-dialog-kicker>NEW CATEGORY</small><h2 data-dialog-title>Add Category</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></div>
            <div class="cat-form-grid">
                <label><span>Category Name *</span><input name="name" required maxlength="160" data-field="name"></label>
                <label><span>Slug</span><input name="slug" maxlength="180" placeholder="auto-generated-from-name" data-field="slug"></label>
                <label><span>Parent Category</span><select name="parent_id" data-field="parent_id"><option value="">— Top Level —</option>@foreach($allCategories as $option)<option value="{{ $option->id }}">{{ $option->parent_id ? '↳ ' : '' }}{{ $option->name }}</option>@endforeach</select></label>
                <label><span>Status *</span><select name="status" required data-field="status"><option value="active">Active</option><option value="draft">Draft</option><option value="inactive">Inactive</option></select></label>
                <label><span>Website Visibility *</span><select name="is_visible" required data-field="is_visible"><option value="1">Visible</option><option value="0">Hidden</option></select></label>
                <label><span>Sort Order *</span><input type="number" name="sort_order" min="0" max="100000" value="0" required data-field="sort_order"></label>
                <label class="full"><span>Description</span><textarea name="description" rows="4" maxlength="5000" data-field="description"></textarea></label>
                <label class="full"><span>Meta Title</span><input name="meta_title" maxlength="255" data-field="meta_title"></label>
                <label class="full"><span>Meta Description</span><textarea name="meta_description" rows="3" maxlength="1000" data-field="meta_description"></textarea></label>
            </div>
            <div class="cat-dialog-actions"><button type="button" class="cat-btn" data-dialog-close>Cancel</button><button type="submit" class="cat-btn cat-btn--primary" data-submit-label>Save Category</button></div>
        </form>
    </dialog>

    <dialog class="cat-dialog cat-guide-dialog" data-guide-dialog>
        <div class="cat-dialog-head"><div><small>CATEGORY MANAGEMENT GUIDE</small><h2>How to manage the category tree</h2></div><button type="button" data-guide-close aria-label="Close">×</button></div>
        <div class="cat-guide-content">
            <p><strong>Create hierarchy:</strong> choose a parent when creating a sub-category. Categories can be nested beyond one level.</p>
            <p><strong>Reorder:</strong> drag a category row above or below another row with the same parent. The new sort order is saved automatically.</p>
            <p><strong>Visibility:</strong> hidden categories cannot be opened through public category route binding. Draft and inactive categories are also excluded there.</p>
            <p><strong>CSV import:</strong> required columns are <code>name</code> and <code>slug</code>. Optional columns: <code>parent_slug,status,visibility,sort_order,description,meta_title,meta_description</code>.</p>
            <p><strong>Traceability:</strong> create, edit, visibility, bulk, import, reorder and delete actions are written to the audit log.</p>
        </div>
    </dialog>
</div>

<script src="{{ asset('js/admin-categories.js') }}?v=20260910-1" defer></script>
@endsection
