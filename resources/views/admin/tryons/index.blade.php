@extends('layouts.admin')
@section('title','Virtual Try-On')
@push('styles')<link rel="stylesheet" href="/css/spins.css"><link rel="stylesheet" href="/css/tryons.css">@endpush
@push('scripts')<script src="/js/tryons-admin.js" defer></script>@endpush
@section('content')
<div class="sd to" data-tryon-dashboard>
<header class="sd-heading">
    <div>
        <p class="sd-breadcrumb">Website &amp; Products › Product Media Manager › Virtual Try-On</p>
        <h1>Virtual Try-On</h1>
        <p>Manage AR/AI virtual try-on assets for hats and caps. Allow customers to see how products look on them in real time.</p>
    </div>
</header>
@if(session('success'))<p class="sd-notice" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="sd-errors" role="alert"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="sd-layout"><main>
<section class="sd-kpis to-kpis" aria-label="Virtual try-on statistics">
@foreach([
    ['package','Total Try-On Assets',$stats['total'],'green','Managed asset library'],
    ['image','Published',$stats['published'],'purple','Available to customers'],
    ['clock','In Review',$stats['in_review'],'orange','Workflow queue'],
    ['eye','Total Try-Ons (30 Days)',$stats['tryons'],'blue','Recorded try-on sessions'],
    ['users','Unique Users (30 Days)',$stats['unique'],'teal','Session-deduplicated visitors'],
    ['trending-up','Assisted Conversion',number_format($stats['conversion_rate'],2).' %','purple','Measured converted sessions']
] as [$icon,$label,$value,$color,$note])
<article><span class="sd-kpi-icon {{ $color }}"><x-icon :name="$icon" size="23" /></span><div><h2>{{ $label }}</h2><strong>{{ $value }}</strong><small>{{ $note }}</small></div></article>
@endforeach
</section>

<section class="sd-card sd-library">
<div class="sd-tabs">
    <a href="{{ route('admin.tryons.index') }}" @class(['active'=>!request('status')])>All Try-On Assets</a>
    @foreach($statuses as $key=>$label)<a href="{{ route('admin.tryons.index',array_merge(request()->except(['page','edit']),['status'=>$key])) }}" @class(['active'=>request('status')===$key])>{{ $label }}</a>@endforeach
    <button type="button" class="sd-button" data-create>＋ Create Try-On</button>
</div>
<form class="sd-filters" method="get" action="{{ route('admin.tryons.index') }}">
    <label class="sd-search"><span class="sd-sr">Search</span><input name="q" placeholder="Search try-on assets, product, SKU…" value="{{ request('q') }}" maxlength="150"></label>
    <button class="sd-button sd-outline"><x-icon name="filter" size="15" /> Filters</button>
    <label><span class="sd-sr">Product</span><select name="product_id"><option value="">All Products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id')==$product->id)>{{ $product->name }}</option>@endforeach</select></label>
    <label><span class="sd-sr">Type</span><select name="type"><option value="">All Types</option>@foreach($types as $key=>$label)<option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label><span class="sd-sr">Status</span><select name="status"><option value="">All Statuses</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label><span class="sd-sr">Device</span><select name="device"><option value="">All Devices</option><option value="mobile" @selected(request('device')==='mobile')>Mobile enabled</option><option value="desktop" @selected(request('device')==='desktop')>Desktop only</option></select></label>
    <a class="sd-reset" href="{{ route('admin.tryons.index') }}">↻ Reset</a>
</form>

