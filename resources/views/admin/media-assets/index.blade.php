@extends('layouts.admin')

@section('title', 'Public Media Library')

@section('content')
<div class="admin-page" data-site-media-library>
    <header class="admin-page-header">
        <div>
            <p class="eyebrow">WEBSITE &amp; PRODUCTS / PUBLIC DELIVERY</p>
            <h1>{{ $view === 'trash' ? 'Media Trash' : 'Public Media Library' }}</h1>
            <p>Only approved, active and tenant-visible media can be rendered by public routes or selected in the page builder.</p>
        </div>
        <div class="admin-action-row">
            <a class="btn ghost" href="{{ $view === 'trash' ? route('admin.site-media.index') : route('admin.site-media.trash') }}">{{ $view === 'trash' ? 'Back to library' : 'Open trash' }} <x-icon name="arrow-right" size="14" /></a>
            <a class="btn" href="{{ route('admin.media.index') }}">Product media <x-icon name="arrow-right" size="14" /></a>
        </div>
    </header>

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif

    @if($view !== 'trash')
    <section class="admin-card">
        <form method="post" action="{{ route('admin.site-media.store') }}" enctype="multipart/form-data" class="admin-form-grid">
            @csrf
            <label>File<input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/avif,image/gif,video/mp4,video/webm" required></label>
            <label>Name<input type="text" name="name" maxlength="180" placeholder="Homepage hero campaign"></label>
            <label>Alt text<input type="text" name="alt_text" maxlength="255" placeholder="Describe the image for customers"></label>
            <button class="btn" type="submit">Upload for approval <x-icon name="upload" size="14" /></button>
        </form>
        <p class="form-hint">Uploads are inactive and private until an administrator approves them. The original file, responsive derivatives and metadata remain versioned.</p>
    </section>
    @endif

    <section class="admin-card">
        <form method="get" class="admin-toolbar" action="{{ $view === 'trash' ? route('admin.site-media.trash') : route('admin.site-media.index') }}">
            <label>Search<input type="search" name="q" value="{{ request('q') }}" placeholder="Name or alt text"></label>
            <label>Status<select name="status" onchange="this.form.submit()"><option value="">All statuses</option>@foreach($statuses as $value)<option value="{{ $value }}" @selected($status === $value)>{{ str($value)->headline() }}</option>@endforeach</select></label>
            <button class="btn ghost" type="submit">Apply</button>
        </form>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Asset</th><th>Dimensions</th><th>Status</th><th>Public UUID</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($assets as $asset)
                    @php
                        $assetUses = $usageByAsset->get($asset->id, []);
                        $assetVersions = $versionsByAsset->get($asset->id, collect());
                        $focal = is_array($asset->focal_point) ? $asset->focal_point : [];
                        $crop = is_array($asset->crop) ? $asset->crop : [];
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $asset->name }}</strong>
                            <small>{{ basename($asset->path) }}{{ $asset->trashed() ? ' · trashed' : '' }}</small>
                            @if($assetUses !== [])
                                <details><summary>Used in {{ count($assetUses) }} record(s)</summary><ul class="admin-compact-list">@foreach($assetUses as $use)<li><a href="{{ $use['href'] }}">{{ $use['label'] }}: {{ $use['detail'] }}</a></li>@endforeach</ul></details>
                            @else
                                <small>Not currently referenced</small>
                            @endif
                        </td>
                        <td>{{ $asset->width && $asset->height ? $asset->width.' × '.$asset->height : 'Not detected' }}<small>{{ $asset->mime_type ?: 'Unknown type' }} · {{ number_format((int) $asset->bytes).' bytes' }}</small></td>
                        <td><span class="status-badge status-{{ $asset->approval_status }}">{{ str($asset->approval_status)->headline() }}</span>@if($asset->deleted_at)<small>Deleted {{ optional($asset->deleted_at)->format('Y-m-d H:i') }}</small>@endif</td>
                        <td><code>{{ $asset->uuid }}</code></td>
                        <td>
                            <div class="admin-action-row">
                                @if($view === 'trash')
                                    <form method="post" action="{{ route('admin.site-media.restore', $asset->uuid) }}" onsubmit="return confirm('Restore this media to its previous approval state?')">@csrf<button type="submit">Restore</button></form>
                                    <form method="post" action="{{ route('admin.site-media.permanent-destroy', $asset->uuid) }}" onsubmit="return confirm('Permanently delete this asset and all retained versions? This cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="is-danger" @disabled($assetUses !== [])>Delete permanently</button></form>
                                @else
                                    @if($asset->approval_status !== 'approved')<form method="post" action="{{ route('admin.site-media.approve', $asset) }}">@csrf<button type="submit">Approve</button></form>@endif
                                    @if($asset->approval_status !== 'rejected' && $asset->approval_status !== 'archived')<form method="post" action="{{ route('admin.site-media.reject', $asset) }}">@csrf<button type="submit">Reject</button></form>@endif
                                    @if($asset->approval_status === 'approved' && $asset->active)<a href="{{ route('media.public', $asset->uuid) }}" target="_blank" rel="noopener">Open</a>@endif
                                    @if($asset->approval_status !== 'archived')<form method="post" action="{{ route('admin.site-media.archive', $asset->uuid) }}" onsubmit="return confirm('Archive this asset? It will stop public delivery until restored.')">@csrf<button type="submit">Archive</button></form>@else<form method="post" action="{{ route('admin.site-media.restore', $asset->uuid) }}">@csrf<button type="submit">Unarchive</button></form>@endif
                                    <form method="post" action="{{ route('admin.site-media.destroy', $asset) }}" onsubmit="return confirm('Move this asset to trash? Its source and versions will be retained.')">@csrf @method('DELETE')<button type="submit">Trash</button></form>
                                @endif
                            </div>
                            @if($view !== 'trash')
                                <details><summary>Edit details</summary>
                                    <form method="post" action="{{ route('admin.site-media.update', $asset) }}" enctype="multipart/form-data" class="admin-inline-form">
                                        @csrf @method('PATCH')
                                        <label>Name<input name="name" value="{{ $asset->name }}" maxlength="180" required></label>
                                        <label>Alt text<input name="alt_text" value="{{ $asset->alt_text }}" maxlength="255" placeholder="Alt text"></label>
                                        <label>Replace file<input type="file" name="replace_file" accept="image/jpeg,image/png,image/webp,image/avif,image/gif,video/mp4,video/webm"></label>
                                        <label>Focal X (%)<input type="number" name="focal_x" min="0" max="100" step="0.1" value="{{ $focal['x'] ?? '' }}"></label>
                                        <label>Focal Y (%)<input type="number" name="focal_y" min="0" max="100" step="0.1" value="{{ $focal['y'] ?? '' }}"></label>
                                        <label>Crop mode<select name="crop_mode"><option value="">No crop override</option>@foreach(['cover','contain','none'] as $mode)<option value="{{ $mode }}" @selected(($crop['mode'] ?? '') === $mode)>{{ str($mode)->headline() }}</option>@endforeach</select></label>
                                        <label>Crop X (%)<input type="number" name="crop_x" min="0" max="100" step="0.1" value="{{ $crop['x'] ?? '' }}"></label>
                                        <label>Crop Y (%)<input type="number" name="crop_y" min="0" max="100" step="0.1" value="{{ $crop['y'] ?? '' }}"></label>
                                        <label>Crop width (%)<input type="number" name="crop_width" min="0.1" max="100" step="0.1" value="{{ $crop['width'] ?? '' }}"></label>
                                        <label>Crop height (%)<input type="number" name="crop_height" min="0.1" max="100" step="0.1" value="{{ $crop['height'] ?? '' }}"></label>
                                        <label><input type="checkbox" name="active" value="1" @checked($asset->active)> Active after approval</label>
                                        <button type="submit">Save details</button>
                                    </form>
                                </details>
                            @endif
                            @if($assetVersions->count() > 0)
                                <details><summary>Version history ({{ $assetVersions->count() }})</summary><ul class="admin-compact-list">
                                    @foreach($assetVersions as $version)
                                        <li><span>Version {{ $version->version }} · {{ optional($version->created_at)->format('Y-m-d H:i') }} · {{ basename($version->path) }}</span>@if($view !== 'trash')<form method="post" action="{{ route('admin.site-media.version.restore', ['asset' => $asset->uuid, 'version' => $version->version]) }}" onsubmit="return confirm('Restore version {{ $version->version }}? The restored content will require approval.')">@csrf<button type="submit">Restore version</button></form>@endif</li>
                                    @endforeach
                                </ul></details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ $view === 'trash' ? 'Trash is empty.' : 'No public media assets have been uploaded.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $assets->links() }}
    </section>
</div>
@endsection
