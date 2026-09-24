@extends('layouts.admin')

@section('title', $category->name.' Products')

@section('content')
@php
    $canEdit = (bool) (auth()->user()?->hasPermission('website.products.edit'));
    $canDelete = (bool) (auth()->user()?->hasPermission('products.delete'));
@endphp

<div class="cpm-page">
    <header class="cpm-head">
        <div>
            <p class="cpm-eyebrow">WEBSITE &amp; PRODUCTS / CATEGORY PRODUCTS</p>
            <h1>{{ $category->name }} Products</h1>
            <p>Manage products assigned to this category: add, edit, approve, publish, unpublish and delete.</p>
        </div>
        <div class="cpm-head-actions">
            <a class="cpm-btn cpm-btn--soft" href="{{ route('admin.categories.index', ['selected' => $category->public_uuid]) }}"><x-icon name="arrow-left" size="14" /> Back to Categories</a>
            <a class="cpm-btn" href="{{ route('admin.add-product', ['category_id' => $category->id]) }}"><x-icon name="plus" size="14" /> Add Product</a>
        </div>
    </header>

    @if(session('success'))<div class="cpm-alert" role="status">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="cpm-alert cpm-alert--warning" role="status">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="cpm-alert cpm-alert--error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="cpm-stats">
        <article><small>Total Products</small><strong>{{ number_format($stats['total']) }}</strong></article>
        <article><small>Approved</small><strong>{{ number_format($stats['approved']) }}</strong></article>
        <article><small>Published</small><strong>{{ number_format($stats['published']) }}</strong></article>
        <article><small>Draft</small><strong>{{ number_format($stats['draft']) }}</strong></article>
    </section>

    <section class="cpm-panel">
        <div class="cpm-toolbar">
            <form method="get" action="{{ route('admin.categories.products', $category) }}" class="cpm-search">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name, SKU or brand">
                <button type="submit"><x-icon name="search" size="14" /> Search</button>
                @if($search !== '')<a href="{{ route('admin.categories.products', $category) }}">Reset</a>@endif
            </form>

            @if($canEdit || $canDelete)
            <div class="cpm-bulk">
                <label>Bulk Actions
                    <select data-cpm-bulk-action>
                        <option value="">Choose action</option>
                        @if($canEdit)
                            <option value="approve">Approve selected</option>
                            <option value="publish">Publish selected</option>
                            <option value="unpublish">Unpublish selected</option>
                        @endif
                        @if($canDelete)<option value="delete">Delete selected</option>@endif
                    </select>
                </label>
                <button type="button" data-cpm-bulk-apply disabled>Apply <span data-cpm-count></span></button>
            </div>
            @endif
        </div>

        <div class="cpm-table-wrap">
            <table class="cpm-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" data-cpm-select-all aria-label="Select all visible products"></th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Approval</th>
                        <th>Public Status</th>
                        <th>Stock</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($products as $product)
                    @php
                        $metadata = is_array($product->product_metadata) ? $product->product_metadata : [];
                        $approved = data_get($metadata, 'approval_status') === 'approved';
                        $published = $product->isPubliclyPublished();
                        $imageUrl = $product->image;
                        if($imageUrl && !preg_match('#^(https?:)?/#',$imageUrl)) $imageUrl = \Illuminate\Support\Facades\Storage::url($imageUrl);
                    @endphp
                    <tr>
                        <td><input type="checkbox" value="{{ $product->id }}" data-cpm-select aria-label="Select {{ $product->name }}"></td>
                        <td>
                            <div class="cpm-product">
                                <span>@if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $product->name }}">@else<x-icon name="package" size="22" />@endif</span>
                                <div><strong>{{ $product->name }}</strong><small>{{ $product->brand ?: 'Emerald Rozalia' }}</small></div>
                            </div>
                        </td>
                        <td><code>{{ $product->sku ?: '—' }}</code></td>
                        <td><span class="cpm-status {{ $approved ? 'is-good' : 'is-pending' }}">{{ $approved ? 'Approved' : 'Needs approval' }}</span></td>
                        <td><span class="cpm-status {{ $published ? 'is-good' : 'is-muted' }}">{{ $published ? 'Published' : 'Not public' }}</span></td>
                        <td>{{ number_format((int) $product->stock) }}</td>
                        <td>€{{ number_format((float) $product->price, 2) }}</td>
                        <td>
                            <div class="cpm-actions">
                                <a href="{{ route('admin.product.edit', $product) }}" title="Edit product"><x-icon name="pencil" size="14" /></a>
                                @if($canEdit && ! $approved)
                                    <form method="post" action="{{ route('admin.product-manager.publish', $product) }}">@csrf
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="return_category_id" value="{{ $category->id }}">
                                        <button type="submit" title="Approve product"><x-icon name="check" size="14" /></button>
                                    </form>
                                @endif
                                @if($canEdit && ! $published)
                                    <form method="post" action="{{ route('admin.product-manager.publish', $product) }}">@csrf
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="return_category_id" value="{{ $category->id }}">
                                        <button type="submit" title="Publish product"><x-icon name="eye" size="14" /></button>
                                    </form>
                                @elseif($canEdit)
                                    <form method="post" action="{{ route('admin.product-manager.publish', $product) }}">@csrf
                                        <input type="hidden" name="action" value="unpublish">
                                        <input type="hidden" name="return_category_id" value="{{ $category->id }}">
                                        <button type="submit" title="Unpublish product"><x-icon name="eye" size="14" /></button>
                                    </form>
                                @endif
                                @if($canDelete)
                                    <form method="post" action="{{ route('admin.product-manager.destroy', $product) }}" onsubmit="return confirm('Move {{ addslashes($product->name) }} to Trash?')">@csrf @method('DELETE')
                                        <input type="hidden" name="return_category_id" value="{{ $category->id }}">
                                        <button type="submit" class="is-danger" title="Delete product"><x-icon name="trash" size="14" /></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="cpm-empty"><x-icon name="package" size="28" /><strong>No products in this category.</strong><a href="{{ route('admin.add-product', ['category_id' => $category->id]) }}">Add the first product</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="cpm-pagination">{{ $products->links() }}</div>
        @endif
    </section>
