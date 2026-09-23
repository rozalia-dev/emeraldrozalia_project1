@extends('layouts.admin')

@section('title', 'Club Master')

@push('styles')
<style>
.club-page{padding:22px;display:grid;gap:18px;color:#17231b}.club-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.club-head h1{margin:4px 0;font-size:26px}.club-head p{margin:0;color:#66756c}.club-kicker{font-size:12px;font-weight:800;color:#087c3f;text-transform:uppercase}.club-nav,.club-filter{display:flex;gap:8px;flex-wrap:wrap}.club-btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 13px;border:1px solid #d4ded7;border-radius:8px;background:#fff;color:#122219;font-weight:700;text-decoration:none;cursor:pointer}.club-btn.primary{background:#087c3f;border-color:#087c3f;color:#fff}.club-btn.danger{color:#a12620;border-color:#efc9c7}.club-card{background:#fff;border:1px solid #dce5df;border-radius:12px;padding:16px}.club-filter input,.club-filter select,.club-add input,.club-add select,.club-table input,.club-table select{height:38px;border:1px solid #ccd8d0;border-radius:7px;padding:0 9px;background:#fff}.club-filter input{min-width:220px}.club-add{display:grid;grid-template-columns:130px minmax(160px,1fr) minmax(170px,1fr) minmax(170px,1.2fr) minmax(150px,1fr) 100px 80px auto;gap:8px;align-items:end}.club-add label{display:grid;gap:5px;font-size:11px;font-weight:700}.club-table-wrap{overflow:auto}.club-table{width:100%;border-collapse:collapse;font-size:13px;min-width:1080px}.club-table th,.club-table td{padding:9px 8px;border-bottom:1px solid #edf1ee;text-align:left;vertical-align:middle}.club-table th{background:#f8faf8;font-size:11px;text-transform:uppercase;color:#67756c}.club-table .name{min-width:180px;width:100%}.club-table .slug{min-width:180px;width:100%}.club-actions{display:flex;gap:6px;align-items:center}.club-orgs{display:flex;gap:6px;flex-wrap:wrap;align-items:center}.club-orgs label{display:inline-flex;gap:4px;align-items:center;font-size:11px;font-weight:700;white-space:nowrap}.club-orgs input{width:auto;height:auto;margin:0}.club-badge{display:inline-flex;padding:3px 7px;border-radius:999px;background:#edf7f0;color:#087c3f;font-size:11px;font-weight:800;text-transform:uppercase}.club-alert{padding:11px 13px;border-radius:8px;background:#eaf7ef;color:#056c35;font-weight:700}.club-alert.error{background:#fff0ef;color:#a12620}.club-pagination{padding-top:12px}@media(max-width:1050px){.club-add{grid-template-columns:repeat(2,minmax(0,1fr))}.club-head{display:grid}}@media(max-width:560px){.club-page{padding:12px}.club-add{grid-template-columns:1fr}.club-filter>*{width:100%;min-width:0!important}}
</style>
@endpush

@section('content')
<div class="club-page">
    <header class="club-head"><div><span class="club-kicker">Website &amp; Products · Categories</span><h1>Club Master</h1><p>Add each club once, then assign it to one or more organizations. Example: Manchester United can belong to both English and UEFA while keeping the same country, county and club record.</p></div><nav class="club-nav"><a class="club-btn" href="{{ route('admin.categories.index') }}">Categories</a><a class="club-btn" href="{{ route('admin.categories.taxonomy') }}">Taxonomy Builder</a><a class="club-btn" href="{{ route('admin.categories.countries') }}">Country Master</a></nav></header>
    @if(session('success'))<div class="club-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="club-alert error">{{ $errors->first() }}</div>@endif

    @php($filterCountryCode = optional($countries->firstWhere('id', $countryId))->code)
    <section class="club-card"><form class="club-filter" method="get" action="{{ route('admin.categories.clubs') }}"><input type="search" name="q" value="{{ $search }}" placeholder="Search club..."><select name="governing_body"><option value="">All Organizations</option>@foreach($governingBodies as $value=>$label)<option value="{{ $value }}" @selected($governingBody===$value)>{{ $label }}</option>@endforeach</select><select name="catalog_country_id"><option value="">All Countries</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($countryId===$country->id)>{{ $country->name }}</option>@endforeach</select><select name="catalog_county_code"><option value="">{{ $filterCountryCode ? 'All Counties / Regions' : 'Select country first' }}</option>@foreach(($countyOptionsByCountry[$filterCountryCode] ?? []) as $row)<option value="{{ $row['code'] }}" @selected($countyCode===$row['code'])>{{ $row['name'] }}</option>@endforeach</select><select name="status"><option value="active" @selected($status==='active')>Active</option><option value="inactive" @selected($status==='inactive')>Inactive</option><option value="all" @selected($status==='all')>All Statuses</option></select><button class="club-btn" type="submit">Filter</button><a class="club-btn" href="{{ route('admin.categories.clubs') }}">Reset</a></form></section>

    <section class="club-card"><h2 style="margin-top:0">Add club</h2><form class="club-add" method="post" action="{{ route('admin.categories.clubs.store') }}" data-club-add-form>@csrf
        <label>Organizations<div class="club-orgs">@foreach($governingBodies as $value=>$label)<label><input type="checkbox" name="organizations[]" value="{{ $value }}"> {{ $label }}</label>@endforeach</div></label>
        <label>Country<select name="catalog_country_id" required data-club-country><option value="">Select country</option>@foreach($countries as $country)<option value="{{ $country->id }}" data-code="{{ $country->code }}">{{ $country->name }} ({{ $country->code }})</option>@endforeach</select></label>
        <label>County<select name="catalog_county_code" required data-club-county><option value="">Select country first</option></select></label>
        <label>Club Name<input name="name" maxlength="180" required></label>
        <label>Slug<input name="slug" maxlength="220" placeholder="auto-from-name"></label>
        <label>Status<select name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></label>
        <label>Sort<input type="number" name="sort_order" value="0" min="0" max="100000"></label>
        <button class="club-btn primary" type="submit">Add Club</button>
    </form></section>

    <section class="club-card"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center"><h2 style="margin:0">Clubs</h2><span class="club-badge">{{ number_format($clubs->total()) }} shown</span></div><div class="club-table-wrap" style="margin-top:12px"><table class="club-table"><thead><tr><th>Organization</th><th>Country</th><th>County</th><th>Club</th><th>Slug</th><th>Status</th><th>Sort</th><th>Menu Uses</th><th>Actions</th></tr></thead><tbody>
        @forelse($clubs as $club)
            @php($clubFormId='club-master-'.$club->id)
            @php($clubOrganizations = $club->organizations->pluck('taxonomy_type')->push($club->governing_body)->filter()->unique()->values()->all())
            <tr><td><form id="{{ $clubFormId }}" method="post" action="{{ route('admin.categories.clubs.update',$club) }}">@csrf @method('PATCH')</form><div class="club-orgs">@foreach($governingBodies as $value=>$label)<label><input form="{{ $clubFormId }}" type="checkbox" name="organizations[]" value="{{ $value }}" @checked(in_array($value,$clubOrganizations,true))> {{ $label }}</label>@endforeach</div></td><td><select form="{{ $clubFormId }}" name="catalog_country_id" data-edit-club-country>@foreach($countries as $country)<option value="{{ $country->id }}" data-code="{{ $country->code }}" @selected($club->catalog_country_id===$country->id)>{{ $country->name }}</option>@endforeach</select></td><td><select form="{{ $clubFormId }}" name="catalog_county_code" data-edit-club-county data-current-county="{{ strtoupper((string) $club->catalog_county_code) }}"><option value="">Select county / region</option>@foreach(($countyOptionsByCountry[$club->country?->code] ?? []) as $row)<option value="{{ $row['code'] }}" @selected(strtoupper((string)$club->catalog_county_code)===strtoupper((string)$row['code']))>{{ $row['name'] }}</option>@endforeach</select></td><td><input form="{{ $clubFormId }}" class="name" name="name" value="{{ $club->name }}" maxlength="180" required></td><td><input form="{{ $clubFormId }}" class="slug" name="slug" value="{{ $club->slug }}" maxlength="220"></td><td><select form="{{ $clubFormId }}" name="is_active"><option value="1" @selected($club->is_active)>Active</option><option value="0" @selected(!$club->is_active)>Inactive</option></select></td><td><input form="{{ $clubFormId }}" type="number" name="sort_order" value="{{ $club->sort_order }}" min="0" max="100000" style="width:75px"></td><td>{{ number_format($club->categories_count) }}</td><td><div class="club-actions"><button form="{{ $clubFormId }}" class="club-btn" type="submit">Save</button><form method="post" action="{{ route('admin.categories.clubs.destroy',$club) }}" onsubmit="return confirm('Remove this club from Club Master?')">@csrf @method('DELETE')<button class="club-btn danger" type="submit">Delete</button></form></div></td></tr>
        @empty<tr><td colspan="9" style="padding:28px;text-align:center;color:#6d7b72">No clubs match these filters. Add the first club above.</td></tr>@endforelse
    </tbody></table></div><div class="club-pagination">{{ $clubs->links() }}</div></section>
<script>
(() => {
    const counties = @json($countyOptionsByCountry);

    const fillCounty = (countrySelect, countySelect, selected = '') => {
        if (!countrySelect || !countySelect) return;
        const code = countrySelect.selectedOptions[0]?.dataset.code || '';
        countySelect.replaceChildren(new Option(code ? 'Select county' : 'Select country first', ''));
        for (const row of (counties[code] || [])) {
            countySelect.add(new Option(row.name, row.code, false, row.code === selected));
        }
        if (selected && [...countySelect.options].some(option => option.value === selected)) {
            countySelect.value = selected;
        }
    };

    const addForm = document.querySelector('[data-club-add-form]');
    const addCountry = addForm?.querySelector('[data-club-country]');
    const addCounty = addForm?.querySelector('[data-club-county]');
    addCountry?.addEventListener('change', () => fillCounty(addCountry, addCounty));
    fillCounty(addCountry, addCounty);

    document.querySelectorAll('[data-edit-club-country]').forEach(countrySelect => {
        const formId = countrySelect.getAttribute('form');
        const countySelect = document.querySelector(`[data-edit-club-county][form="${formId}"]`);
        if (!countySelect) return;

        countrySelect.addEventListener('change', () => {
            countySelect.dataset.currentCounty = '';
            fillCounty(countrySelect, countySelect);
        });

        fillCounty(countrySelect, countySelect, countySelect.dataset.currentCounty || countySelect.value);
    });
})();
</script>
</div>
@endsection