<form method="post" action="{{ route('admin.tryons.bulk') }}" data-bulk>@csrf
<div class="sd-table"><table><thead><tr><th><input type="checkbox" data-select-all aria-label="Select all visible assets"></th><th>Preview</th><th>Product / SKU</th><th>Type</th><th>Model / Target</th><th>Status</th><th>Platform</th><th>Try-Ons (30D)</th><th>Last Updated</th><th>Actions</th></tr></thead><tbody>
@forelse($assets as $asset)
<tr @class(['sd-selected'=>$selected?->id===$asset->id])>
<td><input type="checkbox" name="ids[]" value="{{ $asset->id }}" aria-label="Select {{ $asset->title }}"></td>
<td><a href="{{ route('admin.tryons.index',array_merge(request()->query(),['edit'=>$asset->id])) }}#editor" class="sd-thumb to-thumb">@if($asset->previewPath())<img src="{{ route('tryons.asset',[$asset->uuid,'preview']) }}" alt="{{ $asset->seo['alt']??$asset->title }}" loading="lazy">@else<span>3D</span>@endif</a></td>
<td><strong>{{ $asset->product->name }}</strong><small>SKU: {{ $asset->product->sku }}</small></td>
<td><span class="sd-tag {{ $asset->type }}">{{ $types[$asset->type] }}</span></td>
<td><strong>{{ $targets[$asset->target] }}</strong><small>{{ $asset->age_range ?: 'All Ages' }}</small></td>
<td><span class="sd-tag {{ $asset->status }}">{{ $statuses[$asset->status] }}</span></td>
<td><span class="to-platform" title="Web">◎</span>@if($asset->settings['mobile']??true)<span title="iOS">●</span><span title="Android">◆</span>@endif</td>
<td>{{ number_format($asset->visits_count) }}</td>
<td>{{ $asset->updated_at->format('d M Y') }}<small>by {{ $asset->updated_by ?: 'Admin User' }}</small></td>
<td><div class="sd-actions"><a href="{{ route('virtual-tryon',['product_id'=>$asset->product_id]) }}" target="_blank" rel="noopener" aria-label="Launch {{ $asset->title }}"><x-icon name="eye" size="16" /></a><a href="{{ route('admin.tryons.index',array_merge(request()->query(),['edit'=>$asset->id])) }}#editor" aria-label="Edit {{ $asset->title }}"><x-icon name="edit" size="16" /></a><button type="button" data-audit="{{ route('admin.tryons.audit',$asset->id) }}" aria-label="Audit history for {{ $asset->title }}">⋮</button></div></td>
</tr>
@empty
<tr><td colspan="10"><div class="sd-empty"><x-icon name="camera" size="36" /><h2>{{ request()->hasAny(['q','status','type','product_id'])?'No matching try-on assets':'Your Virtual Try-On library starts here' }}</h2><p>Upload a browser overlay or AR package and link it to a product to create your first customer try-on experience.</p><button class="sd-button" type="button" data-create>Create Try-On Asset</button></div></td></tr>
@endforelse
</tbody></table></div>
<div class="sd-table-footer">
    <span>Showing {{ $assets->firstItem()??0 }} to {{ $assets->lastItem()??0 }} of {{ $assets->total() }} try-on assets</span>
    <nav class="sd-pagination" aria-label="Try-on asset pages">
        @if($assets->previousPageUrl())<a href="{{ $assets->previousPageUrl() }}" aria-label="Previous page">‹</a>@endif
        @if($pageStart>1)<a href="{{ $assets->url(1) }}">1</a>@if($pageStart>2)<span>…</span>@endif @endif
        @for($page=$pageStart;$page<=$pageEnd;$page++)<a href="{{ $assets->url($page) }}" @class(['active'=>$page===$assets->currentPage()]) aria-current="{{ $page===$assets->currentPage()?'page':'false' }}">{{ $page }}</a>@endfor
        @if($pageEnd<$assets->lastPage())@if($pageEnd<$assets->lastPage()-1)<span>…</span>@endif<a href="{{ $assets->url($assets->lastPage()) }}">{{ $assets->lastPage() }}</a>@endif
        @if($assets->nextPageUrl())<a href="{{ $assets->nextPageUrl() }}" aria-label="Next page">›</a>@endif
    </nav>
    <label><span class="sd-sr">Rows per page</span><select data-per-page>@foreach([8,20,40] as $n)<option value="{{ $n }}" @selected($assets->perPage()===$n)>{{ $n }} / page</option>@endforeach</select></label>
</div>
<div class="sd-bulk"><label>Selected assets<select name="action"><option value="draft">Move to Draft</option><option value="in_review">Send to Review</option><option value="published">Publish</option><option value="needs_attention">Needs Attention</option><option value="archived">Archive</option><option value="delete">Delete permanently</option></select></label><button class="sd-button sd-outline" type="submit">Apply</button><a href="{{ route('admin.tryons.export',request()->except(['page','edit'])) }}">Export CSV</a><small data-selection>0 selected</small></div>
</form>
</section>

<section id="editor" class="to-workbench" aria-label="Virtual try-on workbench">
    @include('admin.tryons.form',['record'=>$selected,'embedded'=>true])
    <section class="sd-card to-performance-card">
        <h2>Performance <small>(Last 30 Days)</small></h2>
        <div class="sd-performance-grid"><div><span>Total Try-Ons</span><strong>{{ number_format($stats['tryons']) }}</strong></div><div><span>Unique Users</span><strong>{{ number_format($stats['unique']) }}</strong></div><div><span>Avg. Session</span><strong>{{ $stats['session_seconds']?gmdate('i:s',(int)$stats['session_seconds']):'—' }}</strong></div><div><span>Assisted Conversion</span><strong>{{ number_format($stats['conversion_rate'],2) }}%</strong></div></div>
        <small>Real session-based measurements. Admin previews are excluded.</small>
    </section>
    <section class="sd-card to-preview-card">
        <h2>Preview</h2>
        @if($selected && $selected->previewPath())<div class="to-preview-stage"><img src="{{ route('tryons.asset',[$selected->uuid,'preview']) }}" alt="{{ $selected->seo['alt']??$selected->title }}"></div><a class="sd-button sd-outline sd-wide" href="{{ route('virtual-tryon',['product_id'=>$selected->product_id]) }}" target="_blank" rel="noopener">Launch Try-On</a>@elseif($selected)<p>This asset has a 3D package but no browser preview overlay yet.</p><a class="sd-button sd-outline sd-wide" href="#editor">Add Preview Overlay</a>@else<p>Select an asset to preview it here.</p><button class="sd-button sd-outline sd-wide" type="button" data-create>Create Try-On Asset</button>@endif
    </section>
