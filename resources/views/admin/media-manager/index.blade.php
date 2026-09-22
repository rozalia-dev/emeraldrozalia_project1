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
            <form method="get" action="{{ route('admin.media.index') }}">
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
        <form class="mm-sort-control" method="get" action="{{ route('admin.media.index') }}">
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
        <section class="mm-readiness" data-media-readiness aria-label="Public product media readiness">
            <div class="mm-readiness__head">
                <div>
                    <p class="mm-eyebrow">PUBLIC PRODUCT PAGE READINESS</p>
                    <h2>{{ $selected->name }} <small>· {{ $selected->sku ?: 'No SKU' }}</small></h2>
                    <p>These are the media/customer features currently available on the public product page.</p>
                </div>
                <a class="mm-primary-button" href="{{ route('product', $selected) }}" target="_blank" rel="noopener">Open Product Page <x-icon name="arrow-right" size="13" /></a>
            </div>

            <div class="mm-readiness__grid">
                <article class="mm-readiness-card {{ data_get($mediaReadiness, 'images.ready') ? 'is-ready' : 'is-missing' }}" data-readiness-type="images">
                    <span class="mm-readiness-card__icon"><x-icon name="image" size="22" /></span>
                    <div><strong>COLOUR / PRODUCT IMAGES</strong><span>{{ data_get($mediaReadiness, 'images.detail', 'No approved images') }}</span></div>
                    <b>{{ data_get($mediaReadiness, 'images.ready') ? 'LIVE' : 'NEEDS MEDIA' }}</b>
                    <a href="{{ route('admin.media.index', ['product_id' => $selected->id, 'media_type' => 'image']) }}">Manage Images</a>
                </article>

                <article class="mm-readiness-card {{ data_get($mediaReadiness, 'spin.ready') ? 'is-ready' : 'is-missing' }}" data-readiness-type="spin">
                    <span class="mm-readiness-card__icon"><x-icon name="refresh" size="22" /></span>
                    <div><strong>360° VIEW</strong><span>{{ data_get($mediaReadiness, 'spin.detail', 'No published 360° view') }}</span></div>
                    <b>{{ data_get($mediaReadiness, 'spin.ready') ? 'LIVE' : 'NOT LIVE' }}</b>
                    <a href="{{ route('admin.spins.index', ['product_id' => $selected->id]) }}">Manage 360°</a>
                </article>

                <article class="mm-readiness-card {{ data_get($mediaReadiness, 'video.ready') ? 'is-ready' : 'is-missing' }}" data-readiness-type="video">
                    <span class="mm-readiness-card__icon"><x-icon name="play" size="22" /></span>
                    <div><strong>PRODUCT VIDEO</strong><span>{{ data_get($mediaReadiness, 'video.detail', 'No public gallery video') }}</span></div>
                    <b>{{ data_get($mediaReadiness, 'video.ready') ? 'LIVE' : 'NOT LIVE' }}</b>
                    <a href="{{ route('admin.videos.index', ['product_id' => $selected->id]) }}">Manage Video</a>
                </article>

                <article class="mm-readiness-card {{ data_get($mediaReadiness, 'tryon.ready') ? 'is-ready' : 'is-missing' }}" data-readiness-type="tryon">
                    <span class="mm-readiness-card__icon"><x-icon name="camera" size="22" /></span>
                    <div><strong>VIRTUAL TRY-ON</strong><span>{{ data_get($mediaReadiness, 'tryon.detail', 'No public Try-On asset') }}</span></div>
                    <b>{{ data_get($mediaReadiness, 'tryon.ready') ? 'LIVE' : 'NOT LIVE' }}</b>
                    <a href="{{ route('admin.tryons.index', ['product_id' => $selected->id]) }}">Manage Try-On</a>
                </article>

                <article class="mm-readiness-card {{ data_get($mediaReadiness, 'reviews.ready') ? 'is-ready' : 'is-missing' }}" data-readiness-type="reviews">
                    <span class="mm-readiness-card__icon"><x-icon name="star" size="22" /></span>
                    <div><strong>CUSTOMER REVIEWS</strong><span>{{ data_get($mediaReadiness, 'reviews.detail', '0 approved customer reviews') }}</span></div>
                    <b>{{ data_get($mediaReadiness, 'reviews.ready') ? 'LIVE' : 'NO REVIEWS' }}</b>
                    <a href="{{ route('admin.resource', 'reviews-ratings') }}">Manage Reviews</a>
                </article>
            </div>

            <div class="mm-readiness__note">
                <x-icon name="info" size="15" />
                <span><b>360°:</b> publish a frame ZIP in the 360° manager. <b>Video:</b> publish a public video with “Add to product gallery” enabled. <b>Try-On:</b> publish a public preview overlay. The public product page updates automatically from these records.</span>
            </div>
        </section>

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
                    @if($mediaTotal > 0)
                        <form id="mm-bulk-actions-form" class="mm-bulk-toolbar" method="post" action="{{ route('admin.media.bulk') }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $selected->id }}">
                            <label class="mm-bulk-toolbar__select-all" for="mm-select-all">
                                <input id="mm-select-all" type="checkbox" data-mm-select-all>
                                <span>Select all</span>
                            </label>
                            <label class="mm-bulk-toolbar__action">Bulk action
                                <select name="action" required>
                                    <option value="activate">Activate selected</option>
                                    <option value="deactivate">Deactivate selected</option>
                                    <option value="approve">Approve selected for public</option>
                                    <option value="reject">Reject public use</option>
                                </select>
                            </label>
                            <button type="submit">Apply</button>
                            <span class="mm-bulk-toolbar__count" data-mm-selected-count>0 selected</span>
                        </form>
                    @endif
                    <div class="mm-media-grid" data-mm-grid>
                        @forelse($media as $item)
                            @php
                                $definition = $mediaTypes[$item->type] ?? ['label' => $formatMediaLabel($item->type), 'badge' => 'Media', 'icon' => 'file-text'];
                                $metadata = is_array($item->metadata) ? $item->metadata : [];
                                $approvalStatus = $item->approval_status ?: 'approved';
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
                                    <label class="mm-card-select">
                                        <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="mm-bulk-actions-form" aria-label="Select {{ basename($item->path) }}" data-mm-bulk-checkbox>
                                        <span class="sr-only">Select media item</span>
                                    </label>
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
                                        <span class="mm-status-dot mm-status-dot--{{ $approvalStatus }}">{{ str($approvalStatus)->headline() }}</span>
                                        <button type="button" class="mm-edit-link" data-mm-select-media data-media-id="{{ $item->id }}" data-media-update-url="{{ route('admin.media.update', $item) }}" data-media-type="{{ $item->type }}" data-media-order="{{ $item->sort_order }}" data-media-active="{{ $item->active ? 1 : 0 }}" data-media-alt="{{ $item->alt_text }}">Edit</button>
                                    </div>
                                    <div class="mm-card-approval-actions">
                                        @if($approvalStatus !== 'approved')
                                            <form method="post" action="{{ route('admin.media.approve', $item) }}">@csrf<button type="submit">Approve for public</button></form>
                                        @else
                                            <span class="mm-approved-public">Approved for public</span>
                                        @endif
                                        @if($approvalStatus !== 'rejected')<form method="post" action="{{ route('admin.media.reject', $item) }}">@csrf<button type="submit">Reject public use</button></form>@endif
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
<style>
.mm-readiness{margin:14px 0 16px;padding:15px;border:1px solid #d7e4da;border-radius:8px;background:#fff;box-shadow:0 2px 10px rgba(24,72,42,.05)}
.mm-readiness__head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:13px}
.mm-readiness__head h2{margin:2px 0 4px;color:#183a27;font-size:18px}.mm-readiness__head h2 small{color:#718178;font-size:11px;font-weight:600}.mm-readiness__head p{margin:0;color:#748279;font-size:11px}
.mm-readiness__grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px}
.mm-readiness-card{display:grid;grid-template-columns:auto 1fr;gap:8px 9px;align-items:start;padding:11px;border:1px solid #dce6de;border-radius:7px;background:#fbfdfb}
.mm-readiness-card__icon{display:grid;width:34px;height:34px;place-items:center;border-radius:50%;background:#edf4ef;color:#52675a}
.mm-readiness-card div{min-width:0}.mm-readiness-card strong{display:block;color:#274032;font-size:10px;line-height:1.2}.mm-readiness-card span{display:block;margin-top:3px;color:#718077;font-size:9px;line-height:1.35}
.mm-readiness-card>b{grid-column:1/2;align-self:center;justify-self:start;padding:3px 6px;border-radius:999px;background:#eef2ef;color:#637068;font-size:8px;letter-spacing:.05em}
.mm-readiness-card>a{grid-column:2/3;justify-self:end;color:#0b7139;font-size:9px;font-weight:800;text-decoration:none}.mm-readiness-card>a:hover{text-decoration:underline}
.mm-readiness-card.is-ready{border-color:#b9ddc2;background:#f4fbf5}.mm-readiness-card.is-ready .mm-readiness-card__icon{background:#daf1df;color:#0b7139}.mm-readiness-card.is-ready>b{background:#dff3e4;color:#0a6b35}
.mm-readiness-card.is-missing{border-color:#ead9ad;background:#fffaf0}.mm-readiness-card.is-missing .mm-readiness-card__icon{background:#f7edcf;color:#8d6b16}.mm-readiness-card.is-missing>b{background:#f7edcf;color:#806112}
.mm-readiness__note{display:flex;gap:8px;align-items:flex-start;margin-top:11px;padding:9px 10px;border-radius:5px;background:#f5f8f5;color:#607166;font-size:9px;line-height:1.45}.mm-readiness__note svg{flex:none;color:#0b7139}
@media(max-width:1200px){.mm-readiness__grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.mm-readiness__head{display:block}.mm-readiness__head>a{margin-top:10px}.mm-readiness__grid{grid-template-columns:1fr}}

.mm-bulk-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:9px;padding:9px 13px;margin:10px 13px;border:1px solid #d9e3db;border-radius:6px;background:#f7faf7;color:#405448;font-size:10px}
.mm-bulk-toolbar__select-all,.mm-bulk-toolbar__action{display:flex;align-items:center;gap:7px;font-weight:700}
.mm-bulk-toolbar__select-all input,.mm-card-select input{width:15px;height:15px;accent-color:#0b7139;cursor:pointer}
.mm-bulk-toolbar__action select{min-width:175px;min-height:32px;padding:0 8px;border:1px solid #cfdbd1;border-radius:4px;background:#fff;color:#294132}
.mm-bulk-toolbar>button,.mm-card-approval-actions button{min-height:30px;padding:0 12px;border:1px solid #0c733d;border-radius:4px;background:#0c733d;color:#fff;font-weight:700;cursor:pointer}
.mm-bulk-toolbar>button:hover,.mm-card-approval-actions button:hover{background:#075f31}
.mm-bulk-toolbar__count{margin-left:auto;color:#64766a}
.mm-card-select{position:absolute;top:30px;left:7px;z-index:4;display:grid;place-items:center;width:24px;height:24px;border:1px solid #d6e2d8;border-radius:4px;background:rgba(255,255,255,.96);box-shadow:0 2px 5px rgba(20,50,30,.16);cursor:pointer}
.mm-card-approval-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:5px}
.mm-card-approval-actions form{margin:0}
.mm-card-approval-actions button{min-height:26px;padding:0 8px;font-size:9px}
.mm-card-approval-actions form+form button{border-color:#b65044;background:#fff;color:#963e34}
.mm-card-approval-actions form+form button:hover{background:#fff3f1}
.mm-approved-public{display:inline-flex;align-items:center;min-height:24px;padding:0 7px;border:1px solid #c6e6ce;border-radius:4px;background:#edf8ef;color:#14723b;font-size:9px;font-weight:700}
@media(max-width:600px){.mm-bulk-toolbar{align-items:stretch}.mm-bulk-toolbar__action{flex-wrap:wrap}.mm-bulk-toolbar__action select{width:100%;min-width:0}.mm-bulk-toolbar__count{margin-left:0}}
</style>
<script>
(() => {
    const page = document.querySelector('[data-media-manager]');
    if (!page) return;
    const selectAll = page.querySelector('[data-mm-select-all]');
    const boxes = Array.from(page.querySelectorAll('[data-mm-bulk-checkbox]'));
    const count = page.querySelector('[data-mm-selected-count]');
    const refreshCount = () => {
        const selected = boxes.filter((box) => box.checked).length;
        if (count) count.textContent = selected + ' selected';
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && selected === boxes.length;
            selectAll.indeterminate = selected > 0 && selected < boxes.length;
        }
    };
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            boxes.forEach((box) => { box.checked = selectAll.checked; });
            refreshCount();
        });
    }
    boxes.forEach((box) => box.addEventListener('change', refreshCount));
    const form = page.querySelector('#mm-bulk-actions-form');
    if (form) {
        form.addEventListener('submit', (event) => {
            if (!boxes.some((box) => box.checked)) {
                event.preventDefault();
                window.alert('Select at least one media item first.');
            }
        });
    }
})();
</script>

</div>
@endsection
