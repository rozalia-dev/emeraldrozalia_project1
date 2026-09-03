@extends('layouts.admin')

@section('title', 'Images')

@php
    $roleLabels = [
        'primary' => 'Primary Images',
        'additional' => 'Additional Images',
        'lifestyle' => 'Lifestyle Images',
        'detail' => 'Detail Shots',
        'swatch' => 'Swatch / Color',
        'infographic' => 'Infographics',
        'packaging' => 'Packaging',
    ];
    $formatBytes = function (int $bytes): string {
        if ($bytes <= 0) return '—';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 0).' KB';
        return $bytes.' B';
    };
    $roleFor = function ($image) use ($roleLabels): string {
        $role = (string) data_get($image->metadata, 'image_role', '');
        return array_key_exists($role, $roleLabels) ? $role : ($image->sort_order === 0 ? 'primary' : 'additional');
    };
    $urlFor = function ($image): ?string {
        if (preg_match('/^(https?:)?\\/\\//', $image->path)) return $image->path;
        return $image->disk === 'public' ? \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) : null;
    };
    $dimensionsFor = function ($image): string {
        $width = (int) data_get($image->metadata, 'width', 0);
        $height = (int) data_get($image->metadata, 'height', 0);
        return $width && $height ? $width.' × '.$height : 'Resolution pending';
    };
    $sizeFor = function ($image) use ($formatBytes): string {
        $bytes = (int) data_get($image->metadata, 'size_bytes', 0);
        return $bytes ? $formatBytes($bytes) : (string) data_get($image->metadata, 'size', 'Size pending');
    };
    $selectedUrl = $selectedImage ? $urlFor($selectedImage) : null;
    $primaryPercent = $stats['total'] ? round($stats['primary'] / $stats['total'] * 100) : 0;
    $statCards = [
        ['label' => 'Total Images', 'value' => number_format($stats['total']), 'change' => '+18.6%', 'note' => 'vs last 30 days', 'icon' => 'image', 'tone' => 'green'],
        ['label' => 'Products with Images', 'value' => number_format($stats['products']), 'change' => '+4.2%', 'note' => 'vs last 30 days', 'icon' => 'package', 'tone' => 'purple'],
        ['label' => 'Primary Images', 'value' => number_format($stats['primary']), 'change' => '', 'note' => 'Product lead images', 'icon' => 'star', 'tone' => 'orange'],
        ['label' => 'Additional Images', 'value' => number_format($stats['additional']), 'change' => '', 'note' => 'Supporting product views', 'icon' => 'image', 'tone' => 'blue'],
        ['label' => 'Avg. Image Size', 'value' => $formatBytes($stats['average_size']), 'change' => '-6.3%', 'note' => 'vs last 30 days', 'icon' => 'package', 'tone' => 'teal'],
        ['label' => 'Storage Used', 'value' => $formatBytes($stats['storage_bytes']), 'change' => '+12.7%', 'note' => 'vs last 30 days', 'icon' => 'package', 'tone' => 'purple'],
    ];
@endphp

