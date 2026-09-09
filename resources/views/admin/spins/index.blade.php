@extends('layouts.admin')
@section('title','360° Product View')
@push('styles')<link rel="stylesheet" href="/css/spins.css">@endpush
@push('scripts')<script src="/js/spin-viewer.js" defer></script><script src="/js/spins-admin.js" defer></script>@endpush
@section('content')
@php($statuses=\App\Models\ProductSpin::STATUSES)
@php($categories=\App\Models\ProductSpin::CATEGORIES)
<div class="sd">
<header class="sd-heading"><div><p>Website &amp; Products › Product Media Manager › 360° Product View</p><h1>360° Product View</h1><p>Create, manage and publish interactive product views from every angle.</p></div><a href="{{ route('admin.media.index') }}" class="sd-button sd-outline">Media overview</a></header>
@if(session('success'))<p class="sd-notice" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="sd-errors" role="alert"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="sd-layout"><main>
<section class="sd-kpis" aria-label="360° statistics">
@foreach([['refresh','Total 360° Views',$stats['total'],'green'],['package','Published',$stats['published'],'purple'],['clock','In Progress',$stats['in_progress'],'orange'],['eye','Views (30 Days)',$stats['views'],'blue'],['database','Storage Used',number_format($stats['bytes']/1048576,1).' MB','teal']] as [$icon,$label,$value,$color])
<article><span class="sd-kpi-icon {{ $color }}"><x-icon :name="$icon" size="23" /></span><div><h2>{{ $label }}</h2><strong>{{ $value }}</strong><small>{{ $label==='Views (30 Days)'?'Recorded viewer visits':'Current library' }}</small></div></article>
@endforeach
</section>
<section class="sd-card sd-library">
<div class="sd-tabs"><a href="{{ route('admin.spins.index') }}" @class(['active'=>!request('status')])>All 360° Views</a>@foreach($statuses as $key=>$label)<a href="{{ route('admin.spins.index',array_merge(request()->except(['page','edit']),['status'=>$key])) }}" @class(['active'=>request('status')===$key])>{{ $label }}</a>@endforeach<button type="button" class="sd-button" data-create>＋ Create 360° View</button></div>
<form class="sd-filters" method="get" action="{{ route('admin.spins.index') }}">
<label class="sd-search"><span class="sd-sr">Search</span><input name="q" placeholder="Search 360° views, product, SKU…" value="{{ request('q') }}" maxlength="150"></label>
<label><span class="sd-sr">Product</span><select name="product_id"><option value="">All Products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id')==$product->id)>{{ $product->name }}</option>@endforeach</select></label>
<label><span class="sd-sr">Status</span><select name="status"><option value="">All Statuses</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></label>
<label><span class="sd-sr">Type</span><select name="category"><option value="">All Types</option>@foreach($categories as $key=>$label)<option value="{{ $key }}" @selected(request('category')===$key)>{{ $label }}</option>@endforeach</select></label>
<label><span class="sd-sr">Device</span><select name="device"><option value="">All Devices</option><option value="mobile" @selected(request('device')==='mobile')>Mobile enabled</option><option value="desktop" @selected(request('device')==='desktop')>Desktop only</option></select></label>
<button class="sd-button sd-outline">Filters</button><a href="{{ route('admin.spins.index') }}">Reset</a>
</form>
<form method="post" action="{{ route('admin.spins.bulk') }}" data-bulk>@csrf
<div class="sd-table"><table><thead><tr><th><input type="checkbox" data-select-all aria-label="Select all visible views"></th><th>Preview</th><th>Product / SKU</th><th>Type</th><th>Frames</th><th>Resolution</th><th>Status</th><th>Platform</th><th>Views (30D)</th><th>Last Updated</th><th>Actions</th></tr></thead><tbody>
@forelse($spins as $spin)
<tr @class(['sd-selected'=>$selected?->id===$spin->id])><td><input type="checkbox" name="ids[]" value="{{ $spin->id }}" aria-label="Select {{ $spin->title }}"></td>
<td><a href="{{ route('admin.spins.index',array_merge(request()->query(),['edit'=>$spin->id])) }}#editor" class="sd-thumb"><img src="{{ route('spins.frame',[$spin->uuid,0]) }}" alt="{{ $spin->title }}" loading="lazy"><span>360°</span></a></td>
<td><strong>{{ $spin->product->name }}</strong><small>SKU: {{ $spin->product->sku }}</small><small>{{ $spin->title }}</small></td><td><span class="sd-tag {{ $spin->category }}">{{ $categories[$spin->category] }}</span></td><td>{{ count($spin->frames) }}</td><td>{{ $spin->resolution }}</td><td><span class="sd-tag {{ $spin->status }}">{{ $statuses[$spin->status] }}</span><small>{{ ucfirst($spin->visibility) }}</small></td><td>◎ Web</td><td>{{ number_format($spin->visits_count) }}</td><td>{{ $spin->updated_at->format('d M Y') }}<small>by {{ $spin->updated_by }}</small></td>
<td><div class="sd-actions"><a href="{{ route('spins.show',$spin->uuid) }}" target="_blank" rel="noopener" aria-label="Preview {{ $spin->title }}"><x-icon name="eye" size="16" /></a><a href="{{ route('admin.spins.index',array_merge(request()->query(),['edit'=>$spin->id])) }}#editor" aria-label="Edit {{ $spin->title }}"><x-icon name="edit" size="16" /></a><button type="button" data-audit="{{ route('admin.spins.audit',$spin->id) }}" aria-label="Audit history for {{ $spin->title }}">⋮</button></div></td></tr>
@empty<tr><td colspan="11"><div class="sd-empty"><x-icon name="refresh" size="36" /><h2>{{ request()->hasAny(['q','status','category','product_id'])?'No matching views':'Your 360° library starts here' }}</h2><p>Upload a frame ZIP and link it to a product to create your first interactive view.</p><button class="sd-button" type="button" data-create>Create 360° View</button></div></td></tr>@endforelse
</tbody></table></div>
<div class="sd-table-footer"><span>Showing {{ $spins->firstItem()??0 }} to {{ $spins->lastItem()??0 }} of {{ $spins->total() }} 360° views</span><div class="sd-pagination">@if($spins->previousPageUrl())<a href="{{ $spins->previousPageUrl() }}">← Previous</a>@endif<span>{{ $spins->currentPage() }} / {{ $spins->lastPage() }}</span>@if($spins->nextPageUrl())<a href="{{ $spins->nextPageUrl() }}">Next →</a>@endif</div><label>Per page<select data-per-page>@foreach([8,20,40] as $n)<option value="{{ $n }}" @selected($spins->perPage()===$n)>{{ $n }}</option>@endforeach</select></label></div>
<div class="sd-bulk"><label>Selected views<select name="action"><option value="draft">Move to Draft</option><option value="published">Publish</option><option value="archived">Archive</option><option value="delete">Delete permanently</option></select></label><button class="sd-button sd-outline" type="submit">Apply</button><a href="{{ route('admin.spins.export',request()->except(['page','edit'])) }}">Export CSV</a><small data-selection>0 selected</small></div>
</form></section>
<section id="editor">@if($selected)@include('admin.spins.form',['record'=>$selected])@else<div class="sd-card"><h2>Ready for your first frame set</h2><p>Create a view to configure rotation, accessibility and product publishing.</p></div>@endif</section>
</main>
<aside class="sd-sidebar"><section class="sd-card sd-date"><x-icon name="calendar" size="27" /><div><strong>Today</strong><small>{{ now()->format('l, j F Y') }}</small><b>{{ now()->format('H:i') }}</b></div></section>
<section class="sd-card"><h2>360° Summary</h2><dl><dt>Total 360° Views</dt><dd>{{ $stats['total'] }}</dd>@foreach($statuses as $key=>$label)<dt>{{ $label }}</dt><dd>{{ $stats[$key] }}</dd>@endforeach<dt>Views (30 Days)</dt><dd>{{ $stats['views'] }}</dd><dt>Storage Used</dt><dd>{{ number_format($stats['bytes']/1048576,1) }} MB</dd><dt>Avg. Load Time</dt><dd>{{ $stats['load_ms']?number_format($stats['load_ms']/1000,2).' s':'—' }}</dd></dl></section>
<section class="sd-card"><h2>360° by Type</h2><div class="sd-type-total"><strong>{{ $stats['total'] }}</strong><span>Total views</span></div>@foreach($categories as $key=>$label)<div class="sd-type"><span>{{ $label }}</span><strong>{{ $types[$key]??0 }}</strong><progress max="{{ max(1,$stats['total']) }}" value="{{ $types[$key]??0 }}" aria-label="{{ $label }} views"></progress></div>@endforeach</section>
<section class="sd-card sd-quick"><h2>Quick Actions</h2><button type="button" data-create>＋ Create / Upload 360° ZIP</button><a href="#editor">⚙ Settings, categories &amp; hotspots</a><a href="{{ route('spins.sitemap') }}" target="_blank" rel="noopener">↗ Open live 360° sitemap</a><a href="{{ route('admin.spins.export') }}">↓ Export library CSV</a><details><summary>Frame creation guide</summary><p>Photograph your product on a turntable, with fixed lighting and camera position. Name equal-sized frames 001.jpg, 002.jpg, etc. ZIP only the images. Every import automatically resizes and re-encodes the frames for web delivery.</p></details></section>
<section class="sd-card"><h2>Performance (30 Days)</h2><dl><dt>Total Views</dt><dd>{{ $stats['views'] }}</dd><dt>Unique Browsers</dt><dd>{{ $stats['unique'] }}</dd><dt>Engagement Rate</dt><dd>{{ $stats['views']?number_format(100*$stats['engaged']/$stats['views'],1).' %':'—' }}</dd></dl><small>One visit per session, per view, per day. Engagement records viewer interaction. Admin previews are excluded.</small></section>
<section class="sd-card"><h2>Preview</h2>@if($selected)<x-spin-viewer :spin="$selected" :preview="true" /><a class="sd-button sd-outline sd-wide" href="{{ route('spins.show',$selected->uuid) }}" target="_blank" rel="noopener">Open 360° Viewer</a>@else<p>Select a view to preview.</p>@endif</section>
<section class="sd-card"><h2>UUID Traceability</h2><p>Every view has a stable identifier and logged publishing history.</p>@if($selected)<small>Selected 360° UUID</small><code>{{ $selected->uuid }}</code><button class="sd-button sd-outline sd-wide" type="button" data-audit="{{ route('admin.spins.audit',$selected->id) }}">View 360° Audit Log →</button>@endif</section>
</aside></div>
<dialog class="sd-dialog" id="sd-create"><div class="sd-dialog-heading"><h2>Create 360° View</h2><button type="button" data-close aria-label="Close">×</button></div>@include('admin.spins.form',['record'=>null])</dialog>
<dialog class="sd-dialog sd-audit" id="sd-audit"><div class="sd-dialog-heading"><h2>360° Audit History</h2><button type="button" data-close aria-label="Close">×</button></div><pre data-audit-content role="status"></pre></dialog>
</div>
@endsection
