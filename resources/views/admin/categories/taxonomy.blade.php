@extends('layouts.admin')

@section('title', 'Country Taxonomy Builder')

@push('styles')
<style>
.tax-page{padding:22px;display:grid;gap:18px;color:#17231b}.tax-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.tax-head h1{margin:4px 0 4px;font-size:26px}.tax-head p{margin:0;color:#627067}.tax-kicker{font-size:12px;font-weight:800;color:#087c3f;text-transform:uppercase}.tax-nav{display:flex;gap:8px;flex-wrap:wrap}.tax-btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 13px;border:1px solid #d4ded7;border-radius:8px;background:#fff;color:#122219;font-weight:700;text-decoration:none;cursor:pointer}.tax-btn.primary{background:#087c3f;border-color:#087c3f;color:#fff}.tax-card{background:#fff;border:1px solid #dce5df;border-radius:12px;padding:16px;box-shadow:0 4px 14px rgba(13,45,27,.04)}.tax-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.tax-grid label,.tax-field{display:grid;gap:6px;font-size:12px;font-weight:700}.tax-grid select,.tax-grid input,.tax-filter select,.tax-filter input{height:40px;border:1px solid #ccd8d0;border-radius:8px;padding:0 10px;background:#fff;color:#15251b}.tax-span-2{grid-column:span 2}.tax-checks{display:flex;gap:14px;align-items:center;min-height:40px;flex-wrap:wrap}.tax-checks label{display:flex;align-items:center;gap:6px;font-weight:600}.tax-help{font-size:12px;color:#66766c;margin:10px 0 0}.tax-alert{padding:11px 13px;border-radius:8px;background:#eaf7ef;color:#056c35;font-weight:700}.tax-alert.error{background:#fff0ef;color:#a12620}.tax-filter{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.tax-filter input{min-width:190px}.tax-filter select{min-width:150px}.tax-table-wrap{overflow:auto}.tax-table{width:100%;border-collapse:collapse;font-size:13px}.tax-table th,.tax-table td{padding:10px 9px;border-bottom:1px solid #edf1ee;text-align:left;vertical-align:top}.tax-table th{font-size:11px;text-transform:uppercase;color:#647268;background:#f8faf8}.tax-pill{display:inline-flex;padding:4px 8px;border-radius:999px;background:#edf7f0;color:#087c3f;font-size:11px;font-weight:800}.tax-path{display:grid;gap:2px}.tax-path small{color:#718078}.tax-empty{padding:28px;text-align:center;color:#6a786f}.tax-pagination{padding-top:12px}.tax-section-title{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px}.tax-section-title h2{margin:0;font-size:18px}@media(max-width:1200px){.tax-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:850px){.tax-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:650px){.tax-page{padding:12px}.tax-head{display:grid}.tax-grid{grid-template-columns:1fr}.tax-span-2{grid-column:auto}.tax-filter>*{width:100%;min-width:0!important}}
</style>
@endpush

@section('content')
<div class="tax-page" data-taxonomy-page>
    <header class="tax-head">
        <div><span class="tax-kicker">Website &amp; Products · Categories</span><h1>Country Taxonomy Builder</h1><p>Build synchronized SHOP ALL paths as Category → Country → County / Club → Beanies / Caps / Hats.</p></div>
        <nav class="tax-nav"><a class="tax-btn" href="{{ route('admin.categories.index') }}">Categories</a><a class="tax-btn" href="{{ route('admin.categories.countries') }}">Country Master</a><a class="tax-btn" href="{{ route('admin.categories.clubs') }}">Club Master</a></nav>
    </header>

    @if(session('success'))<div class="tax-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="tax-alert error"><strong>Please check the form:</strong> {{ $errors->first() }}</div>@endif

    <section class="tax-card">
        <div class="tax-section-title"><div><h2>Create / synchronize a menu path</h2><p class="tax-help">For GAA, English, UEFA and FIFA use the fixed order: Category → Country → County → Club Name → Subcategory (Caps / Hats / Beanie).</p></div></div>
        <form method="post" action="{{ route('admin.categories.taxonomy.build') }}" class="tax-grid"
            data-taxonomy-builder
            data-club-options-url="{{ route('admin.categories.clubs.options') }}">
            @csrf
            <label><span>Category / Organization *</span><select name="taxonomy_type" required data-taxonomy-select><option value="">Select category</option>@foreach($taxonomyTypes as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label data-country-field><span>Country</span><select name="catalog_country_id" data-country-select><option value="">Select country</option>@foreach($countries as $country)<option value="{{ $country->id }}" data-code="{{ $country->code }}" data-eu="{{ $country->is_eu ? '1':'0' }}" data-uefa="{{ $country->is_uefa ? '1':'0' }}">{{ $country->name }} ({{ $country->code }})</option>@endforeach</select></label>
            <label data-county-field hidden><span>County / Subdivision</span><select name="catalog_county_code" data-county-select disabled><option value="">Select county</option></select></label>
            <label data-club-field hidden><span data-club-label>Club / City / Town</span><select name="catalog_club_id" data-club-select disabled><option value="">Select category and country first</option>@foreach($clubs as $club)<option value="{{ $club->id }}" data-body="{{ $club->governing_body }}" data-country="{{ $club->catalog_country_id }}" data-county="{{ strtoupper((string) $club->catalog_county_code) }}">{{ $club->name }} · {{ $club->country?->name }}</option>@endforeach</select></label>
            <div class="tax-field"><span>Product Type *</span><div class="tax-checks">@foreach($productTypes as $value=>$label)<label><input type="checkbox" name="product_types[]" value="{{ $value }}" checked> {{ $label }}</label>@endforeach</div></div>
            <label><span>Style / Range</span><select name="style"><option value="">No style level</option>@foreach($styles as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <div class="tax-span-2"><button class="tax-btn primary" type="submit">Create Menu Hierarchy</button></div>
        </form>
    </section>

    <section class="tax-card">
        <div class="tax-section-title"><h2>Taxonomy menu items</h2><span class="tax-pill">{{ number_format($categories->total()) }} records</span></div>
        <form class="tax-filter" method="get" action="{{ route('admin.categories.taxonomy') }}">
            <select name="taxonomy_type"><option value="">All Categories</option>@foreach($taxonomyTypes as $value=>$label)<option value="{{ $value }}" @selected($filters['taxonomy']===$value)>{{ $label }}</option>@endforeach</select>
            <select name="catalog_country_id"><option value="">All Countries</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($filters['countryId']===$country->id)>{{ $country->name }}</option>@endforeach</select>
            <input type="text" name="catalog_county_code" value="{{ $filters['countyCode'] }}" placeholder="County code, e.g. IE-LK">
            <select name="catalog_club_id"><option value="">All Clubs</option>@foreach($clubs as $club)<option value="{{ $club->id }}" @selected($filters['clubId']===$club->id)>{{ $club->name }}</option>@endforeach</select>
            <select name="product_type"><option value="">All Product Types</option>@foreach($productTypes as $value=>$label)<option value="{{ $value }}" @selected($filters['productType']===$value)>{{ $label }}</option>@endforeach</select>
            <button class="tax-btn" type="submit">Filter</button><a class="tax-btn" href="{{ route('admin.categories.taxonomy') }}">Reset</a>
        </form>
        <div class="tax-table-wrap" style="margin-top:12px"><table class="tax-table"><thead><tr><th>Menu Item</th><th>Category</th><th>Country</th><th>County</th><th>Club</th><th>Product Type</th><th>Parent</th><th>Public</th></tr></thead><tbody>
            @forelse($categories as $category)
                <tr><td><div class="tax-path"><strong>{{ $category->name }}</strong><small>{{ $category->slug }}</small></div></td><td><span class="tax-pill">{{ $taxonomyTypes[$category->taxonomy_type] ?? str($category->taxonomy_type)->headline() }}</span></td><td>{{ $category->catalogCountry?->name ?? '—' }}</td><td>{{ $countyNames[$category->catalog_county_code] ?? ($category->catalog_county_code ?: '—') }}</td><td>{{ $category->catalogClub?->name ?? '—' }}</td><td>{{ $productTypes[$category->product_type] ?? '—' }}</td><td>{{ $category->parent?->name ?? 'SHOP ALL' }}</td><td><a href="{{ route('category', ['category'=>$category->slug]) }}" target="_blank" rel="noopener">Open ↗</a></td></tr>
            @empty<tr><td colspan="8"><div class="tax-empty">No taxonomy menu items match these filters.</div></td></tr>@endforelse
        </tbody></table></div>
        <div class="tax-pagination">{{ $categories->links() }}</div>
    </section>
</div>

<script>
(() => {
    const root=document.querySelector('[data-taxonomy-page]'); if(!root)return;
    const form=root.querySelector('[data-taxonomy-builder]'); if(!form)return;
    const counties=@json($countyOptionsByCountry);
    const geo=@json(array_values($geoTaxonomies));
    const countyTypes=@json(array_values($countyTaxonomies));
    const requiredCounty=@json(array_values($requiredCountyTaxonomies));
    const clubTypes=@json(array_values($clubTaxonomies));
    const requiredClub=@json(array_values($requiredClubTaxonomies));
    const clubOptionsUrl=form.dataset.clubOptionsUrl||'';
    let clubRequestSerial=0;

    const type=form.querySelector('[data-taxonomy-select]');
    const country=form.querySelector('[data-country-select]');
    const countryField=form.querySelector('[data-country-field]');
    const county=form.querySelector('[data-county-select]');
    const countyField=form.querySelector('[data-county-field]');
    const club=form.querySelector('[data-club-select]');
    const clubField=form.querySelector('[data-club-field]');
    const clubLabel=form.querySelector('[data-club-label]');

    const refreshCounty=()=>{
        const value=type.value;
        const enabled=countyTypes.includes(value);
        countyField.hidden=!enabled;
        county.required=requiredCounty.includes(value);
        county.disabled=!enabled;
        const selected=county.value;
        county.replaceChildren(new Option(enabled&&country.value?'Select county / subdivision':'Select country first',''));
        if(enabled&&country.value){
            const code=country.selectedOptions[0]?.dataset.code||'';
            for(const row of (counties[code]||[])) county.add(new Option(`${row.name} (${row.type})`,row.code,false,row.code===selected));
        }
    };

    const refreshClubs=async()=>{
        const value=type.value;
        const enabled=clubTypes.includes(value);
        const required=requiredClub.includes(value);
        const organization=value ? value.toUpperCase() : '';
        const requestSerial=++clubRequestSerial;

        clubField.hidden=!enabled;
        club.required=required;

        if(clubLabel){
            clubLabel.innerHTML=required
                ? `${organization} Club / City / Town *`
                : 'Club / City / Town';
        }

        const placeholder=!enabled
            ? 'Not required for this category'
            : !country.value
                ? `Select country before ${organization} club`
                : !county.value
                    ? `Select county before ${organization} club`
                    : `Loading ${organization} clubs...`;

        club.replaceChildren(new Option(placeholder,''));
        club.disabled=!enabled||!country.value||!county.value;

        if(!enabled||!country.value||!county.value||!clubOptionsUrl)return;

        try{
            const params=new URLSearchParams({
                governing_body:value,
                catalog_country_id:country.value,
                catalog_county_code:county.value,
            });
            const response=await fetch(`${clubOptionsUrl}?${params.toString()}`,{
                headers:{Accept:'application/json'},
                credentials:'same-origin',
            });
            if(!response.ok)throw new Error('Unable to load club options.');
            const payload=await response.json();
            if(requestSerial!==clubRequestSerial)return;

            const rows=Array.isArray(payload.clubs)?payload.clubs:[];
            club.replaceChildren(new Option(
                rows.length
                    ? `Select ${organization} club / city / town`
                    : `No ${organization} clubs found — add in Club Master`,
                ''
            ));
            for(const row of rows){
                club.add(new Option(row.name,String(row.id)));
            }
            club.disabled=false;
        }catch(error){
            if(requestSerial!==clubRequestSerial)return;
            club.replaceChildren(new Option(`Unable to load ${organization} clubs`,''));
            club.disabled=false;
        }
    };

    const refreshCountries=()=>{
        const value=type.value;
        const enabled=geo.includes(value);
        countryField.hidden=!enabled;
        country.required=enabled;
        country.disabled=!enabled;
        [...country.options].forEach((o,i)=>{
            if(i===0)return;
            const allowed=!enabled ? false : value==='uefa' ? o.dataset.uefa==='1' : (['traditional','heritage'].includes(value) ? o.dataset.eu==='1' : true);
            o.hidden=!allowed;
            o.disabled=!allowed;
        });
        if(country.selectedOptions[0]?.disabled)country.value='';
        refreshCounty();
        void refreshClubs();
    };

    type.addEventListener('change',refreshCountries);
    country.addEventListener('change',()=>{county.value='';club.value='';refreshCounty();void refreshClubs();});
    county.addEventListener('change',()=>{club.value='';void refreshClubs();});
    refreshCountries();
})();
</script>
@endsection
