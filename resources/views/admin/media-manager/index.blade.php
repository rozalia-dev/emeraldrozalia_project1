@extends('layouts.admin')

@section('title','Product Media Manager')

@php
    $mediaTypes = [
        'image' => ['label' => 'Images', 'badge' => 'Image', 'icon' => 'image'],
        'video' => ['label' => 'Videos', 'badge' => 'Video', 'icon' => 'camera'],
        'spin_360' => ['label' => '360° Views', 'badge' => '360°', 'icon' => 'refresh'],
        'try_on' => ['label' => 'Virtual Try-On', 'badge' => 'Try-On', 'icon' => 'heart'],
        'document' => ['label' => 'Documents', 'badge' => 'Document', 'icon' => 'file-text'],
    ];
    $mediaType = in_array($mediaType ?? '', array_merge(['all'], array_keys($mediaTypes)), true) ? ($mediaType ?: 'all') : 'all';
    $mediaStatus = in_array($mediaStatus ?? '', ['','active','inactive','archived'], true) ? ($mediaStatus ?: 'all') : 'all';
    $mediaSort = in_array($mediaSort ?? '', ['newest','oldest','order'], true) ? $mediaSort : 'newest';
    $mediaTotal = $media->count();
    $activeCount = $media->where('active', true)->count();
    $inactiveCount = $media->where('active', false)->count();
    $archivedCount = $media->filter(fn ($item) => data_get($item->metadata, 'status') === 'archived')->count();
    $selectedMedia = $media->first();
    $selectedMetadata = is_array($selected?->product_metadata) ? $selected->product_metadata : [];
    $selectedOrderCategories = array_values((array) data_get($selectedMetadata, 'order_categories', []));
    $orderCategories = [
        'online' => 'Online Orders',
        'corporate' => 'Corporate Orders',
        'bulk' => 'Bulk Orders',
        'franchise' => 'Franchise Orders',
        'franchise_retail' => 'Franchise Retail Orders',
        'buyer' => 'Buyer Orders',
    ];
    $filterQuery = array_filter([
        'product_id' => $selected?->id,
        'media_status' => $mediaStatus === 'all' ? null : $mediaStatus,
        'media_sort' => $mediaSort === 'newest' ? null : $mediaSort,
    ], fn ($value) => $value !== null && $value !== '');
    $formatMediaLabel = fn (string $type): string => data_get($mediaTypes, $type.'.label', str($type)->replace('_', ' ')->headline());
    $countFor = fn (string $type): int => $media->where('type', $type)->count();
@endphp

