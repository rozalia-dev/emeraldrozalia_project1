@extends('layouts.admin')
@section('title','360° Product View')
@push('styles')<link rel="stylesheet" href="/css/spins.css">@endpush
@push('scripts')<script src="/js/spin-viewer.js" defer></script><script src="/js/spins-admin.js" defer></script>@endpush
@section('content')
<div class="sd" data-spin-dashboard>
<header class="sd-heading">
    <div>
        <p class="sd-breadcrumb">Website &amp; Products › Product Media Manager › 360° Product View</p>
        <h1>360° Product View</h1>
        <p>Create, manage and publish interactive 360° product views to give customers a complete look from every angle.</p>
    </div>
</header>
@if(session('success'))<p class="sd-notice" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="sd-errors" role="alert"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="sd-layout"><main>
<section class="sd-kpis" aria-label="360° statistics">
@foreach([
    ['refresh','Total 360° Views',$stats['total'],'green','Current library'],
    ['package','Published',$stats['published'],'purple','Published now'],
    ['clock','In Progress',$stats['in_progress'],'orange','Workflow queue'],
    ['eye','Total Views (30 Days)',$stats['views'],'blue','Recorded viewer visits'],
    ['database','Storage Used',number_format($stats['bytes']/1073741824,2).' GB','teal','Private optimized frames']
] as [$icon,$label,$value,$color,$note])
<article><span class="sd-kpi-icon {{ $color }}"><x-icon :name="$icon" size="23" /></span><div><h2>{{ $label }}</h2><strong>{{ $value }}</strong><small>{{ $note }}</small></div></article>
@endforeach
</section>
<section class="sd-card sd-library">
<div class="sd-tabs">
    <a href="{{ route('admin.spins.index') }}" @class(['active'=>!request('status')])>All 360° Views</a>
    @foreach($statuses as $key=>$label)<a href="{{ route('admin.spins.index',array_merge(request()->except(['page','edit']),['status'=>$key])) }}" @class(['active'=>request('status')===$key])>{{ $label }}</a>@endforeach
    <button type="button" class="sd-button" data-create>＋ Create 360° View</button>
