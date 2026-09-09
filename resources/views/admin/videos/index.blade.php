@extends('layouts.admin')
@section('title','Videos')
@push('styles')
<link rel="stylesheet" href="/css/videos.css?v=20260908">
@endpush
@section('content')
@php
    $categories = \App\Models\ProductVideo::CATEGORIES;
    $duration = fn ($seconds) => sprintf('%02d:%02d', floor((float)$seconds / 60), (int)$seconds % 60);
    $videoData = $videos->getCollection()->map(fn ($video) => $video->details())->values();
    $colors = ['#005b32','#0066db','#ff9d00','#fb4b26','#8c4bb4','#55969a'];
@endphp
<div class="vd" data-video-dashboard data-store-url="{{ route('admin.videos.store') }}" data-bulk-url="{{ route('admin.videos.bulk') }}" data-csrf="{{ csrf_token() }}">
    <header class="vd-heading">
        <div><p class="vd-breadcrumb">Website &amp; Products <span>›</span> Product Media Manager <span>›</span> Videos</p><h1>Videos</h1><p>Manage product videos to showcase features, usage and lifestyle. Support for multiple formats and platforms.</p></div>
        <a class="vd-button vd-quiet" href="{{ route('admin.media.index') }}"><x-icon name="arrow-left" size="15" /> Media overview</a>
    </header>
    <div class="vd-notice" role="status" aria-live="polite" id="vd-notice" hidden></div>
    <div class="vd-layout">
    <div class="vd-main">
        <section class="vd-kpis" aria-label="Video metrics">
            @foreach([
                ['play','Total Videos',number_format($stats['total']),'Live library count','green'],
                ['package','Products with Videos',number_format($stats['products']),'Linked catalogue products','purple'],
                ['eye','Website Views (30 Days)',number_format($stats['views']),'One view per browser / day','orange'],
                ['clock','Watch Time (30 Days)',number_format($stats['seconds']/3600,1).' h','Recorded website playback','blue'],
                ['database','Storage Used',number_format($stats['bytes']/1048576,1).' MB','Known uploaded file sizes','teal'],
            ] as [$icon,$label,$value,$hint,$tone])
            <article class="vd-kpi"><span class="vd-kpi-icon vd-{{ $tone }}"><x-icon :name="$icon" size="23" /></span><div><h2>{{ $label }}</h2><strong>{{ $value }}</strong><small>{{ $hint }}</small></div></article>
            @endforeach
        </section>
        <section class="vd-library" aria-label="Video library">
            <div class="vd-tab-row">
                <nav class="vd-tabs" aria-label="Video categories">
                    <a href="{{ route('admin.videos.index',request()->except(['category','page'])) }}" class="{{ !request('category') ? 'is-active' : '' }}" @if(!request('category')) aria-current="page" @endif>All Videos</a>
                    @foreach($categories as $key=>$label)<a href="{{ route('admin.videos.index',array_merge(request()->except('page'),['category'=>$key])) }}" class="{{ request('category')===$key ? 'is-active' : '' }}" @if(request('category')===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach
                </nav>
                <button type="button" class="vd-button vd-primary" data-new-video><x-icon name="plus" size="16" /> Upload Video</button>
            </div>
            <form class="vd-filters" method="get" action="{{ route('admin.videos.index') }}" id="vd-filters">
                <label class="vd-search"><span class="vd-sr">Search videos</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Search videos by name, product, SKU…"><x-icon name="search" size="17" /></label>
                <label><span class="vd-sr">Product filter</span><select name="product_id"><option value="">All Products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id')==$product->id)>{{ $product->name }}</option>@endforeach</select></label>
                <label><span class="vd-sr">Type filter</span><select name="category"><option value="">All Types</option>@foreach($categories as $key=>$label)<option value="{{ $key }}" @selected(request('category')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="vd-sr">Status filter</span><select name="status"><option value="">All Statuses</option>@foreach(['published','scheduled','draft'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                <label><span class="vd-sr">Platform filter</span><select name="platform"><option value="">All Platforms</option>@foreach(['Website','YouTube','Vimeo'] as $platform)<option @selected(request('platform')===$platform)>{{ $platform }}</option>@endforeach</select></label>
                <button class="vd-button" type="submit"><x-icon name="filter" size="14" /> Filter</button>
                <a class="vd-reset" href="{{ route('admin.videos.index') }}">↺ Reset</a>
            </form>
            <div class="vd-bulkbar" id="vd-bulkbar" hidden><strong id="vd-selected-count">0 selected</strong><button type="button" data-bulk-action="publish">Publish</button><button type="button" data-bulk-action="draft">Move to draft</button><button type="button" data-bulk-action="delete" class="vd-danger">Delete</button><span>Privacy settings are preserved.</span></div>
            <div class="vd-table-scroll">
            <table class="vd-table">
                <thead><tr><th><input type="checkbox" id="vd-check-all" aria-label="Select all videos on this page"></th><th>Video</th><th>Product / SKU</th><th>Type</th><th>Duration</th><th>Resolution</th><th>Platform</th><th>Status</th><th>Views (30d)</th><th>Added on</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($videos as $video)
                @php($detail = $video->details())
                <tr data-video-row="{{ $video->id }}">
                    <td><input type="checkbox" class="vd-row-check" value="{{ $video->id }}" aria-label="Select {{ $video->title }}"></td>
                    <td><button type="button" class="vd-video-cell" data-select-video="{{ $video->id }}" aria-label="Preview {{ $video->title }}"><span class="vd-thumb">@if($detail['poster'])<img src="{{ $detail['poster'] }}" alt="" loading="lazy">@endif<span class="vd-play"><x-icon name="play" size="14" /></span><small>{{ $detail['duration'] ? $duration($detail['duration']) : '—' }}</small></span><span><strong>{{ $video->title }}</strong><small>{{ \Illuminate\Support\Str::limit($detail['description'] ?: 'Select to preview and edit',65) }}</small></span></button></td>
                    <td>{{ $video->product?->name }}<small>{{ $video->product?->sku }}</small></td>
                    <td><span class="vd-tag vd-type-{{ $detail['category'] }}">{{ $categories[$detail['category']] ?? 'Other' }}</span></td>
                    <td>{{ $detail['duration'] ? $duration($detail['duration']) : '—' }}</td>
                    <td>{{ $detail['resolution'] ?: '—' }}<small>{{ data_get($video->metadata,'format','Video') }}</small></td>
                    <td><span class="vd-platform {{ strtolower($video->platform) }}">●</span> {{ $video->platform }}</td>
                    <td><span class="vd-tag vd-status-{{ $detail['status'] }}">{{ ucfirst($detail['status']) }}</span>@if($detail['visibility']==='private')<small>Private</small>@endif</td>
                    <td>{{ $video->platform==='Website' ? number_format($video->plays_count) : '—' }}</td>
                    <td>{{ $video->created_at->format('d M Y') }}<small>{{ data_get($video->metadata,'added_by','Media library') }}</small></td>
                    <td><div class="vd-row-actions"><button type="button" data-select-video="{{ $video->id }}" aria-label="Preview {{ $video->title }}"><x-icon name="eye" size="14" /></button><button type="button" data-edit-video="{{ $video->id }}" aria-label="Edit {{ $video->title }}"><x-icon name="edit" size="14" /></button><button type="button" data-delete-video="{{ $video->id }}" aria-label="Delete {{ $video->title }}"><x-icon name="trash" size="14" /></button></div></td>
                </tr>
                @empty
                <tr><td colspan="11"><div class="vd-empty"><span class="vd-empty-icon"><x-icon name="play" size="30" /></span><h3>{{ request()->hasAny(['q','category','status','platform','product_id']) ? 'No videos match your filters' : 'Your video library starts here' }}</h3><p>Upload your first product video or add a YouTube or Vimeo link.</p><button type="button" class="vd-button vd-primary" data-new-video>Add a video</button></div></td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            <div class="vd-pagination">
                <span>Showing {{ $videos->firstItem() ?? 0 }} to {{ $videos->lastItem() ?? 0 }} of {{ number_format($videos->total()) }} videos</span>
                <nav aria-label="Video pages">
                    @if($videos->onFirstPage())<span aria-disabled="true">‹</span>@else<a href="{{ $videos->previousPageUrl() }}" aria-label="Previous page">‹</a>@endif
                    @for($page=max(1,$videos->currentPage()-2);$page<=min($videos->lastPage(),$videos->currentPage()+2);$page++)<a href="{{ $videos->url($page) }}" class="{{ $page===$videos->currentPage() ? 'is-active' : '' }}" @if($page===$videos->currentPage()) aria-current="page" @endif>{{ $page }}</a>@endfor
                    @if($videos->hasMorePages())<a href="{{ $videos->nextPageUrl() }}" aria-label="Next page">›</a>@else<span aria-disabled="true">›</span>@endif
                </nav>
                <label><span class="vd-sr">Videos per page</span><select name="per_page" form="vd-filters" data-page-size>@foreach([8,16,32] as $size)<option value="{{ $size }}" @selected($videos->perPage()===$size)>{{ $size }} / page</option>@endforeach</select></label>
            </div>
        </section>
        <p class="vd-data-note">Metrics show website playback from the last 30 days. Admin previews are excluded. External platform analytics are not connected.</p>
        <form id="vd-settings-form">
        <fieldset id="vd-edit-fields">
        <div class="vd-bottom-grid">
            <section class="vd-panel vd-upload-card">
                <h2>Upload Video</h2>
                <div class="vd-dropzone" id="vd-quick-drop"><x-icon name="upload" size="32" /><strong>Drag &amp; drop video files here</strong><span>or</span><button type="button" class="vd-button vd-primary" data-new-video>Choose Files</button></div>
                <p>MP4, WebM, MOV · Up to 20 MB per video.<br>For larger videos, add a YouTube or Vimeo link.</p>
                <button type="button" class="vd-text-button" data-guidelines>Upload Guidelines</button>
            </section>
            <section class="vd-panel" id="vd-settings">
                <h2>Video Settings</h2>
                <label class="vd-toggle">Add to Product Gallery<input type="checkbox" name="gallery" role="switch"></label>
                <label>Video Category<select name="category">@foreach($categories as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                <label>Privacy<select name="visibility"><option value="public">Public</option><option value="private">Private</option></select></label>
                <label class="vd-toggle">Allow Download<input type="checkbox" name="allow_download" role="switch"></label>
                <button class="vd-button vd-save" type="submit">Save Settings</button>
            </section>
            <section class="vd-panel">
                <h2>SEO &amp; Accessibility</h2>
                <label>Title (for SEO)<input name="seo_title" maxlength="160"></label>
                <label>Description<textarea name="description" rows="2" maxlength="3000"></textarea></label>
                <label>Tags<input name="tags" maxlength="500" placeholder="cap, signature cap, emerald"></label>
                <label>Caption language<select name="caption_language">@foreach(['en'=>'English','ga'=>'Irish','fr'=>'French','de'=>'German','es'=>'Spanish'] as $key=>$language)<option value="{{ $key }}">{{ $language }}</option>@endforeach</select></label>
                <button class="vd-button vd-save" type="submit">Save SEO &amp; Accessibility</button>
            </section>
            <section class="vd-panel vd-performance">
                <h2>Video Performance <small>(Last 30 Days)</small></h2>
                <dl><dt>Website Views</dt><dd id="vd-detail-views">—</dd><dt>Watch Time</dt><dd id="vd-detail-time">—</dd><dt>Avg. Watch Time</dt><dd id="vd-detail-average">—</dd></dl>
                <p>One view per browser, per video, per day. External playback is not measured.</p>
                <a class="vd-text-button" href="{{ route('admin.videos.export',request()->query()) }}">Export Analytics ↗</a>
            </section>
            <section class="vd-panel vd-preview-card">
                <h2>Video Preview</h2>
                <div class="vd-preview" id="vd-preview"><span>Select a video to preview</span></div>
                <strong id="vd-preview-title">No video selected</strong><small id="vd-preview-meta"></small>
                <div class="vd-preview-actions"><button type="button" class="vd-button" id="vd-fullscreen">Full Screen</button><button type="button" class="vd-button" id="vd-edit-selected">Edit Video</button></div>
            </section>
        </div>
        </fieldset>
        </form>
        <p class="vd-data-note" id="vd-editor-hint">Select a video from the library to edit its settings and accessibility details.</p>
    </div>
    <aside class="vd-rail">
        <section class="vd-panel vd-today"><x-icon name="calendar" size="28" /><div><strong>Today</strong><span>{{ now('Europe/Dublin')->format('l, j F Y') }}</span><time id="vd-clock">{{ now('Europe/Dublin')->format('H:i') }}</time><small>Europe / Dublin</small></div></section>
        <section class="vd-panel"><h2>Video Summary</h2><dl class="vd-summary">
            @foreach(['Total Videos'=>number_format($stats['total']),'Products with Videos'=>number_format($stats['products']),'Website Views (30 Days)'=>number_format($stats['views']),'Watch Time (30 Days)'=>number_format($stats['seconds']/3600,1).' h','Storage Used'=>number_format($stats['bytes']/1048576,1).' MB','Published'=>$stats['published'],'Scheduled'=>$stats['scheduled'],'Private'=>$stats['private']] as $label=>$value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
        </dl></section>
        <section class="vd-panel"><h2>Videos by Type</h2><div class="vd-types"><ul>@foreach($types as $key=>$count)<li><i style="background:{{ $colors[$loop->index] }}"></i><span>{{ $categories[$key] }}</span><b>{{ $count }}</b></li>@endforeach</ul><div class="vd-donut" id="vd-donut" data-counts="{{ $types->values()->toJson() }}"><div><strong>{{ number_format($stats['total']) }}</strong><small>Total</small></div></div></div></section>
        <section class="vd-panel"><h2>Top Website Videos (30d)</h2><ol class="vd-top-list">@forelse($top->where('plays_count','>',0) as $video)<li><a href="{{ route('admin.videos.index',['q'=>$video->title]) }}"><span class="vd-mini-play"><x-icon name="play" size="13" /></span><span>{{ $video->title }}</span><b>{{ number_format($video->plays_count) }}</b></a></li>@empty<li class="vd-muted">Playback data will appear after visitors watch your published videos.</li>@endforelse</ol><a class="vd-text-button" href="{{ route('admin.videos.export') }}">Export All Video Analytics →</a></section>
        <section class="vd-panel"><h2>Quick Actions</h2><div class="vd-quick-actions">
            <button type="button" data-new-video><x-icon name="upload" size="15" /> Upload New Video</button>
            <button type="button" data-new-video data-bulk-upload><x-icon name="package" size="15" /> Bulk Upload Videos</button>
            <a href="{{ route('admin.videos.export',request()->query()) }}"><x-icon name="download" size="15" /> Export Filtered Library</a>
            <a href="{{ route('videos.sitemap') }}" target="_blank" rel="noopener"><x-icon name="file" size="15" /> View Video Sitemap</a>
            <button type="button" data-guidelines><x-icon name="help" size="15" /> Upload Guidelines</button>
            <a href="#vd-settings"><x-icon name="settings" size="15" /> Video Settings</a>
        </div></section>
        <section class="vd-panel"><h2>UUID Traceability</h2><p>Every video is assigned a unique UUID for traceability.</p><strong class="vd-small-heading">Selected Video UUID</strong><div class="vd-uuid"><code id="vd-uuid">Select a video</code><button type="button" id="vd-copy-uuid" aria-label="Copy video UUID" disabled><x-icon name="copy" size="15" /></button></div><button type="button" class="vd-button vd-audit" id="vd-audit" disabled>View Video Audit Log <span>›</span></button></section>
    </aside>
    </div>
    <section class="vd-benefits" aria-label="Video tools">
        @foreach([['play','HIGH QUALITY VIDEOS','Showcase your products with clear, engaging videos.'],['globe','MULTI-PLATFORM SUPPORT','Upload videos or embed YouTube and Vimeo.'],['file','SEO & ACCESSIBILITY','Titles, descriptions, tags and WebVTT captions.'],['bar-chart','WEBSITE ANALYTICS','Recorded views and watch time from your website.'],['shield','SECURE & TRACEABLE','Private file delivery and an audited video history.']] as [$icon,$title,$copy])<div><x-icon :name="$icon" size="23" /><p><strong>{{ $title }}</strong><span>{{ $copy }}</span></p></div>@endforeach
    </section>
    <dialog id="vd-dialog" class="vd-dialog" aria-labelledby="vd-dialog-title">
        <form id="vd-form" enctype="multipart/form-data">
            <header><div><p>PRODUCT MEDIA MANAGER</p><h2 id="vd-dialog-title">Upload Video</h2></div><button type="button" data-close-dialog aria-label="Close dialog">×</button></header>
            <div class="vd-form-body">
                <div class="vd-form-errors" id="vd-form-errors" role="alert" hidden></div>
                <div class="vd-form-grid">
                    <label class="vd-wide">Video title<input name="title" required maxlength="160" placeholder="Emerald Signature Cap — Product Overview"></label>
                    <label>Product<select name="product_id" required><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} · {{ $product->sku }}</option>@endforeach</select></label>
                    <label>Video category<select name="category">@foreach($categories as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                    <label>Platform<select name="platform" id="vd-platform"><option>Website</option><option>YouTube</option><option>Vimeo</option></select></label>
                    <label>Publication status<select name="status" id="vd-status"><option value="draft">Draft</option><option value="published">Published</option><option value="scheduled">Scheduled</option></select></label>
                    <label id="vd-publish-field" hidden>Publish at (your local time)<input type="datetime-local" name="publish_at"></label>
                    <label id="vd-external-field" class="vd-wide" hidden>Video URL<input type="url" name="external_url" placeholder="https://www.youtube.com/watch?v=…"><small>Add an existing public video. This does not upload to the external platform.</small></label>
                    <div class="vd-wide" id="vd-upload-fields">
                        <label class="vd-file-drop" id="vd-file-drop"><x-icon name="upload" size="26" /><strong>Choose videos or drop files here</strong><span>MP4, WebM, MOV · 20 MB per file · Up to 10 files</span><input type="file" name="file" id="vd-files" accept="video/mp4,video/webm,video/quicktime" multiple></label>
                        <div id="vd-file-list" class="vd-file-list"></div>
                        <label class="vd-toggle">Generate thumbnail from uploaded video<input type="checkbox" id="vd-auto-poster" checked role="switch"></label>
                    </div>
                    <label>Thumbnail image<input type="file" name="poster" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, WebP · 2 MB</small></label>
                    <label>Captions / subtitles<input type="file" name="captions" accept=".vtt,text/vtt"><small>WebVTT (.vtt) · 512 KB</small></label>
                    <label>Privacy<select name="visibility"><option value="public">Public</option><option value="private">Private (admin only)</option></select></label>
                    <label>Caption language<select name="caption_language">@foreach(['en'=>'English','ga'=>'Irish','fr'=>'French','de'=>'German','es'=>'Spanish'] as $key=>$language)<option value="{{ $key }}">{{ $language }}</option>@endforeach</select></label>
                    <label class="vd-toggle">Add to product gallery<input type="checkbox" name="gallery" checked role="switch"></label>
                    <label class="vd-toggle">Allow download<input type="checkbox" name="allow_download" role="switch"></label>
                    <label class="vd-wide">SEO title<input name="seo_title" maxlength="160"></label>
                    <label class="vd-wide">Description<textarea name="description" rows="3" maxlength="3000"></textarea></label>
                    <label class="vd-wide">Tags<input name="tags" maxlength="500" placeholder="Comma-separated tags"></label>
                </div>
                <p class="vd-form-note">New uploads are stored privately. Public videos appear on the product page when published and “Add to product gallery” is enabled. External links retain the provider’s own privacy settings.</p>
                <progress id="vd-progress" value="0" max="100" hidden></progress><p id="vd-upload-status" role="status" aria-live="polite"></p>
            </div>
            <footer><button type="button" class="vd-button" data-close-dialog>Cancel</button><button type="submit" class="vd-button vd-primary" id="vd-submit">Save Video</button></footer>
        </form>
    </dialog>
    <dialog id="vd-info-dialog" class="vd-dialog vd-info-dialog" aria-labelledby="vd-info-title"><header><h2 id="vd-info-title">Video information</h2><button type="button" data-close-info aria-label="Close dialog">×</button></header><div id="vd-info-body" class="vd-form-body"></div><footer><button type="button" class="vd-button" data-close-info>Close</button></footer></dialog>
    <script type="application/json" id="vd-video-data">{!! json_encode($videoData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</div>
@endsection
@push('scripts')
<script src="/js/videos.js?v=20260908" defer></script>
@endpush