</section>
</main>

<aside class="sd-sidebar">
<section class="sd-card sd-date"><x-icon name="calendar" size="27" /><div><strong>Today</strong><small>{{ now()->format('l, j F Y') }}</small><b>{{ now()->format('g:i A') }}</b></div></section>
<section class="sd-card"><h2>Try-On Summary</h2><dl><dt>Total Try-On Assets</dt><dd>{{ $stats['total'] }}</dd>@foreach($statuses as $key=>$label)<dt>{{ $label }}</dt><dd>{{ $stats[$key] }}</dd>@endforeach<dt>Total Try-Ons (30 Days)</dt><dd>{{ number_format($stats['tryons']) }}</dd><dt>Unique Users (30 Days)</dt><dd>{{ number_format($stats['unique']) }}</dd><dt>Avg. Session Time</dt><dd>{{ $stats['session_seconds']?gmdate('i:s',(int)$stats['session_seconds']):'—' }}</dd><dt>Assisted Conversion</dt><dd>{{ number_format($stats['conversion_rate'],2) }}%</dd></dl></section>
<section class="sd-card sd-by-type"><h2>Try-Ons by Device <small>(30 Days)</small></h2><div class="sd-type-layout"><div class="sd-type-legend">@foreach(['mobile_ar'=>'Mobile (AR)','desktop_web'=>'Desktop (Web)','ios_app'=>'iOS App','android_app'=>'Android App'] as $key=>$label)<div><i class="{{ $key }}"></i><span>{{ $label }}</span><strong>{{ number_format($devices[$key]??0) }} ({{ $stats['tryons']?number_format(100*($devices[$key]??0)/$stats['tryons'],1):'0.0' }}%)</strong></div>@endforeach</div><div class="to-donut" style="--a1:{{ $angleMobile }}deg;--a2:{{ $angleDesktop }}deg;--a3:{{ $angleIos }}deg"><span><strong>{{ number_format($stats['tryons']) }}</strong><small>Total</small></span></div></div></section>
<section class="sd-card sd-quick"><h2>Quick Actions</h2><button type="button" data-create>↥ Create Try-On Asset</button><button type="button" data-create>◎ Bulk Upload Try-On (ZIP)</button><a href="#to-target-field" data-focus-target>◉ Manage Target Models</a><a href="#editor" title="Browser overlays are decoded and re-encoded safely during upload.">◇ Optimize Try-On Assets</a><a href="#editor" data-focus-settings>▣ Try-On Settings</a></section>
<section class="sd-card"><h2>UUID Traceability</h2><p>Every try-on asset is assigned a unique UUID for full traceability.</p>@if($selected)<small>Selected Asset UUID</small><div class="sd-uuid"><code>{{ $selected->uuid }}</code><button type="button" data-copy-uuid="{{ $selected->uuid }}" aria-label="Copy UUID">⧉</button></div><button class="sd-button sd-outline sd-wide" type="button" data-audit="{{ route('admin.tryons.audit',$selected->id) }}">View Try-On Audit Log →</button>@endif</section>
</aside>
</div>

<section class="sd-benefits" aria-label="Virtual try-on benefits"><article><span>◎</span><div><strong>Realistic Experience</strong><p>Private in-browser fitting gives customers a realistic preview.</p></div></article><article><span>⌘</span><div><strong>Increase Conversion</strong><p>Customers can compare styles before purchasing.</p></div></article><article><span>▣</span><div><strong>Cross-Device Support</strong><p>Published assets work across responsive web experiences.</p></div></article><article><span>◫</span><div><strong>Performance Optimized</strong><p>Images are validated, decoded and re-encoded on upload.</p></div></article><article><span>⌁</span><div><strong>Secure &amp; Traceable</strong><p>UUID tracking and audit logs cover every asset.</p></div></article></section>

<dialog class="sd-dialog" id="to-create"><div class="sd-dialog-heading"><h2>Create Try-On Asset</h2><button type="button" data-close aria-label="Close">×</button></div>@include('admin.tryons.form',['record'=>null,'embedded'=>false])</dialog>
<dialog class="sd-dialog sd-audit" id="to-audit"><div class="sd-dialog-heading"><h2>Try-On Audit History</h2><button type="button" data-close aria-label="Close">×</button></div><pre data-audit-content role="status"></pre></dialog>
</div>
@endsection
