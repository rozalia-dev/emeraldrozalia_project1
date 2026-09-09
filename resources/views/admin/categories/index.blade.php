@extends('layouts.admin')
@section('title','Categories')
@push('styles')<link rel="stylesheet" href="/css/categories.css">@endpush
@push('scripts')<script src="/js/categories-admin.js" defer></script>@endpush
@section('content')
<div class="cg" data-category-dashboard data-reorder-url="{{ route('admin.categories.reorder') }}" data-csrf="{{ csrf_token() }}">
    <header class="cg-heading">
        <div>
            <p class="cg-breadcrumb">Website &amp; Products › Categories</p>
            <h1>Categories</h1>
            <p>Organize your product catalog with intuitive categories and sub-categories.</p>
        </div>
        <section class="cg-date"><x-icon name="calendar" size="25" /><div><strong>Today</strong><small>{{ now()->format('l, j F Y') }}</small><b>{{ now()->format('g:i A') }}</b></div></section>
    </header>

    @if(session('success'))<p class="cg-notice" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="cg-errors" role="alert"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="cg-kpis" aria-label="Category statistics">
        @foreach([
            ['grid','Total Categories',$stats['total'],'green'],
            ['layers','Level 1 Categories',$stats['top_level'],'purple'],
            ['git-branch','Total Sub-Categories',$stats['subcategories'],'orange'],
            ['shopping-bag','Products Mapped',number_format($stats['products_mapped']),'blue'],
            ['eye','Visible on Website',$stats['visible'],'teal'],
        ] as [$icon,$label,$value,$tone])
        <article><span class="cg-kpi-icon {{ $tone }}"><x-icon :name="$icon" size="22" /></span><div><small>{{ $label }}</small><strong>{{ $value }}</strong>@if($label==='Visible on Website')<em>{{ $stats['total']?number_format(100*$stats['visible']/$stats['total'],1):'0.0' }}% of total</em>@else<em>Live catalog data</em>@endif</div></article>
        @endforeach
    </section>

    <section class="cg-toolbar">
        <form method="get" action="{{ route('admin.categories.index') }}" class="cg-search-form">
            <label class="cg-search"><span class="cg-sr">Search categories</span><input name="q" value="{{ $search }}" placeholder="Search categories…"><x-icon name="search" size="16" /></label>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select>
            <select name="visibility" aria-label="Filter by visibility"><option value="">All visibility</option><option value="visible" @selected($visibility==='visible')>Visible</option><option value="hidden" @selected($visibility==='hidden')>Hidden</option></select>
            <button class="cg-btn cg-outline"><x-icon name="filter" size="15" /> Filters</button>
            <a href="{{ route('admin.categories.index') }}" class="cg-link">Reset</a>
        </form>
        <div class="cg-toolbar-actions">
            <button type="button" class="cg-btn cg-outline" data-expand-all>↕ Expand All</button>
            <button type="button" class="cg-btn cg-outline" data-collapse-all>↕ Collapse All</button>
            <button type="button" class="cg-btn" data-create>＋ Add Category</button>
        </div>
    </section>

    <div class="cg-layout">
        <main class="cg-main">
            <section class="cg-card cg-tree-card">
                <h2>Category Tree</h2>
                <form method="post" action="{{ route('admin.categories.bulk') }}" data-bulk-form>@csrf
                    <div class="cg-table-wrap"><table class="cg-table">
                        <thead><tr><th><input type="checkbox" data-select-all aria-label="Select all visible categories"></th><th>Category Name</th><th>Products</th><th>Status</th><th>Visibility</th><th>Sort Order</th><th>Actions</th></tr></thead>
                        <tbody data-category-tree>
                        @forelse($treeRows as $category)
                            <tr draggable="true" data-category-row data-id="{{ $category->id }}" data-parent="{{ $category->parent_id ?: 0 }}" data-depth="{{ $category->tree_depth }}" @class(['selected'=>$selected?->id===$category->id])>
                                <td><input type="checkbox" name="ids[]" value="{{ $category->id }}" aria-label="Select {{ $category->name }}"></td>
                                <td>
                                    <div class="cg-category-name" style="--depth:{{ $category->tree_depth }}">
                                        @if($treeRows->contains(fn($row)=>$row->parent_id===$category->id))<button type="button" class="cg-toggle" data-toggle-branch="{{ $category->id }}" aria-label="Toggle {{ $category->name }}">⌄</button>@else<span class="cg-branch-space"></span>@endif
                                        <span class="cg-folder"><x-icon name="folder" size="15" /></span>
                                        <a href="{{ route('admin.categories.index',array_merge(request()->except('page'),['selected'=>$category->uuid])) }}">{{ $category->name }}</a>
                                    </div>
                                </td>
                                <td>{{ number_format($category->mapped_products) }}</td>
                                <td><span class="cg-status {{ $category->status }}">● {{ $statuses[$category->status] ?? ucfirst($category->status) }}</span></td>
                                <td><span class="cg-visibility {{ $category->is_visible?'visible':'hidden' }}">{{ $category->is_visible?'Visible':'Hidden' }}</span></td>
                                <td>{{ $category->sort_order }}</td>
                                <td><button type="button" class="cg-icon-btn" data-row-menu="{{ $category->uuid }}" aria-label="Actions for {{ $category->name }}">⋮</button><div class="cg-row-menu" data-menu="{{ $category->uuid }}"><a href="{{ route('admin.categories.index',array_merge(request()->except('page'),['selected'=>$category->uuid])) }}">View details</a><button type="button" data-edit="{{ $category->uuid }}">Edit</button><button type="button" data-create-parent="{{ $category->id }}">Add sub-category</button></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="cg-empty"><x-icon name="folder" size="34" /><h3>No categories found</h3><p>Create your first category or reset the current filters.</p><button type="button" class="cg-btn" data-create>Add Category</button></div></td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                    <div class="cg-table-footer">
                        <span>Showing {{ $rootPage->firstItem()??0 }} to {{ $rootPage->lastItem()??0 }} of {{ $rootPage->total() }} top-level categories</span>
                        <nav class="cg-pagination" aria-label="Category pages">
                            @if($rootPage->previousPageUrl())<a href="{{ $rootPage->previousPageUrl() }}">‹</a>@endif
                            @if($pageStart>1)<a href="{{ $rootPage->url(1) }}">1</a>@if($pageStart>2)<span>…</span>@endif @endif
                            @for($p=$pageStart;$p<=$pageEnd;$p++)<a href="{{ $rootPage->url($p) }}" @class(['active'=>$p===$rootPage->currentPage()])>{{ $p }}</a>@endfor
                            @if($pageEnd<$rootPage->lastPage())@if($pageEnd<$rootPage->lastPage()-1)<span>…</span>@endif<a href="{{ $rootPage->url($rootPage->lastPage()) }}">{{ $rootPage->lastPage() }}</a>@endif
                            @if($rootPage->nextPageUrl())<a href="{{ $rootPage->nextPageUrl() }}">›</a>@endif
                        </nav>
                        <label><span class="cg-sr">Rows per page</span><select data-per-page>@foreach([10,20,40] as $n)<option value="{{ $n }}" @selected($perPage===$n)>{{ $n }} / page</option>@endforeach</select></label>
                    </div>
                    <div class="cg-bulk"><select name="action"><option value="visible">Show on website</option><option value="hidden">Hide from website</option><option value="active">Set active</option><option value="draft">Move to draft</option><option value="inactive">Set inactive</option><option value="delete">Delete</option></select><button class="cg-btn cg-outline">Apply to selected</button><small data-selection>0 selected</small></div>
                </form>
            </section>

            <section class="cg-card cg-details" data-category-details>
                <h2>Category Details</h2>
                @if($selected)
                    <div class="cg-detail-top"><span class="cg-detail-icon"><x-icon name="folder" size="28" /></span><span class="cg-status {{ $selected->status }}">{{ $statuses[$selected->status] ?? ucfirst($selected->status) }}</span></div>
                    <dl>
                        <dt>Category Name</dt><dd>{{ $selected->name }}</dd>
                        <dt>Slug</dt><dd>{{ $selected->slug }}</dd>
                        <dt>Parent Category</dt><dd>{{ $selected->parent?->name ?? '— (Top Level)' }}</dd>
                        <dt>Level</dt><dd>{{ $selected->tree_depth + 1 }}</dd>
                        <dt>Products</dt><dd>{{ number_format($selected->mapped_products) }}</dd>
                        <dt>Description</dt><dd>{{ $selected->description ?: '—' }}</dd>
                        <dt>Meta Title</dt><dd>{{ $selected->meta_title ?: '—' }}</dd>
                        <dt>Meta Description</dt><dd>{{ $selected->meta_description ?: '—' }}</dd>
                        <dt>Sort Order</dt><dd>{{ $selected->sort_order }}</dd>
                        <dt>Visibility</dt><dd>{{ $selected->is_visible?'◉ Visible':'◌ Hidden' }}</dd>
                        <dt>Created On</dt><dd>{{ $selected->created_at?->format('d M Y h:i A') }}</dd>
                        <dt>Created By</dt><dd>{{ $selected->creator?->name ?? 'System' }}</dd>
                        <dt>Last Updated</dt><dd>{{ $selected->updated_at?->format('d M Y h:i A') }}</dd>
                        <dt>Last Updated By</dt><dd>{{ $selected->updater?->name ?? 'System' }}</dd>
                    </dl>
                    <div class="cg-detail-actions"><button class="cg-btn cg-outline" type="button" data-edit="{{ $selected->uuid }}">✎ Edit Category</button><button class="cg-btn cg-outline" type="button" data-create-parent="{{ $selected->id }}">＋ Add Sub-Category</button></div>
                @else<p>Select a category to see its details.</p>@endif
            </section>
        </main>

        <aside class="cg-sidebar">
            <section class="cg-card"><h2>Category Summary</h2><dl class="cg-summary"><dt>Total Categories</dt><dd>{{ $stats['total'] }}</dd><dt>Top Level Categories</dt><dd>{{ $stats['top_level'] }}</dd><dt>Sub-Categories</dt><dd>{{ $stats['subcategories'] }}</dd><dt>Products Mapped</dt><dd>{{ number_format($stats['products_mapped']) }}</dd><dt>Visible on Website</dt><dd>{{ $stats['visible'] }}</dd><dt>Hidden from Website</dt><dd>{{ $stats['hidden'] }}</dd><dt>Draft Categories</dt><dd>{{ $stats['draft'] }}</dd><dt>Inactive Categories</dt><dd>{{ $stats['inactive'] }}</dd></dl></section>
            <section class="cg-card cg-quick"><h2>Quick Actions</h2><button type="button" data-create>↥ Add Category</button><button type="button" data-create-parent="{{ $selected?->id }}">⊕ Add Sub-Category</button><button type="button" data-bulk-focus>▣ Bulk Update Categories</button><button type="button" data-reorder-mode>↕ Reorder Categories</button><button type="button" data-import>⇩ Import Categories</button><a href="{{ route('admin.categories.export') }}">⇧ Export Categories</a><button type="button" data-guide>▤ Category Management Guide</button></section>
            <section class="cg-card"><h2>Category Visibility</h2><div class="cg-donut-wrap"><div class="cg-legend"><p><i class="visible"></i>Visible <strong>{{ $stats['visible'] }} ({{ $stats['total']?number_format(100*$stats['visible']/$stats['total'],1):0 }}%)</strong></p><p><i class="hidden"></i>Hidden <strong>{{ $stats['hidden'] }} ({{ $stats['total']?number_format(100*$stats['hidden']/$stats['total'],1):0 }}%)</strong></p></div><div class="cg-donut" style="--visible:{{ $stats['total']?360*$stats['visible']/$stats['total']:0 }}deg"><span><strong>{{ $stats['total'] }}</strong><small>Total</small></span></div></div></section>
            <section class="cg-card"><h2>UUID Traceability</h2><p>Every category is assigned a unique UUID for full traceability.</p>@if($selected)<small>Selected Category UUID</small><div class="cg-uuid"><code>{{ $selected->uuid }}</code><button type="button" data-copy="{{ $selected->uuid }}">⧉</button></div><button class="cg-btn cg-outline cg-wide" type="button" data-audit="{{ route('admin.categories.audit',$selected) }}">View Category Audit Log ›</button>@endif</section>
        </aside>
    </div>

    <section class="cg-benefits"><article><span>◎</span><div><strong>Intuitive Hierarchy</strong><p>Create unlimited levels of categories and sub-categories.</p></div></article><article><span>⌘</span><div><strong>Smart Organization</strong><p>Keep your catalog structured for easy navigation.</p></div></article><article><span>◇</span><div><strong>SEO Optimized</strong><p>Manage meta titles, descriptions and website visibility.</p></div></article><article><span>↕</span><div><strong>Drag &amp; Drop Reorder</strong><p>Reorder categories quickly while keeping hierarchy safe.</p></div></article><article><span>⌁</span><div><strong>Traceable &amp; Secure</strong><p>UUID based tracking and audit logs for every category change.</p></div></article></section>

    <dialog class="cg-dialog" id="cg-create"><div class="cg-dialog-head"><h2>Add Category</h2><button type="button" data-close>×</button></div><form method="post" action="{{ route('admin.categories.store') }}" class="cg-form">@csrf
        <label>Category Name<input name="name" required maxlength="160"></label><label>Slug<input name="slug" maxlength="180" placeholder="generated-from-name"></label>
        <label>Parent Category<select name="parent_id" data-parent-select><option value="">— Top Level —</option>@foreach($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->name }}</option>@endforeach</select></label>
        <div class="cg-form-grid"><label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="inactive">Inactive</option></select></label><label>Visibility<select name="is_visible"><option value="1">Visible</option><option value="0">Hidden</option></select></label><label>Sort Order<input type="number" name="sort_order" value="0" min="0"></label></div>
        <label>Description<textarea name="description" rows="3" maxlength="5000"></textarea></label><label>Meta Title<input name="meta_title" maxlength="255"></label><label>Meta Description<textarea name="meta_description" rows="2" maxlength="1000"></textarea></label>
        <div class="cg-form-actions"><button type="button" class="cg-btn cg-outline" data-close>Cancel</button><button class="cg-btn">Save Category</button></div></form></dialog>

    @if($selected)<dialog class="cg-dialog" id="cg-edit"><div class="cg-dialog-head"><h2>Edit Category</h2><button type="button" data-close>×</button></div><form method="post" action="{{ route('admin.categories.update',$selected) }}" class="cg-form">@csrf @method('PATCH')
        <label>Category Name<input name="name" required maxlength="160" value="{{ $selected->name }}"></label><label>Slug<input name="slug" required maxlength="180" value="{{ $selected->slug }}"></label>
        <label>Parent Category<select name="parent_id"><option value="">— Top Level —</option>@foreach($parents as $parent)@if($parent->id!==$selected->id)<option value="{{ $parent->id }}" @selected($selected->parent_id===$parent->id)>{{ $parent->name }}</option>@endif @endforeach</select></label>
        <div class="cg-form-grid"><label>Status<select name="status">@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected($selected->status===$key)>{{ $label }}</option>@endforeach</select></label><label>Visibility<select name="is_visible"><option value="1" @selected($selected->is_visible)>Visible</option><option value="0" @selected(!$selected->is_visible)>Hidden</option></select></label><label>Sort Order<input type="number" name="sort_order" value="{{ $selected->sort_order }}" min="0"></label></div>
        <label>Description<textarea name="description" rows="3">{{ $selected->description }}</textarea></label><label>Meta Title<input name="meta_title" value="{{ $selected->meta_title }}"></label><label>Meta Description<textarea name="meta_description" rows="2">{{ $selected->meta_description }}</textarea></label>
        <div class="cg-form-actions"><button type="button" class="cg-btn cg-outline" data-close>Cancel</button><button class="cg-btn">Save Changes</button></div></form></dialog>@endif

    <dialog class="cg-dialog" id="cg-import"><div class="cg-dialog-head"><h2>Import Categories</h2><button type="button" data-close>×</button></div><form method="post" enctype="multipart/form-data" action="{{ route('admin.categories.import') }}" class="cg-form">@csrf<p>CSV columns: name, slug, parent_slug, status, visibility, sort_order, description, meta_title, meta_description.</p><label>CSV File<input type="file" name="file" accept=".csv,text/csv" required></label><div class="cg-form-actions"><button type="button" class="cg-btn cg-outline" data-close>Cancel</button><button class="cg-btn">Import Categories</button></div></form></dialog>
    <dialog class="cg-dialog" id="cg-audit"><div class="cg-dialog-head"><h2>Category Audit Log</h2><button type="button" data-close>×</button></div><pre class="cg-audit-output" data-audit-output role="status"></pre></dialog>
    <dialog class="cg-dialog" id="cg-guide"><div class="cg-dialog-head"><h2>Category Management Guide</h2><button type="button" data-close>×</button></div><div class="cg-guide"><p><strong>Hierarchy:</strong> choose a parent when creating or editing a sub-category. Circular hierarchy is blocked.</p><p><strong>Visibility:</strong> Visible + Active categories can be surfaced on the website. Hidden and draft categories remain in the control panel.</p><p><strong>Reorder:</strong> drag categories within the same hierarchy level, then the new sort order is saved automatically.</p><p><strong>Safety:</strong> categories containing products or sub-categories cannot be deleted until those records are reassigned.</p></div></dialog>
</div>
@endsection