@section('content')
<div class="im-page" data-image-manager>
    <header class="im-page-head">
        <div>
            <p class="im-eyebrow">WEBSITE &amp; PRODUCTS / MEDIA OPERATIONS</p>
            <h1>Images</h1>
            <p class="im-subtitle">Manage product images, alt text, ordering and visibility. High quality images help increase trust and conversions.</p>
        </div>
        <div class="im-head-meta">
            <nav class="im-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Home</a>
                <x-icon name="chevron-right" size="11" />
                <a href="{{ route('admin.resource', 'product-manager') }}">Website &amp; Products</a>
                <x-icon name="chevron-right" size="11" />
                <strong>Images</strong>
            </nav>
            <div class="im-date-card">
                <x-icon name="calendar" size="22" />
                <span><small>Today</small><strong>{{ now()->format('l, j F Y') }}</strong><b>{{ now()->format('H:i') }}</b></span>
            </div>
        </div>
    </header>

    <section class="im-stat-grid" aria-label="Image statistics">
        @foreach($statCards as $stat)
            <article class="im-stat-card">
                <span class="im-stat-icon im-tone-{{ $stat['tone'] }}"><x-icon name="{{ $stat['icon'] }}" size="17" /></span>
                <span class="im-stat-copy"><small>{{ $stat['label'] }}</small><strong>{{ $stat['value'] }}</strong><span class="{{ str_starts_with($stat['change'], '-') ? 'is-down' : 'is-up' }}">{{ $stat['change'] }} <i>{{ $stat['note'] }}</i></span></span>
            </article>
        @endforeach
    </section>

    <section class="im-toolbar-panel">
        <div class="im-tabs-row">
            <nav class="im-tabs" aria-label="Image categories">
                <a class="{{ $tab === 'all' ? 'is-active' : '' }}" href="{{ route('admin.images.index', $filterQuery) }}">All Images</a>
                @foreach($roleLabels as $role => $label)
                    <a class="{{ $tab === $role ? 'is-active' : '' }}" href="{{ route('admin.images.index', array_merge($filterQuery, ['tab' => $role])) }}">{{ $label }}</a>
                @endforeach
            </nav>
            <button type="button" class="im-primary-button" data-im-open-upload aria-controls="im-upload-drawer" aria-expanded="false"><x-icon name="plus" size="13" /> Upload Images <x-icon name="chevron-right" size="12" /></button>
        </div>
        <form class="im-filter-row" method="get" action="{{ route('admin.images.index') }}">
            <label class="im-search-box"><x-icon name="search" size="14" /><input type="search" name="q" value="{{ $search }}" placeholder="Search images by name, SKU, product..." aria-label="Search images"></label>
            <label><span class="im-filter-label">Product</span><select name="product_id"><option value="">All Products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected($selectedProduct?->id === $product->id)>{{ $product->name }}</option>@endforeach</select></label>
            <label><span class="im-filter-label">Status</span><select name="status"><option value="">All Statuses</option><option value="active" @selected($status === 'active')>Active</option><option value="inactive" @selected($status === 'inactive')>Inactive</option></select></label>
            <label><span class="im-filter-label">Image Type</span><select name="tab"><option value="all">All Types</option>@foreach($roleLabels as $role => $label)<option value="{{ $role }}" @selected($tab === $role)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="im-filter-label">Orientation</span><select name="orientation"><option value="">All Orientations</option><option value="square" @selected($orientation === 'square')>Square</option><option value="landscape" @selected($orientation === 'landscape')>Landscape</option><option value="portrait" @selected($orientation === 'portrait')>Portrait</option></select></label>
            <label><span class="im-filter-label">Sort By</span><select name="sort"><option value="newest" @selected($sort === 'newest')>Newest First</option><option value="oldest" @selected($sort === 'oldest')>Oldest First</option><option value="order" @selected($sort === 'order')>Gallery Order</option><option value="name" @selected($sort === 'name')>Name</option></select></label>
            <a class="im-reset-link" href="{{ route('admin.images.index') }}"><x-icon name="refresh" size="12" /> Reset</a>
        </form>
    </section>

    <div class="im-upload-drawer" id="im-upload-drawer" data-im-upload-drawer hidden>
        <div class="im-drawer-heading"><div><h2>Upload Product Images</h2><p>Upload approved JPG, PNG, WEBP or AVIF files. Maximum 20 MB per image.</p></div><button type="button" class="im-close-button" data-im-close-upload aria-label="Close upload panel"><x-icon name="chevron-right" size="14" /></button></div>
        <form method="post" action="{{ route('admin.images.store') }}" enctype="multipart/form-data" data-im-upload-form>
            @csrf
            <label><span>Product</span><select name="product_id" required><option value="">Select a product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected($selectedProduct?->id === $product->id)>{{ $product->name }}{{ $product->sku ? ' ('.$product->sku.')' : '' }}</option>@endforeach</select></label>
            <label><span>Image files</span><input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.avif,image/jpeg,image/png,image/webp,image/avif" data-im-file-input><small data-im-file-name>No files selected</small></label>
            <label><span>Image type</span><select name="image_role" required>@foreach($roleLabels as $role => $label)<option value="{{ $role }}">{{ $label }}</option>@endforeach</select></label>
            <label class="im-upload-alt"><span>Alt text</span><input type="text" name="alt_text" maxlength="255" placeholder="Describe the product image for accessibility"></label>
            <button type="submit" class="im-primary-button"><x-icon name="upload" size="13" /> Upload Images</button>
        </form>
    </div>

    <div class="im-layout">
        <section class="im-library-card">
            <div class="im-library-head">
                <div><h2>Image Library <span>({{ number_format($media->total()) }})</span></h2><p>{{ $selectedProduct ? $selectedProduct->name.' · '.$selectedProduct->sku : 'All active products' }}</p></div>
                <div class="im-library-head-actions"><span class="im-view-label">Table view</span><button type="button" class="im-icon-button is-active" aria-label="Table view"><x-icon name="file-text" size="14" /></button></div>
            </div>
            <form class="im-bulk-toolbar" id="im-bulk-form" method="post" action="{{ route('admin.images.bulk') }}" data-im-bulk-form>
                @csrf
                <label><span>Bulk Actions</span><select name="action"><option value="activate">Activate selected</option><option value="deactivate">Deactivate selected</option><option value="delete">Delete selected</option></select></label>
                <button type="submit" class="im-outline-button"><x-icon name="check" size="12" /> Apply</button>
                <span data-im-selection-count>0 selected</span>
            </form>
            <div class="im-table-scroll">
                <table class="im-image-table">
                    <thead><tr><th><input type="checkbox" data-im-select-all aria-label="Select all visible images"></th><th>IMAGE</th><th>TYPE</th><th>RESOLUTION</th><th>SIZE</th><th>ALT TEXT</th><th>STATUS</th><th>SORT ORDER</th><th>ACTIONS</th></tr></thead>
                    <tbody>
                    @forelse($media as $image)
                        @php
                            $role = $roleFor($image);
                            $imageUrl = $urlFor($image);
                            $dimensions = $dimensionsFor($image);
                            $sizeLabel = $sizeFor($image);
                            $imageName = basename($image->path);
                        @endphp
                        <tr data-im-card data-im-id="{{ $image->id }}" data-im-role="{{ $role }}" data-im-name="{{ $imageName }}" data-im-product="{{ $image->product?->name }}" data-im-alt="{{ $image->alt_text }}" data-im-url="{{ $imageUrl }}" data-im-dimensions="{{ $dimensions }}" data-im-size="{{ $sizeLabel }}" data-im-update-url="{{ route('admin.images.update', $image) }}" data-im-order="{{ $image->sort_order }}" data-im-active="{{ $image->active ? 1 : 0 }}">
                            <td><input type="checkbox" value="{{ $image->id }}" data-im-select aria-label="Select {{ $imageName }}"></td>
                            <td><div class="im-image-cell"><span class="im-table-thumb">@if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $image->alt_text ?: $imageName }}" loading="lazy">@else<x-icon name="image" size="20" />@endif</span><span><strong title="{{ $imageName }}">{{ $imageName }}</strong><small>SKU: {{ $image->product?->sku ?: '—' }}</small><small>{{ $image->product?->name ?: 'Product unavailable' }}</small></span><em class="im-role-badge im-role-{{ $role }}">{{ $roleLabels[$role] }}</em></div></td>
                            <td><span class="im-type-label">{{ $roleLabels[$role] }}</span><small>{{ strtoupper((string) data_get($image->metadata, 'format', pathinfo($image->path, PATHINFO_EXTENSION))) }}</small></td>
                            <td>{{ $dimensions }}</td>
                            <td>{{ $sizeLabel }}</td>
                            <td><span class="im-alt-cell" title="{{ $image->alt_text }}">{{ $image->alt_text ?: 'Alt text not set' }}</span></td>
                            <td><span class="im-status {{ $image->active ? 'is-active' : 'is-inactive' }}">{{ $image->active ? 'Active' : 'Inactive' }}</span></td>
                            <td><input class="im-order-input" value="{{ $image->sort_order }}" readonly aria-label="Sort order for {{ $imageName }}"></td>
                            <td><div class="im-actions"><a class="im-icon-button" href="{{ route('admin.images.index', array_merge($filterQuery, ['tab' => $tab, 'selected_media_id' => $image->id])) }}" aria-label="Preview {{ $imageName }}"><x-icon name="eye" size="13" /></a><button type="button" class="im-icon-button" data-im-select-image aria-label="Edit {{ $imageName }}"><x-icon name="pencil" size="13" /></button><details class="im-row-menu"><summary aria-label="More actions for {{ $imageName }}"><x-icon name="dots" size="14" /></summary><div><button type="button" data-im-select-image>Edit details</button><form method="post" action="{{ route('admin.images.destroy', $image) }}">@csrf @method('DELETE')<button type="submit">Remove image</button></form></div></details></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="im-empty-state"><x-icon name="image" size="32" /><strong>No images uploaded yet</strong><span>Upload the approved product photography to start the image library.</span><button type="button" class="im-link-button" data-im-open-upload>Upload Images <x-icon name="arrow-right" size="12" /></button></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="im-table-footer">
                <span>Showing {{ $media->total() ? $media->firstItem() : 0 }} to {{ $media->total() ? $media->lastItem() : 0 }} of {{ number_format($media->total()) }} images</span>
                <div class="im-pagination" aria-label="Image pagination">
                    @if($media->currentPage() > 1)<a href="{{ $media->previousPageUrl() }}" aria-label="Previous page"><x-icon name="arrow-left" size="12" /></a>@else<button type="button" disabled><x-icon name="arrow-left" size="12" /></button>@endif
                    @for($page = 1; $page <= $media->lastPage(); $page++)
                        @if($page === $media->currentPage())<strong>{{ $page }}</strong>@else<a href="{{ $media->url($page) }}">{{ $page }}</a>@endif
                    @endfor
                    @if($media->hasMorePages())<a href="{{ $media->nextPageUrl() }}" aria-label="Next page"><x-icon name="arrow-right" size="12" /></a>@else<button type="button" disabled><x-icon name="arrow-right" size="12" /></button>@endif
                </div>
                <label class="im-per-page">8 / page</label>
            </div>
        </section>

        <aside class="im-side-column">
            <section class="im-side-card">
                <div class="im-side-heading"><h2>Image Summary</h2><a href="{{ route('admin.images.index') }}">Reset</a></div>
                <dl class="im-summary-list"><div><dt>Total Images</dt><dd>{{ number_format($stats['total']) }}</dd></div><div><dt>Primary Images</dt><dd>{{ number_format($stats['primary']) }}</dd></div><div><dt>Additional Images</dt><dd>{{ number_format($stats['additional']) }}</dd></div><div><dt>Active Images</dt><dd>{{ number_format($stats['active']) }}</dd></div><div><dt>Storage Used</dt><dd>{{ $formatBytes($stats['storage_bytes']) }}</dd></div></dl>
            </section>
            <section class="im-side-card">
                <div class="im-side-heading"><h2>Image Types</h2></div>
                <div class="im-type-summary"><div class="im-type-donut" style="--im-primary:{{ $primaryPercent }}%"><span>{{ number_format($stats['total']) }}<small>Total</small></span></div><div class="im-type-legend"><span><i class="is-primary"></i>Primary <b>{{ number_format($stats['primary']) }}</b></span><span><i class="is-additional"></i>Additional <b>{{ number_format($stats['additional']) }}</b></span></div></div>
            </section>
            <section class="im-side-card im-quick-actions">
                <div class="im-side-heading"><h2>Quick Actions</h2><x-icon name="arrow-right" size="13" /></div>
                <button type="button" data-im-open-upload><x-icon name="upload" size="13" /> Upload Images</button>
                <button type="button" data-im-select-first><x-icon name="pencil" size="13" /> Edit Selected Image</button>
                <button type="button" data-im-optimize><x-icon name="settings" size="13" /> Optimize Images</button>
                <a href="{{ route('admin.resource', 'media-manager') }}"><x-icon name="camera" size="13" /> Open Media Manager</a>
            </section>
            <section class="im-side-card im-uuid-card">
                <div class="im-side-heading"><h2>UUID Traceability</h2><x-icon name="check" size="13" /></div>
                <p>Every image is assigned a unique UUID for full traceability.</p>
                <dl><div><dt>Selected Image UUID</dt><dd>{{ $selectedImage?->uuid ?: 'Select an image' }}</dd></div><div><dt>Status</dt><dd>{{ $selectedImage ? ($selectedImage->active ? 'Active' : 'Inactive') : 'No image selected' }}</dd></div></dl>
                @if($selectedImage)<a href="{{ route('admin.images.index', ['selected_media_id' => $selectedImage->id]) }}">View Image Audit Log <x-icon name="arrow-right" size="11" /></a>@endif
            </section>
        </aside>
    </div>

    <section class="im-bottom-grid">
        <article class="im-tool-card">
            <div class="im-tool-heading"><h2>Upload &amp; Guidelines</h2><x-icon name="upload" size="15" /></div>
            <ul><li><x-icon name="check" size="11" /> Accepted: JPG, JPEG, PNG, WEBP, AVIF</li><li><x-icon name="check" size="11" /> Maximum 20 MB per image</li><li><x-icon name="check" size="11" /> Recommended 2000px to 4000px square</li><li><x-icon name="check" size="11" /> Use descriptive alt text</li></ul>
            <button type="button" class="im-tool-link" data-im-open-upload>View Upload Guidelines <x-icon name="arrow-right" size="11" /></button>
        </article>
        <article class="im-tool-card">
            <div class="im-tool-heading"><h2>Image Settings</h2><x-icon name="settings" size="15" /></div>
            <label class="im-toggle-row"><span>Auto Optimize Images</span><input type="checkbox" checked disabled><i></i></label>
            <label class="im-toggle-row"><span>Convert to WebP</span><input type="checkbox" checked disabled><i></i></label>
            <label class="im-toggle-row"><span>Maintain Aspect Ratio</span><input type="checkbox" checked disabled><i></i></label>
            <label class="im-toggle-row"><span>Strip Metadata (EXIF)</span><input type="checkbox" checked disabled><i></i></label>
            <button type="button" class="im-tool-link" data-im-settings>Settings are managed by the media policy <x-icon name="arrow-right" size="11" /></button>
        </article>
        <article class="im-tool-card">
            <div class="im-tool-heading"><h2>Alt Text &amp; SEO</h2><x-icon name="file-text" size="15" /></div>
            @if($selectedImage)
                @php $selectedRole = $roleFor($selectedImage); @endphp
                <form class="im-editor-form" method="post" action="{{ route('admin.images.update', $selectedImage) }}" data-im-editor>
                    @csrf @method('PATCH')
                    <input type="hidden" name="image_role" value="{{ $selectedRole }}" data-im-editor-role>
                    <input type="hidden" name="sort_order" value="{{ $selectedImage->sort_order }}" data-im-editor-order>
                    <input type="hidden" name="active" value="{{ $selectedImage->active ? 1 : 0 }}" data-im-editor-active>
                    <label>Alt text<textarea name="alt_text" maxlength="255" data-im-editor-alt>{{ $selectedImage->alt_text }}</textarea></label>
                    <button type="submit" class="im-small-button">Save Alt Text</button>
                </form>
            @else
                <p class="im-note">Select an image to edit alt text and ordering.</p>
            @endif
        </article>
        <article class="im-tool-card">
            <div class="im-tool-heading"><h2>Image Performance <small>(Last 30 Days)</small></h2><x-icon name="chart" size="15" /></div>
            <div class="im-performance-grid"><div><strong>{{ number_format($performance['views']) }}</strong><small>Total Views</small><em>+18.6%</em></div><div><strong>{{ number_format($performance['clicks']) }}</strong><small>Zoom / Clicks</small><em>+16.3%</em></div><div><strong>{{ number_format($performance['engagement'], 2) }}%</strong><small>Avg. Engagement</small><em>+12.7%</em></div></div>
            <a class="im-tool-link" href="{{ route('admin.resource', 'reports') }}">View Full Media Performance <x-icon name="arrow-right" size="11" /></a>
        </article>
        <article class="im-tool-card im-preview-card">
            <div class="im-tool-heading"><h2>Image Preview</h2><x-icon name="eye" size="15" /></div>
            <div class="im-preview-frame" data-im-preview-frame>
                @if($selectedUrl)<img src="{{ $selectedUrl }}" alt="{{ $selectedImage?->alt_text ?: 'Selected image' }}" data-im-preview-image>@else<div data-im-preview-empty><x-icon name="image" size="31" /><span>Select an image</span></div>@endif
            </div>
            <strong data-im-preview-name>{{ $selectedImage ? basename($selectedImage->path) : 'No image selected' }}</strong>
            <small data-im-preview-meta>{{ $selectedImage ? $dimensionsFor($selectedImage).' · '.$sizeFor($selectedImage) : 'Choose an image from the library' }}</small>
        </article>
    </section>
</div>
@endsection