</div>
<form class="sd-filters" method="get" action="{{ route('admin.spins.index') }}">
    <label class="sd-search"><span class="sd-sr">Search</span><input name="q" placeholder="Search 360° views, product, SKU…" value="{{ request('q') }}" maxlength="150"></label>
    <button class="sd-button sd-outline"><x-icon name="filter" size="15" /> Filters</button>
    <label><span class="sd-sr">Product</span><select name="product_id"><option value="">All Products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id')==$product->id)>{{ $product->name }}</option>@endforeach</select></label>
    <label><span class="sd-sr">Status</span><select name="status"><option value="">All Statuses</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label><span class="sd-sr">Type</span><select name="category"><option value="">All Types</option>@foreach($categories as $key=>$label)<option value="{{ $key }}" @selected(request('category')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label><span class="sd-sr">Device</span><select name="device"><option value="">All Devices</option><option value="mobile" @selected(request('device')==='mobile')>Mobile enabled</option><option value="desktop" @selected(request('device')==='desktop')>Desktop only</option></select></label>
    <a class="sd-reset" href="{{ route('admin.spins.index') }}">↻ Reset</a>
</form>
<form method="post" action="{{ route('admin.spins.bulk') }}" data-bulk>@csrf
<div class="sd-table"><table><thead><tr><th><input type="checkbox" data-select-all aria-label="Select all visible views"></th><th>Preview</th><th>Product / SKU</th><th>Type</th><th>Frames</th><th>Resolution</th><th>Status</th><th>Platform</th><th>Views (30D)</th><th>Last Updated</th><th>Actions</th></tr></thead><tbody>
@forelse($spins as $spin)
<tr @class(['sd-selected'=>$selected?->id===$spin->id])>
<td><input type="checkbox" name="ids[]" value="{{ $spin->id }}" aria-label="Select {{ $spin->title }}"></td>
<td><a href="{{ route('admin.spins.index',array_merge(request()->query(),['edit'=>$spin->id])) }}#editor" class="sd-thumb"><img src="{{ route('spins.frame',[$spin->uuid,0]) }}" alt="{{ $spin->title }}" loading="lazy"><span>360°</span></a></td>
<td><strong>{{ $spin->product->name }}</strong><small>SKU: {{ $spin->product->sku }}</small></td>
<td><span class="sd-tag {{ $spin->category }}">{{ $categories[$spin->category] }}</span></td>
<td>{{ count($spin->frames) }}</td><td>{{ $spin->resolution }}</td>
<td><span class="sd-tag {{ $spin->status }}">{{ $statuses[$spin->status] }}</span></td>
<td>◎ Web</td><td>{{ number_format($spin->visits_count) }}</td>
<td>{{ $spin->updated_at->format('d M Y') }}<small>by {{ $spin->updated_by }}</small></td>
<td><div class="sd-actions"><a href="{{ route('spins.show',$spin->uuid) }}" target="_blank" rel="noopener" aria-label="Preview {{ $spin->title }}"><x-icon name="eye" size="16" /></a><a href="{{ route('admin.spins.index',array_merge(request()->query(),['edit'=>$spin->id])) }}#editor" aria-label="Edit {{ $spin->title }}"><x-icon name="edit" size="16" /></a><button type="button" data-audit="{{ route('admin.spins.audit',$spin->id) }}" aria-label="Audit history for {{ $spin->title }}">⋮</button></div></td>
</tr>
@empty
<tr><td colspan="11"><div class="sd-empty"><x-icon name="refresh" size="36" /><h2>{{ request()->hasAny(['q','status','category','product_id'])?'No matching views':'Your 360° library starts here' }}</h2><p>Upload a frame ZIP and link it to a product to create your first interactive view.</p><button class="sd-button" type="button" data-create>Create 360° View</button></div></td></tr>
@endforelse
</tbody></table></div>
<div class="sd-table-footer">
    <span>Showing {{ $spins->firstItem()??0 }} to {{ $spins->lastItem()??0 }} of {{ $spins->total() }} 360° views</span>
    <nav class="sd-pagination" aria-label="360° view pages">
        @if($spins->previousPageUrl())<a href="{{ $spins->previousPageUrl() }}" aria-label="Previous page">‹</a>@endif
        @if($pageStart>1)<a href="{{ $spins->url(1) }}">1</a>@if($pageStart>2)<span>…</span>@endif @endif
        @for($page=$pageStart;$page<=$pageEnd;$page++)<a href="{{ $spins->url($page) }}" @class(['active'=>$page===$spins->currentPage()]) aria-current="{{ $page===$spins->currentPage()?'page':'false' }}">{{ $page }}</a>@endfor
        @if($pageEnd<$spins->lastPage())@if($pageEnd<$spins->lastPage()-1)<span>…</span>@endif<a href="{{ $spins->url($spins->lastPage()) }}">{{ $spins->lastPage() }}</a>@endif
        @if($spins->nextPageUrl())<a href="{{ $spins->nextPageUrl() }}" aria-label="Next page">›</a>@endif
    </nav>
    <label><span class="sd-sr">Rows per page</span><select data-per-page>@foreach([8,20,40] as $n)<option value="{{ $n }}" @selected($spins->perPage()===$n)>{{ $n }} / page</option>@endforeach</select></label>
</div>
<div class="sd-bulk"><label>Selected views<select name="action"><option value="draft">Move to Draft</option><option value="published">Publish</option><option value="archived">Archive</option><option value="delete">Delete permanently</option></select></label><button class="sd-button sd-outline" type="submit">Apply</button><a href="{{ route('admin.spins.export',request()->except(['page','edit'])) }}">Export CSV</a><small data-selection>0 selected</small></div>
</form>
</section>

<section id="editor" class="sd-workbench" aria-label="360° workbench">
    @include('admin.spins.form',['record'=>$selected,'embedded'=>true])
    <section class="sd-card sd-performance-card">
        <h2>Performance <small>(Last 30 Days)</small></h2>
        <div class="sd-performance-grid"><div><span>Total Views</span><strong>{{ number_format($stats['views']) }}</strong></div><div><span>Unique Views</span><strong>{{ number_format($stats['unique']) }}</strong></div><div><span>Engagement Rate</span><strong>{{ $stats['views']?number_format(100*$stats['engaged']/$stats['views'],1).' %':'—' }}</strong></div><div><span>Avg. Load</span><strong>{{ $stats['load_ms']?number_format($stats['load_ms']/1000,2).' s':'—' }}</strong></div></div>
        <small>Real session-based measurements. Admin previews are excluded.</small>
    </section>
    <section class="sd-card sd-preview-card">
        <h2>Preview</h2>
        @if($selected)<x-spin-viewer :spin="$selected" :preview="true" /><a class="sd-button sd-outline sd-wide" href="{{ route('spins.show',$selected->uuid) }}" target="_blank" rel="noopener">Open 360° Viewer</a>@else<p>Select a view to preview it here.</p><button class="sd-button sd-outline sd-wide" type="button" data-create>Create 360° View</button>@endif
    </section>
</section>
</main>

<aside class="sd-sidebar">
<section class="sd-card sd-date"><x-icon name="calendar" size="27" /><div><strong>Today</strong><small>{{ now()->format('l, j F Y') }}</small><b>{{ now()->format('g:i A') }}</b></div></section>
<section class="sd-card"><h2>360° Summary</h2><dl><dt>Total 360° Views</dt><dd>{{ $stats['total'] }}</dd>@foreach($statuses as $key=>$label)<dt>{{ $label }}</dt><dd>{{ $stats[$key] }}</dd>@endforeach<dt>Total Views (30 Days)</dt><dd>{{ $stats['views'] }}</dd><dt>Storage Used</dt><dd>{{ number_format($stats['bytes']/1073741824,2) }} GB</dd><dt>Avg. Load Time</dt><dd>{{ $stats['load_ms']?number_format($stats['load_ms']/1000,2).' s':'—' }}</dd></dl></section>
<section class="sd-card sd-by-type"><h2>360° by Type</h2>
    <div class="sd-type-layout">
        <div class="sd-type-legend">
            @foreach($categories as $key=>$label)<div><i class="{{ $key }}"></i><span>{{ $label }}</span><strong>{{ $types[$key]??0 }} ({{ $stats['total']?number_format(100*($types[$key]??0)/$stats['total'],1):'0.0' }}%)</strong></div>@endforeach
        </div>
        <div class="sd-donut" style="--a1:{{ $angleProduct }}deg;--a2:{{ $angleLifestyle }}deg;--a3:{{ $anglePromotional }}deg"><span><strong>{{ $stats['total'] }}</strong><small>Total</small></span></div>
    </div>
</section>
<section class="sd-card sd-quick"><h2>Quick Actions</h2>
    <button type="button" data-create>↥ Create 360° View</button>
    <button type="button" data-create>◎ Bulk Upload 360° (ZIP)</button>
    <a href="#editor" data-focus-category>◉ Manage 360° Categories</a>
    <a href="#editor" title="Optimization is applied automatically when a ZIP is uploaded or replaced.">◇ Optimize 360° Files</a>
    <a href="{{ route('spins.sitemap') }}" target="_blank" rel="noopener">↗ Generate / View 360° Sitemap</a>
    <a href="#editor" data-focus-settings>▣ 360° Settings</a>
</section>
<section class="sd-card"><h2>UUID Traceability</h2><p>Every 360° view is assigned a unique UUID for full traceability.</p>@if($selected)<small>Selected 360° UUID</small><div class="sd-uuid"><code>{{ $selected->uuid }}</code><button type="button" data-copy-uuid="{{ $selected->uuid }}" aria-label="Copy UUID">⧉</button></div><button class="sd-button sd-outline sd-wide" type="button" data-audit="{{ route('admin.spins.audit',$selected->id) }}">View 360° Audit Log →</button>@endif</section>
</aside>
</div>

<section class="sd-benefits" aria-label="360° product view benefits">
    <article><span>◎</span><div><strong>Immersive Experience</strong><p>Give customers a complete view from every angle.</p></div></article>
    <article><span>⌘</span><div><strong>High Engagement</strong><p>Interactive 360° views increase confidence and reduce returns.</p></div></article>
    <article><span>▣</span><div><strong>Better Conversions</strong><p>Interactive 360° content helps customers buy with confidence.</p></div></article>
    <article><span>◫</span><div><strong>Optimized Performance</strong><p>Fast loading, mobile optimized and SEO friendly.</p></div></article>
    <article><span>⌁</span><div><strong>Secure &amp; Traceable</strong><p>UUID tracking and audit logs for every 360° view.</p></div></article>
</section>

<dialog class="sd-dialog" id="sd-create"><div class="sd-dialog-heading"><h2>Create 360° View</h2><button type="button" data-close aria-label="Close">×</button></div>@include('admin.spins.form',['record'=>null,'embedded'=>false])</dialog>
<dialog class="sd-dialog sd-audit" id="sd-audit"><div class="sd-dialog-heading"><h2>360° Audit History</h2><button type="button" data-close aria-label="Close">×</button></div><pre data-audit-content role="status"></pre></dialog>
</div>
@endsection
