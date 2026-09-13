@extends('layouts.admin')

@section('title', 'Public Media Library')

@push('styles')
    <link rel="stylesheet" href="/css/media-library.css?v=20260913-premium-library">
@endpush

@section('content')
@php
    $isTrash = $view === 'trash';
    $statusTone = static fn (?string $status): string => match ($status) {
        'approved' => 'green',
        'pending' => 'orange',
        'rejected' => 'red',
        'archived' => 'muted',
        default => 'muted',
    };
@endphp

<div class="media-library-screen" data-site-media-library>
    <header class="media-library-heading">
        <div>
            <p class="media-library-eyebrow">WEBSITE &amp; PRODUCTS / PUBLIC DELIVERY</p>
            <h1>{{ $isTrash ? 'Media Trash' : 'Public Media Library' }}</h1>
            <p>One controlled library for the approved photography, campaign artwork and video used by public pages.</p>
        </div>
        <div class="media-library-heading-actions">
            <a class="media-library-button media-library-button--soft" href="{{ $isTrash ? route('admin.site-media.index') : route('admin.site-media.trash') }}"><x-icon name="{{ $isTrash ? 'arrow-left' : 'trash' }}" size="15" /> {{ $isTrash ? 'Back to library' : 'Open trash' }}</a>
            <a class="media-library-button" href="{{ route('admin.media.index') }}"><x-icon name="camera" size="15" /> Product media</a>
        </div>
    </header>

    @if(session('success'))<div class="media-library-alert media-library-alert--success" role="status"><x-icon name="check" size="15" /> {{ session('success') }}</div>@endif
    @if($errors->any())<div class="media-library-alert media-library-alert--error" role="alert"><x-icon name="alert" size="15" /> {{ $errors->first() }}</div>@endif

    <section class="media-library-stats" aria-label="Media library summary">
        <a class="media-library-stat media-library-stat--green" href="{{ route('admin.site-media.index', ['status' => 'approved']) }}"><span class="media-library-stat-icon"><x-icon name="check" size="18" /></span><span><small>Approved &amp; live</small><strong>{{ number_format($stats['approved']) }}</strong><em>Public delivery ready</em></span></a>
        <a class="media-library-stat media-library-stat--orange" href="{{ route('admin.site-media.index', ['status' => 'pending']) }}"><span class="media-library-stat-icon"><x-icon name="clock" size="18" /></span><span><small>Awaiting approval</small><strong>{{ number_format($stats['pending']) }}</strong><em>Needs review</em></span></a>
        <a class="media-library-stat media-library-stat--blue" href="{{ route('admin.site-media.index') }}"><span class="media-library-stat-icon"><x-icon name="image" size="18" /></span><span><small>Total library</small><strong>{{ number_format($stats['total']) }}</strong><em>Tenant-visible assets</em></span></a>
        <a class="media-library-stat media-library-stat--red" href="{{ route('admin.site-media.index', ['status' => 'rejected']) }}"><span class="media-library-stat-icon"><x-icon name="alert" size="18" /></span><span><small>Needs attention</small><strong>{{ number_format($stats['attention']) }}</strong><em>Rejected or archived</em></span></a>
        <a class="media-library-stat media-library-stat--muted" href="{{ route('admin.site-media.trash') }}"><span class="media-library-stat-icon"><x-icon name="trash" size="18" /></span><span><small>Trash</small><strong>{{ number_format($stats['trash']) }}</strong><em>Recoverable records</em></span></a>
    </section>

    @if(! $isTrash)
        <section class="media-library-upload media-library-card">
            <div class="media-library-upload-copy"><span class="media-library-upload-icon"><x-icon name="upload" size="20" /></span><div><h2>Upload public media</h2><p>Upload an original, add accessible metadata, then approve it before selecting it in a public page.</p><small>Images and MP4/WebM video · up to 50 MB · responsive derivatives remain versioned.</small></div></div>
            <form method="post" action="{{ route('admin.site-media.store') }}" enctype="multipart/form-data" class="media-library-upload-form">
                @csrf
                <label class="media-library-field media-library-field--file"><span>File</span><input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/avif,image/gif,video/mp4,video/webm" required></label>
                <label class="media-library-field"><span>Asset name</span><input type="text" name="name" maxlength="180" placeholder="Homepage hero campaign"></label>
                <label class="media-library-field"><span>Alt text</span><input type="text" name="alt_text" maxlength="255" placeholder="Describe the media for customers"></label>
                <button class="media-library-button" type="submit"><x-icon name="upload" size="15" /> Upload for approval</button>
            </form>
        </section>
    @endif

    <section class="media-library-card media-library-catalogue">
        <div class="media-library-card-heading"><div><p class="media-library-eyebrow">CONTROLLED ASSET INVENTORY</p><h2>{{ $isTrash ? 'Recoverable media' : 'Approved media workspace' }}</h2><p>{{ $isTrash ? 'Restore a record to its previous approval state or permanently remove it when no references remain.' : 'Preview each asset, see where it is used, and manage its approval and version lifecycle.' }}</p></div><span class="media-library-results">{{ number_format($assets->total()) }} result{{ $assets->total() === 1 ? '' : 's' }}</span></div>
        <form method="get" class="media-library-toolbar" action="{{ $isTrash ? route('admin.site-media.trash') : route('admin.site-media.index') }}">
            <label class="media-library-search"><span class="sr-only">Search media</span><x-icon name="search" size="16" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Search by name or alt text"></label>
            <label class="media-library-filter"><span class="sr-only">Filter by status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $value)<option value="{{ $value }}" @selected($status === $value)>{{ str($value)->headline() }}</option>@endforeach</select></label>
            <button class="media-library-button media-library-button--soft" type="submit"><x-icon name="filter" size="14" /> Apply filters</button>
            @if(request('q') || $status)<a class="media-library-clear" href="{{ $isTrash ? route('admin.site-media.trash') : route('admin.site-media.index') }}">Clear filters</a>@endif
        </form>

        <div class="media-library-grid">
            @forelse($assets as $asset)
                @php
                    $assetUses = $usageByAsset->get($asset->id, []);
                    $assetVersions = $versionsByAsset->get($asset->id, collect());
                    $focal = is_array($asset->focal_point) ? $asset->focal_point : [];
                    $crop = is_array($asset->crop) ? $asset->crop : [];
                    $mediaIsVideo = str_starts_with((string) $asset->mime_type, 'video/');
                    $mediaPreviewUrl = $asset->trashed() ? null : route('admin.site-media.preview', ['asset' => $asset->uuid]);
                    $originalName = data_get($asset->metadata, 'original_name') ?: basename((string) $asset->path);
                    $currentVersion = max(1, (int) data_get($asset->metadata, 'current_version', 1));
                @endphp
                <article class="media-asset-card" data-media-asset-card>
                    <div class="media-asset-preview {{ $mediaIsVideo ? 'media-asset-preview--video' : '' }}" data-media-preview>
                        @if($mediaPreviewUrl && $mediaIsVideo)
                            <video src="{{ $mediaPreviewUrl }}" preload="metadata" controls muted playsinline aria-label="Preview {{ $asset->name }}"></video>
                        @elseif($mediaPreviewUrl)
                            <img src="{{ $mediaPreviewUrl }}" alt="{{ $asset->alt_text ?: $asset->name }}" loading="lazy">
                        @else
                            <span class="media-asset-placeholder"><x-icon name="trash" size="28" /><small>Retained in trash</small></span>
                        @endif
                        <span class="media-asset-status media-asset-status--{{ $statusTone($asset->approval_status) }}"><i></i>{{ str($asset->approval_status)->headline() }}</span>
                        @if($mediaPreviewUrl && $asset->approval_status === 'approved' && $asset->active)<a class="media-asset-open" href="{{ route('media.public', ['uuid' => $asset->uuid]) }}" target="_blank" rel="noopener" aria-label="Open {{ $asset->name }} publicly"><x-icon name="external-link" size="14" /></a>@endif
                    </div>
                    <div class="media-asset-body">
                        <div class="media-asset-title"><div><h3>{{ $asset->name }}</h3><p>{{ $originalName }}</p></div><span class="media-asset-version">v{{ $currentVersion }}</span></div>
                        <dl class="media-asset-meta"><div><dt>Format</dt><dd>{{ $mediaIsVideo ? 'Video' : 'Image' }} · {{ strtoupper(str_replace('image/', '', str_replace('video/', '', (string) $asset->mime_type))) ?: 'Unknown' }}</dd></div><div><dt>Size</dt><dd>{{ $asset->width && $asset->height ? $asset->width.' × '.$asset->height : 'Not detected' }} · {{ number_format((int) $asset->bytes) }} bytes</dd></div></dl>
                        <p class="media-asset-alt"><x-icon name="info" size="13" /> {{ $asset->alt_text ?: 'Alt text not supplied' }}</p>
                        <div class="media-asset-usage"><span><x-icon name="link" size="13" /> {{ count($assetUses) }} reference{{ count($assetUses) === 1 ? '' : 's' }}</span>@if($asset->approved_at)<span>Approved {{ optional($asset->approved_at)->format('d M Y') }}</span>@endif</div>

                        <div class="media-asset-actions">
                            @if($isTrash)
                                <form method="post" action="{{ route('admin.site-media.restore', $asset->uuid) }}">@csrf<button class="media-asset-action media-asset-action--primary" type="submit"><x-icon name="rotate-ccw" size="13" /> Restore</button></form>
                                <form method="post" action="{{ route('admin.site-media.permanent-destroy', $asset->uuid) }}" onsubmit="return confirm('Permanently delete this asset and all retained versions? This cannot be undone.')">@csrf @method('DELETE')<button class="media-asset-action media-asset-action--danger" type="submit" @disabled($assetUses !== []) title="{{ $assetUses !== [] ? 'Remove all references before permanently deleting this asset.' : 'Delete permanently' }}"><x-icon name="trash" size="13" /> Delete</button></form>
                            @else
                                @if($asset->approval_status !== 'approved')<form method="post" action="{{ route('admin.site-media.approve', $asset) }}">@csrf<button class="media-asset-action media-asset-action--primary" type="submit"><x-icon name="check" size="13" /> Approve</button></form>@endif
                                @if($asset->approval_status !== 'rejected' && $asset->approval_status !== 'archived')<form method="post" action="{{ route('admin.site-media.reject', $asset) }}">@csrf<button class="media-asset-action" type="submit">Reject</button></form>@endif
                                @if($asset->approval_status !== 'archived')<form method="post" action="{{ route('admin.site-media.archive', $asset->uuid) }}" onsubmit="return confirm('Archive this asset? It will stop public delivery until restored.')">@csrf<button class="media-asset-action" type="submit">Archive</button></form>@else<form method="post" action="{{ route('admin.site-media.restore', $asset->uuid) }}">@csrf<button class="media-asset-action" type="submit">Unarchive</button></form>@endif
                                <form method="post" action="{{ route('admin.site-media.destroy', $asset) }}" onsubmit="return confirm('Move this asset to trash? Its source and versions will be retained.')">@csrf @method('DELETE')<button class="media-asset-action media-asset-action--danger" type="submit"><x-icon name="trash" size="13" /> Trash</button></form>
                            @endif
                        </div>

                        @if(! $isTrash)
                            <details class="media-asset-details"><summary><x-icon name="pencil" size="13" /> Edit details</summary>
                                <form method="post" action="{{ route('admin.site-media.update', $asset) }}" enctype="multipart/form-data" class="media-asset-edit-form">
                                    @csrf @method('PATCH')
                                    <label class="media-library-field"><span>Name</span><input name="name" value="{{ $asset->name }}" maxlength="180" required></label>
                                    <label class="media-library-field"><span>Alt text</span><input name="alt_text" value="{{ $asset->alt_text }}" maxlength="255" placeholder="Alt text"></label>
                                    <label class="media-library-field media-library-field--wide"><span>Replace file</span><input type="file" name="replace_file" accept="image/jpeg,image/png,image/webp,image/avif,image/gif,video/mp4,video/webm"></label>
                                    <div class="media-edit-grid"><label class="media-library-field"><span>Focal X (%)</span><input type="number" name="focal_x" min="0" max="100" step="0.1" value="{{ $focal['x'] ?? '' }}"></label><label class="media-library-field"><span>Focal Y (%)</span><input type="number" name="focal_y" min="0" max="100" step="0.1" value="{{ $focal['y'] ?? '' }}"></label><label class="media-library-field"><span>Crop mode</span><select name="crop_mode"><option value="">No crop override</option>@foreach(['cover','contain','none'] as $mode)<option value="{{ $mode }}" @selected(($crop['mode'] ?? '') === $mode)>{{ str($mode)->headline() }}</option>@endforeach</select></label><label class="media-library-field"><span>Active after approval</span><input type="checkbox" name="active" value="1" @checked($asset->active)></label></div>
                                    <details class="media-asset-advanced"><summary>Advanced crop coordinates</summary><div class="media-edit-grid"><label class="media-library-field"><span>Crop X (%)</span><input type="number" name="crop_x" min="0" max="100" step="0.1" value="{{ $crop['x'] ?? '' }}"></label><label class="media-library-field"><span>Crop Y (%)</span><input type="number" name="crop_y" min="0" max="100" step="0.1" value="{{ $crop['y'] ?? '' }}"></label><label class="media-library-field"><span>Crop width (%)</span><input type="number" name="crop_width" min="0.1" max="100" step="0.1" value="{{ $crop['width'] ?? '' }}"></label><label class="media-library-field"><span>Crop height (%)</span><input type="number" name="crop_height" min="0.1" max="100" step="0.1" value="{{ $crop['height'] ?? '' }}"></label></div></details>
                                    <button class="media-library-button media-library-button--small" type="submit"><x-icon name="check" size="13" /> Save details</button>
                                </form>
                            </details>
                        @endif
                        <details class="media-asset-details"><summary><x-icon name="link" size="13" /> Used in {{ count($assetUses) }} record{{ count($assetUses) === 1 ? '' : 's' }}</summary>@if($assetUses !== [])<ul class="media-asset-reference-list">@foreach($assetUses as $use)<li><a href="{{ $use['href'] }}"><span>{{ $use['label'] }}</span><small>{{ $use['detail'] }}</small><x-icon name="arrow-right" size="12" /></a></li>@endforeach</ul>@else<p class="media-asset-no-references">Not currently referenced by a public or managed record.</p>@endif</details>
                        @if($assetVersions->count() > 0)<details class="media-asset-details"><summary><x-icon name="refresh" size="13" /> Version history <span>({{ $assetVersions->count() }})</span></summary><ul class="media-asset-version-list">@foreach($assetVersions as $version)<li><span><strong>Version {{ $version->version }}</strong><small>{{ optional($version->created_at)->format('d M Y, H:i') }} · {{ basename($version->path) }}</small></span>@if(! $isTrash)<form method="post" action="{{ route('admin.site-media.version.restore', ['asset' => $asset->uuid, 'version' => $version->version]) }}" onsubmit="return confirm('Restore version {{ $version->version }}? The restored content will require approval.')">@csrf<button type="submit">Restore version</button></form>@endif</li>@endforeach</ul></details>@endif
                    </div>
                </article>
            @empty
                <div class="media-library-empty"><span class="media-library-empty-icon"><x-icon name="image" size="25" /></span><h3>{{ $isTrash ? 'Trash is empty' : 'No public media yet' }}</h3><p>{{ $isTrash ? 'Deleted media records will remain here until they are restored or permanently removed.' : 'Upload an image or video to start building the approved public media library.' }}</p></div>
            @endforelse
        </div>
        <div class="media-library-pagination">{{ $assets->links() }}</div>
    </section>
</div>
@endsection