@section('content')
<div class="mm-page" data-media-manager>
    <header class="mm-page-head">
        <div>
            <p class="mm-eyebrow">WEBSITE &amp; PRODUCTS / MEDIA OPERATIONS</p>
            <h1>Product Media Manager</h1>
            <p class="mm-subtitle">Manage all product images, videos, 360° views, virtual try-on assets and documents in one place.</p>
        </div>
        <div class="mm-head-meta">
            <nav class="mm-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Home</a>
                <x-icon name="chevron-right" size="11" />
                <a href="{{ route('admin.resource', 'product-manager') }}">Website &amp; Products</a>
                <x-icon name="chevron-right" size="11" />
                <strong>Product Media Manager</strong>
            </nav>
            <div class="mm-date-card">
                <x-icon name="calendar" size="22" />
                <span>
                    <small>Today</small>
                    <strong>{{ now()->format('l, j F Y') }}</strong>
                    <b>{{ now()->format('H:i') }}</b>
                </span>
            </div>
        </div>
    </header>

    <section class="mm-filter-bar" aria-label="Media filters">
        <div class="mm-product-control">
            <label for="mm-product-select">SELECT PRODUCT</label>
            <form method="get">
                <select id="mm-product-select" name="product_id" onchange="this.form.submit()">
                    @forelse($products as $product)
                        <option value="{{ $product->id }}" @selected($selected?->id === $product->id)>{{ $product->name }} ({{ $product->media_count }} active media)</option>
                    @empty
                        <option value="">No active products available</option>
                    @endforelse
                </select>
            </form>
        </div>
        <div class="mm-filter-control">
            <span class="mm-filter-label">MEDIA TYPE FILTER</span>
            <div class="mm-filter-pills">
                <a class="{{ $mediaType === 'all' ? 'is-active' : '' }}" href="{{ route('admin.media.index', $filterQuery) }}">All</a>
                @foreach($mediaTypes as $type => $definition)
                    <a class="{{ $mediaType === $type ? 'is-active' : '' }}" href="{{ route('admin.media.index', array_merge($filterQuery, ['media_type' => $type])) }}">{{ $definition['label'] }}</a>
                @endforeach
            </div>
        </div>
        <form class="mm-sort-control" method="get">
            <input type="hidden" name="product_id" value="{{ $selected?->id }}">
            @if($mediaType !== 'all')<input type="hidden" name="media_type" value="{{ $mediaType }}">@endif
            <label>Status Filter<select name="media_status" onchange="this.form.submit()"><option value="">All</option><option value="active" @selected($mediaStatus === 'active')>Active</option><option value="inactive" @selected($mediaStatus === 'inactive')>Inactive</option><option value="archived" @selected($mediaStatus === 'archived')>Archived</option></select></label>
            <label>Sort By<select name="media_sort" onchange="this.form.submit()"><option value="newest" @selected($mediaSort === 'newest')>Newest First</option><option value="oldest" @selected($mediaSort === 'oldest')>Oldest First</option><option value="order" @selected($mediaSort === 'order')>Gallery Order</option></select></label>
        </form>
        <div class="mm-view-switcher" aria-label="Media view">
            <button type="button" class="is-active" data-mm-view="grid" aria-label="Grid view"><x-icon name="image" size="14" /></button>
            <button type="button" data-mm-view="list" aria-label="List view"><x-icon name="file-text" size="14" /></button>
        </div>
    </section>

    @if($selected)
        <div class="mm-workspace">
            <section class="mm-main-column" aria-label="Media library and editing tools">
                <section class="mm-panel mm-library-panel">
                    <div class="mm-library-heading">
                        <div>
                            <h2>Media Library <span data-mm-visible-count>({{ $mediaTotal }})</span></h2>
                            <p>{{ $selected->name }} <span>·</span> {{ $selected->sku ?: 'No SKU' }}</p>
                        </div>
                        <div class="mm-library-tools">
                            <label class="mm-search-box"><x-icon name="search" size="14" /><input type="search" placeholder="Search media..." aria-label="Search media" data-mm-search></label>
                            <button type="button" class="mm-outline-button" data-mm-show-filters><x-icon name="filter" size="13" /> <span>Filters</span></button>
                        </div>
                    </div>
                    <div class="mm-tabs" role="tablist" aria-label="Media categories">
                        <button type="button" class="is-active" data-mm-tab="all" role="tab" aria-selected="true">All Media <b>({{ $mediaTotal }})</b></button>
                        @foreach($mediaTypes as $type => $definition)
                            <button type="button" data-mm-tab="{{ $type }}" role="tab" aria-selected="false">{{ $definition['label'] }} <b>({{ $countFor($type) }})</b></button>
                        @endforeach
                    </div>
                    <div class="mm-media-grid" data-mm-grid>
                        @forelse($media as $item)
                            @php
                                $definition = $mediaTypes[$item->type] ?? ['label' => $formatMediaLabel($item->type), 'badge' => 'Media', 'icon' => 'file-text'];
                                $metadata = is_array($item->metadata) ? $item->metadata : [];
                                $extension = strtolower(pathinfo($item->path, PATHINFO_EXTENSION));
                                $mediaUrl = null;
                                if (preg_match('/^(https?:)?\\/\\//', $item->path)) {
                                    $mediaUrl = $item->path;
                                } elseif ($item->disk === 'public') {
                                    $mediaUrl = \Illuminate\Support\Facades\Storage::disk($item->disk)->url($item->path);
                                }
                                $isImage = $item->type === 'image' && in_array($extension, ['jpg','jpeg','png','webp','avif','gif'], true) && $mediaUrl;
                                $isVideo = $item->type === 'video' && $mediaUrl;
                                $mediaSearch = strtolower($item->path.' '.($item->alt_text ?? '').' '.$definition['label']);
                                $dimensions = data_get($metadata, 'dimensions');
                                if (is_array($dimensions)) {
                                    $dimensions = implode(' × ', array_filter($dimensions, fn ($value) => is_scalar($value)));
                                }
                                $dimensions = $dimensions ?: ((data_get($metadata, 'width') && data_get($metadata, 'height')) ? data_get($metadata, 'width').' × '.data_get($metadata, 'height') : 'Asset preview');
                            @endphp
                            <article class="mm-media-card" data-mm-card data-mm-type="{{ $item->type }}" data-mm-status="{{ $item->active ? 'active' : 'inactive' }}" data-mm-search="{{ $mediaSearch }}">
                                <div class="mm-card-preview">
                                    @if($isImage)
                                        <img src="{{ $mediaUrl }}" alt="{{ $item->alt_text ?: $item->path }}" loading="lazy">
                                    @elseif($isVideo)
                                        <video src="{{ $mediaUrl }}" muted preload="metadata"></video>
                                        <span class="mm-preview-play"><x-icon name="camera" size="18" /></span>
                                    @else
                                        <div class="mm-preview-placeholder mm-preview-{{ $item->type }}"><x-icon name="{{ $definition['icon'] }}" size="32" /><strong>{{ $definition['badge'] }}</strong></div>
                                    @endif
                                    <span class="mm-media-badge mm-badge-{{ $item->type }}">{{ $definition['badge'] }}</span>
                                    <details class="mm-card-menu">
                                        <summary aria-label="Actions for {{ $item->path }}"><x-icon name="dots" size="16" /></summary>
                                        <div>
                                            <button type="button" data-mm-select-media data-media-id="{{ $item->id }}">Edit details</button>
                                            <form method="post" action="{{ route('admin.media.destroy', $item) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit">Remove media</button>
                                            </form>
                                        </div>
                                    </details>
                                </div>
                                <div class="mm-card-body">
                                    <strong title="{{ $item->path }}">{{ basename($item->path) }}</strong>
                                    <span>{{ $dimensions }} <i>·</i> {{ data_get($metadata, 'size') ?: 'Size recorded on upload' }}</span>
                                    <small>Uploaded: {{ optional($item->created_at)->format('d M Y') ?: '—' }}</small>
                                    <div class="mm-card-footer">
                                        <span class="mm-status-dot {{ $item->active ? 'is-active' : 'is-inactive' }}">{{ $item->active ? 'Active' : 'Inactive' }}</span>
                                        <button type="button" class="mm-edit-link" data-mm-select-media data-media-id="{{ $item->id }}" data-media-update-url="{{ route('admin.media.update', $item) }}" data-media-type="{{ $item->type }}" data-media-order="{{ $item->sort_order }}" data-media-active="{{ $item->active ? 1 : 0 }}" data-media-alt="{{ $item->alt_text }}">Edit</button>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="mm-empty-state" data-mm-empty>
                                <x-icon name="image" size="34" />
                                <strong>No media uploaded yet</strong>
                                <p>Upload approved product photography, video, 360° views or try-on assets to build this library.</p>
                                <a href="#mm-upload">Upload Media <x-icon name="arrow-right" size="13" /></a>
                            </div>
                        @endforelse
                    </div>
                    <div class="mm-filter-empty" data-mm-filter-empty hidden>
                        <x-icon name="search" size="24" />
                        <strong>No matching media</strong>
                        <span>Try another search or media category.</span>
                    </div>
                    <footer class="mm-library-footer">
                        <span>Showing {{ $mediaTotal ? 1 : 0 }} to {{ $mediaTotal }} of {{ $mediaTotal }} media items</span>
                        <div class="mm-pagination" aria-label="Media pagination"><button type="button" disabled><x-icon name="arrow-left" size="13" /></button><strong>1</strong><button type="button" disabled><x-icon name="arrow-right" size="13" /></button></div>
                    </footer>
                </section>

                <section class="mm-tools-grid">
                    <article class="mm-tool-card">
                        <div class="mm-tool-heading"><h3>Media Best Practices</h3><x-icon name="check" size="15" /></div>
                        <ul>
                            <li><x-icon name="check" size="11" /> Use high quality images (1200×1200px or higher).</li>
                            <li><x-icon name="check" size="11" /> Optimise file size for fast website performance.</li>
                            <li><x-icon name="check" size="11" /> Add alt text for better SEO and accessibility.</li>
                            <li><x-icon name="check" size="11" /> Use 360° views to increase customer engagement.</li>
                            <li><x-icon name="check" size="11" /> Add virtual try-on assets for better product experience.</li>
                        </ul>
                    </article>
                    <article class="mm-tool-card">
                        <div class="mm-tool-heading"><h3>Alt Text / SEO <small>(Selected Media)</small></h3><x-icon name="file-text" size="15" /></div>
                        @if($selectedMedia)
                            <form class="mm-editor-form" method="post" action="{{ route('admin.media.update', $selectedMedia) }}" data-mm-editor>
                                @csrf @method('PATCH')
                                <input type="hidden" name="type" value="{{ $selectedMedia->type }}" data-mm-editor-type>
                                <input type="hidden" name="sort_order" value="{{ $selectedMedia->sort_order }}" data-mm-editor-order>
                                <input type="hidden" name="active" value="{{ $selectedMedia->active ? 1 : 0 }}" data-mm-editor-active>
                                <label>Alt text<textarea name="alt_text" maxlength="255" data-mm-editor-alt>{{ $selectedMedia->alt_text }}</textarea></label>
                                <button type="submit" class="mm-small-button">Save Alt Text</button>
                            </form>
                        @else
                            <p class="mm-tool-note">Select a media item after uploading it to edit its accessibility text.</p>
                        @endif
                    </article>
                    <article class="mm-tool-card">
                        <div class="mm-tool-heading"><h3>Assign to Collections <small>(Selected Media)</small></h3><x-icon name="package" size="15" /></div>
                        <div class="mm-assignment-empty"><span>Collections are managed from the shared catalogue.</span><a href="{{ route('admin.resource', 'collections') }}">Manage Collections <x-icon name="arrow-right" size="12" /></a></div>
                    </article>
                    <article class="mm-tool-card">
                        <div class="mm-tool-heading"><h3>Assign to Six Order Master Categories</h3><x-icon name="shopping-bag" size="15" /></div>
                        <div class="mm-category-checks">
                            @foreach($orderCategories as $key => $label)
                                <label><input type="checkbox" disabled @checked(in_array($key, $selectedOrderCategories, true))><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                        <a class="mm-manage-link" href="{{ route('admin.add-product') }}">Manage from product workflow <x-icon name="arrow-right" size="12" /></a>
                    </article>
                </section>
            </section>

            <aside class="mm-rail">
                <section class="mm-rail-card mm-upload-card" id="mm-upload">
                    <div class="mm-rail-heading"><h2>Upload Media</h2><x-icon name="upload" size="16" /></div>
                    <form method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" data-mm-upload-form>
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $selected->id }}">
                        <input type="hidden" name="disk" value="public">
                        <input type="hidden" name="sort_order" value="{{ $mediaTotal }}">
                        <input type="hidden" name="active" value="1">
                        <label class="mm-dropzone" for="mm-file-input" data-mm-dropzone>
                            <x-icon name="upload" size="27" />
                            <strong>Drag &amp; drop files here</strong>
                            <span>or</span>
                            <span class="mm-choose-button">Choose Files</span>
                            <small>Supported: JPG, PNG, WEBP, MP4, MOV, GLB, PDF<br>Max file size: 100 MB</small>
                            <input id="mm-file-input" type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.avif,.mp4,.mov,.webm,.glb,.gltf,.pdf,image/jpeg,image/png,image/webp,video/mp4,video/quicktime,model/gltf-binary,model/gltf+json,application/pdf" data-mm-file-input>
                        </label>
                        <p class="mm-file-name" data-mm-file-name>No file selected</p>
                        <div class="mm-upload-options">
                            <label>Media type<select name="type" required><option value="image">Product image</option><option value="video">Product video</option><option value="spin_360">360° product view</option><option value="try_on">Virtual Try-On</option><option value="document">Document</option></select></label>
                            <label>Alt text<input name="alt_text" maxlength="255" placeholder="Describe this media for accessibility"></label>
                        </div>
                        <details class="mm-existing-path"><summary>Use an existing disk path</summary><label>Path<input name="path" placeholder="product-media/example.webp"></label><small>Use this when the approved file already exists on the public disk.</small></details>
                        <button type="submit" class="mm-primary-button"><x-icon name="upload" size="13" /> Upload Media</button>
                    </form>
                </section>

                <section class="mm-rail-card">
                    <div class="mm-rail-heading"><h2>Media Summary</h2><span>{{ $mediaTotal }} total</span></div>
                    <div class="mm-summary-overview">
                        <div class="mm-donut" style="--mm-active:{{ $mediaTotal ? round($activeCount / $mediaTotal * 100) : 0 }}%"><span>{{ $mediaTotal }}<small>Total media</small></span></div>
                        <div class="mm-legend"><span><i class="is-green"></i>Active <b>{{ $activeCount }}</b></span><span><i class="is-muted"></i>Inactive <b>{{ $inactiveCount }}</b></span><span><i class="is-archived"></i>Archived <b>{{ $archivedCount }}</b></span></div>
                    </div>
                    <dl class="mm-count-list">
                        @foreach($mediaTypes as $type => $definition)
                            <div><dt>{{ $definition['label'] }}</dt><dd>{{ $countFor($type) }}</dd></div>
                        @endforeach
                    </dl>
                </section>

                <section class="mm-rail-card mm-quick-actions">
                    <div class="mm-rail-heading"><h2>Quick Actions</h2><x-icon name="arrow-right" size="14" /></div>
                    <a href="#mm-upload"><x-icon name="upload" size="14" /> Upload new media</a>
                    <button type="button" data-mm-action="select-first"><x-icon name="pencil" size="14" /> Edit selected media</button>
                    <a href="{{ route('admin.resource', 'categories') }}"><x-icon name="package" size="14" /> Manage Categories</a>
                    <a href="{{ route('admin.resource', 'collections') }}"><x-icon name="clover" size="14" /> Manage Collections</a>
                </section>

                <section class="mm-rail-card mm-trace-card">
                    <div class="mm-rail-heading"><h2>UUID Traceability</h2><x-icon name="check" size="14" /></div>
                    <p>Every media file is assigned a unique UUID for full traceability.</p>
                    <dl><div><dt>Selected media UUID</dt><dd>{{ $selectedMedia?->uuid ?: 'Assigned on upload' }}</dd></div><div><dt>Status</dt><dd>{{ $selectedMedia ? ($selectedMedia->active ? 'Active' : 'Inactive') : 'No media selected' }}</dd></div></dl>
                </section>
            </aside>
        </div>
    @else
        <section class="mm-panel mm-empty-products"><x-icon name="package" size="32" /><h2>No active products</h2><p>Create or publish a product before adding media.</p><a class="mm-primary-button" href="{{ route('admin.add-product') }}">Add Product <x-icon name="arrow-right" size="13" /></a></section>
    @endif
</div>
@endsection