</div>

<form id="cpm-bulk-publish-form" method="post" action="{{ route('admin.product-manager.bulk-publish') }}" hidden>
    @csrf
    <input type="hidden" name="action" value="">
    <input type="hidden" name="return_category_id" value="{{ $category->id }}">
</form>
<form id="cpm-bulk-delete-form" method="post" action="{{ route('admin.product-manager.bulk-destroy') }}" hidden>
    @csrf @method('DELETE')
    <input type="hidden" name="mode" value="selected">
    <input type="hidden" name="return_category_id" value="{{ $category->id }}">
</form>

<style>
.cpm-page{padding:18px}.cpm-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:16px}.cpm-eyebrow{margin:0 0 4px;color:#0b7139;font-size:11px;font-weight:800;letter-spacing:.08em}.cpm-head h1{margin:0;color:#173824;font-size:27px}.cpm-head p{margin:6px 0 0;color:#718077}.cpm-head-actions{display:flex;gap:8px;flex-wrap:wrap}.cpm-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 13px;border:1px solid #0b7139;border-radius:7px;background:#0b7139;color:#fff;text-decoration:none;font-size:13px;font-weight:750}.cpm-btn--soft{background:#fff;color:#0b7139}.cpm-alert{margin:0 0 12px;padding:10px 12px;border:1px solid #b9ddc2;border-radius:7px;background:#f2fbf4;color:#166534}.cpm-alert--warning{border-color:#ead9ad;background:#fffaf0;color:#806112}.cpm-alert--error{border-color:#efc1bb;background:#fff5f3;color:#a82424}.cpm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.cpm-stats article{padding:14px;border:1px solid #dbe5de;border-radius:8px;background:#fff}.cpm-stats small{display:block;color:#718077}.cpm-stats strong{display:block;margin-top:5px;color:#173824;font-size:23px}.cpm-panel{border:1px solid #d8e2da;border-radius:9px;background:#fff;overflow:hidden}.cpm-toolbar{display:flex;justify-content:space-between;gap:12px;padding:12px;border-bottom:1px solid #e3e9e5}.cpm-search,.cpm-bulk{display:flex;align-items:center;gap:7px}.cpm-search input,.cpm-bulk select{min-height:36px;border:1px solid #cad7ce;border-radius:6px;padding:0 10px;background:#fff}.cpm-search button,.cpm-bulk button{min-height:36px;border:1px solid #0b7139;border-radius:6px;padding:0 11px;background:#fff;color:#0b7139;font-weight:750;cursor:pointer}.cpm-bulk button:disabled{border-color:#d3ddd6;color:#9aa59d;cursor:not-allowed}.cpm-search a{color:#0b7139}.cpm-table-wrap{overflow:auto}.cpm-table{width:100%;border-collapse:collapse;min-width:950px}.cpm-table th,.cpm-table td{padding:11px 12px;border-bottom:1px solid #edf1ee;text-align:left;vertical-align:middle}.cpm-table th{background:#f8faf8;color:#607066;font-size:11px;letter-spacing:.03em}.cpm-product{display:flex;gap:9px;align-items:center}.cpm-product>span{display:grid;place-items:center;width:48px;height:48px;border:1px solid #dce5df;border-radius:7px;overflow:hidden;background:#fafcfa}.cpm-product img{width:100%;height:100%;object-fit:contain}.cpm-product strong,.cpm-product small{display:block}.cpm-product small{color:#718077;margin-top:3px}.cpm-status{display:inline-flex;padding:4px 7px;border-radius:999px;font-size:11px;font-weight:750}.cpm-status.is-good{background:#e4f6e8;color:#087a48}.cpm-status.is-pending{background:#fff3d6;color:#8a6812}.cpm-status.is-muted{background:#eef2ef;color:#69756e}.cpm-actions{display:flex;align-items:center;gap:5px}.cpm-actions form{margin:0}.cpm-actions a,.cpm-actions button{display:grid;place-items:center;width:34px;height:34px;border:1px solid #d3ddd6;border-radius:6px;background:#fff;color:#264934;cursor:pointer;text-decoration:none}.cpm-actions .is-danger{color:#b42318;border-color:#efc1bb}.cpm-empty{text-align:center!important;padding:35px!important;color:#718077}.cpm-empty strong,.cpm-empty a{display:block;margin-top:7px}.cpm-empty a{color:#0b7139}.cpm-pagination{padding:12px}.cpm-bulk label{display:flex;align-items:center;gap:6px;color:#5d6f63;font-size:12px;font-weight:700}@media(max-width:900px){.cpm-head,.cpm-toolbar{display:block}.cpm-head-actions,.cpm-bulk{margin-top:10px}.cpm-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

<script>
(() => {
    const boxes = Array.from(document.querySelectorAll('[data-cpm-select]'));
    const selectAll = document.querySelector('[data-cpm-select-all]');
    const action = document.querySelector('[data-cpm-bulk-action]');
    const apply = document.querySelector('[data-cpm-bulk-apply]');
    const count = document.querySelector('[data-cpm-count]');
    if (!action || !apply) return;

    const sync = () => {
        const selected = boxes.filter(box => box.checked);
        count.textContent = selected.length ? '(' + selected.length + ')' : '';
        apply.disabled = selected.length === 0 || action.value === '';
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && selected.length === boxes.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
        }
    };

    selectAll?.addEventListener('change', () => {
        boxes.forEach(box => { box.checked = selectAll.checked; });
        sync();
    });
    boxes.forEach(box => box.addEventListener('change', sync));
    action.addEventListener('change', sync);

    apply.addEventListener('click', () => {
        const selected = boxes.filter(box => box.checked).map(box => box.value);
        const chosen = action.value;
        if (!selected.length || !chosen) return;

        const deleting = chosen === 'delete';
        if (!window.confirm(deleting
            ? 'Move ' + selected.length + ' selected product(s) to Trash?'
            : chosen.charAt(0).toUpperCase() + chosen.slice(1) + ' ' + selected.length + ' selected product(s)?')) return;

        const form = document.getElementById(deleting ? 'cpm-bulk-delete-form' : 'cpm-bulk-publish-form');
        if (!form) return;
        if (!deleting) form.querySelector('input[name="action"]').value = chosen;

        form.querySelectorAll('input[name="products[]"]').forEach(node => node.remove());
        selected.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'products[]';
            input.value = id;
            form.appendChild(input);
        });
        form.submit();
    });

    sync();
})();
</script>
@endsection
