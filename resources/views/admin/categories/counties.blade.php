@extends('layouts.admin')

@section('title', 'County / Region Master')

@push('styles')
<style>
.master-page{padding:22px;display:grid;gap:18px;color:#17231b}.master-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.master-head h1{margin:4px 0;font-size:26px}.master-head p{margin:0;color:#66756c}.master-kicker{font-size:12px;font-weight:800;color:#087c3f;text-transform:uppercase}.master-nav,.master-filter{display:flex;gap:8px;flex-wrap:wrap}.master-btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 13px;border:1px solid #d4ded7;border-radius:8px;background:#fff;color:#122219;font-weight:700;text-decoration:none;cursor:pointer}.master-btn.primary{background:#087c3f;border-color:#087c3f;color:#fff}.master-btn.danger{color:#a12620;border-color:#efc9c7}.master-card{background:#fff;border:1px solid #dce5df;border-radius:12px;padding:16px}.master-filter input,.master-filter select,.master-add input,.master-add select,.master-table input,.master-table select{height:38px;border:1px solid #ccd8d0;border-radius:7px;padding:0 9px;background:#fff}.master-filter input{min-width:220px}.master-add{display:grid;grid-template-columns:minmax(190px,1.5fr) 130px minmax(190px,2fr) 120px 100px auto;gap:8px;align-items:end}.master-add label{display:grid;gap:5px;font-size:11px;font-weight:700}.master-table-wrap{overflow:auto}.master-table{width:100%;border-collapse:collapse;font-size:13px;min-width:980px}.master-table th,.master-table td{padding:9px 8px;border-bottom:1px solid #edf1ee;text-align:left}.master-table th{background:#f8faf8;font-size:11px;text-transform:uppercase;color:#67756c}.master-badge{display:inline-flex;padding:3px 7px;border-radius:999px;background:#edf7f0;color:#087c3f;font-size:11px;font-weight:800}.master-alert{padding:11px 13px;border-radius:8px;background:#eaf7ef;color:#056c35;font-weight:700}.master-alert.error{background:#fff0ef;color:#a12620}.master-actions{display:flex;gap:6px}.master-pagination{padding-top:12px}@media(max-width:950px){.master-add{grid-template-columns:repeat(2,minmax(0,1fr))}.master-head{display:grid}}@media(max-width:560px){.master-page{padding:12px}.master-add{grid-template-columns:1fr}.master-filter>*{width:100%;min-width:0!important}}
</style>
@endpush

@section('content')
<div class="master-page">
    <header class="master-head">
        <div><span class="master-kicker">Website &amp; Products · Categories</span><h1>County / Region Master</h1><p>Manage the state, province, county or region between Country and Club. Product and club dropdowns use this database master directly.</p></div>
        <nav class="master-nav"><a class="master-btn" href="{{ route('admin.categories.index') }}">Categories</a><a class="master-btn" href="{{ route('admin.categories.taxonomy') }}">Taxonomy Builder</a><a class="master-btn" href="{{ route('admin.categories.countries') }}">Country Master</a><a class="master-btn" href="{{ route('admin.categories.clubs') }}">Club Master</a></nav>
    </header>

    @if(session('success'))<div class="master-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="master-alert error">{{ $errors->first() }}</div>@endif

    <section class="master-card">
        <form class="master-filter" method="get" action="{{ route('admin.categories.counties') }}">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search county / region or code...">
            <select name="catalog_country_id"><option value="">All Countries</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($countryId===$country->id)>{{ $country->name }} ({{ $country->code }})</option>@endforeach</select>
            <select name="status"><option value="active" @selected($status==='active')>Active</option><option value="inactive" @selected($status==='inactive')>Inactive</option><option value="all" @selected($status==='all')>All Statuses</option></select>
            <button class="master-btn" type="submit">Filter</button><a class="master-btn" href="{{ route('admin.categories.counties') }}">Reset</a>
        </form>
    </section>

    <section class="master-card">
        <h2 style="margin-top:0">Add county / region</h2>
        <form class="master-add" method="post" action="{{ route('admin.categories.counties.store') }}">@csrf
            <label>Country<select name="catalog_country_id" required><option value="">Select country</option>@foreach($countries as $country)<option value="{{ $country->id }}">{{ $country->name }} ({{ $country->code }})</option>@endforeach</select></label>
            <label>Code<input name="code" required maxlength="16" placeholder="IE-LK"></label>
            <label>Name<input name="name" required maxlength="180" placeholder="Limerick"></label>
            <label>Status<select name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></label>
            <label>Sort<input type="number" name="sort_order" min="0" max="100000" value="0"></label>
            <button class="master-btn primary" type="submit">Add County / Region</button>
        </form>
    </section>

    <section class="master-card">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center"><h2 style="margin:0">Counties / Regions</h2><span class="master-badge">{{ number_format($counties->total()) }} shown</span></div>
        <div class="master-table-wrap" style="margin-top:12px"><table class="master-table"><thead><tr><th>Country</th><th>Code</th><th>Name</th><th>Status</th><th>Sort</th><th>Actions</th></tr></thead><tbody>
        @forelse($counties as $county)
            @php($formId='county-master-'.$county->id)
            <tr>
                <td><form id="{{ $formId }}" method="post" action="{{ route('admin.categories.counties.update',$county) }}">@csrf @method('PATCH')</form><select form="{{ $formId }}" name="catalog_country_id" required>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($county->catalog_country_id===$country->id)>{{ $country->name }} ({{ $country->code }})</option>@endforeach</select></td>
                <td><input form="{{ $formId }}" name="code" value="{{ $county->code }}" maxlength="16" required style="width:130px"></td>
                <td><input form="{{ $formId }}" name="name" value="{{ $county->name }}" maxlength="180" required style="min-width:220px;width:100%"></td>
                <td><select form="{{ $formId }}" name="is_active"><option value="1" @selected($county->is_active)>Active</option><option value="0" @selected(!$county->is_active)>Inactive</option></select></td>
                <td><input form="{{ $formId }}" type="number" name="sort_order" value="{{ $county->sort_order }}" min="0" max="100000" style="width:80px"></td>
                <td><div class="master-actions"><button form="{{ $formId }}" class="master-btn" type="submit">Save</button><form method="post" action="{{ route('admin.categories.counties.destroy',$county) }}" onsubmit="return confirm('Delete this county / region?')">@csrf @method('DELETE')<button class="master-btn danger" type="submit">Delete</button></form></div></td>
            </tr>
        @empty
            <tr><td colspan="6" style="padding:28px;text-align:center;color:#6d7b72">No county / region records match these filters.</td></tr>
        @endforelse
        </tbody></table></div>
        <div class="master-pagination">{{ $counties->links() }}</div>
    </section>
</div>
@endsection
