@extends('layouts.admin')
@section('title','Language, Translation & Currency Manager')
@push('styles')
<style>
.localization-page{max-width:1480px;margin:0 auto;padding:22px}.localization-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:18px}.localization-head h1{margin:5px 0 7px}.localization-kicker{color:#13884d;font-weight:800;letter-spacing:.12em;font-size:12px}.localization-muted{color:#66756c}.localization-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.localization-card{background:#fff;border:1px solid #dbe5dd;border-radius:12px;padding:18px;margin-bottom:18px;box-shadow:0 7px 22px rgba(20,55,35,.05)}.localization-card h2{margin:0 0 4px}.localization-scroll{overflow:auto}.localization-table{width:100%;border-collapse:collapse;font-size:13px}.localization-table th,.localization-table td{border-bottom:1px solid #e8eee9;padding:8px;text-align:left;vertical-align:top}.localization-table input,.localization-table select,.localization-table textarea,.localization-form input,.localization-form select,.localization-form textarea{width:100%;border:1px solid #cfdcd2;border-radius:7px;padding:8px;background:#fff}.localization-table textarea{min-width:240px}.localization-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.localization-actions button,.localization-actions a,.localization-button,.localization-table button,.localization-add button,.translation-editor button,.content-row button{border:1px solid #176b43;background:#176b43;color:#fff;border-radius:7px;padding:8px 12px;text-decoration:none;cursor:pointer}.localization-actions .secondary,.localization-button.secondary{background:#fff;color:#176b43}.localization-check{display:flex;gap:5px;align-items:center;white-space:nowrap}.localization-check input{width:auto}.localization-add{display:grid;grid-template-columns:100px 1fr 1fr 100px 140px auto auto auto;gap:8px;align-items:end;margin-top:14px}.currency-add{grid-template-columns:75px 1fr 85px 85px 110px auto auto auto}.rate-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:9px;margin:12px 0}.rate-chip{border:1px solid #dce7df;border-radius:8px;padding:10px}.rate-chip small{display:block;color:#708078;margin-top:3px}.translation-toolbar{display:flex;justify-content:space-between;align-items:end;gap:14px;margin-bottom:15px}.translation-toolbar form{display:flex;gap:8px;align-items:end}.translation-editor{display:grid;grid-template-columns:160px 1fr 1fr 90px;gap:8px;align-items:end}.content-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.content-list{max-height:620px;overflow:auto;border:1px solid #e0e8e2;border-radius:9px}.content-row{padding:10px;border-bottom:1px solid #edf2ee}.content-row:last-child{border-bottom:0}.content-row strong{display:block;margin-bottom:6px}.content-row form{display:grid;grid-template-columns:1fr 1.3fr auto;gap:7px}.content-row textarea{min-height:58px}.status-note{font-size:12px;color:#67766d}.localization-badge{display:inline-flex;padding:3px 7px;border-radius:999px;background:#edf7f0;color:#176b43;font-size:11px;font-weight:700}.localization-badge.off{background:#f4f4f4;color:#7b7b7b}@media(max-width:1100px){.localization-grid,.content-grid{grid-template-columns:1fr}.localization-add,.currency-add{grid-template-columns:1fr 1fr}.translation-editor{grid-template-columns:1fr 1fr}}@media(max-width:700px){.localization-head,.translation-toolbar{display:block}.localization-add,.currency-add,.translation-editor,.content-row form{grid-template-columns:1fr}.localization-page{padding:12px}}
</style>
@endpush
@section('content')
<div class="localization-page">
    <header class="localization-head">
        <div>
            <div class="localization-kicker">SETTINGS · GLOBAL STOREFRONT LOCALIZATION</div>
            <h1>Language, Translation &amp; Currency Manager</h1>
            <p class="localization-muted">Add any language or ISO currency, control what is enabled on the storefront, translate interface and catalogue content, and manage exchange rates from one cPanel screen.</p>
        </div>
        <div class="localization-actions"><a class="secondary" href="{{ route('admin.settings.page','localization') }}">Localization Settings</a><a href="{{url('/')}}" target="_blank" rel="noopener">Open Storefront</a></div>
    </header>

    @if(session('success'))<div class="alert alert-success">{{session('success')}}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{implode(' ', $errors->all())}}</div>@endif

    <div class="localization-grid">
        <section class="localization-card">
            <h2>Languages</h2>
            <p class="localization-muted">Languages are data-driven. Attach any locale to this company, set LTR/RTL, fallback language, storefront visibility and default language.</p>
            <div class="localization-scroll">
                <table class="localization-table">
                    <thead><tr><th>Locale</th><th>Name</th><th>Native name</th><th>Direction</th><th>Fallback</th><th>Storefront</th><th>Default</th><th></th></tr></thead>
                    <tbody>
                    @foreach($languages as $language)
                        @php($pivot = $languagePivots->get($language->locale)?->pivot)
                        @php($languageForm = 'language-row-'.$loop->index)
                        <tr>
                            <td>
                                <form id="{{$languageForm}}" method="post" action="{{route('admin.localization.language')}}">@csrf</form>
                                <input form="{{$languageForm}}" name="locale" value="{{$language->locale}}" readonly>
                                <input form="{{$languageForm}}" type="hidden" name="active" value="1">
                            </td>
                            <td><input form="{{$languageForm}}" name="name" value="{{$language->name}}" required></td>
                            <td><input form="{{$languageForm}}" name="native_name" value="{{$language->native_name}}" required></td>
                            <td><select form="{{$languageForm}}" name="direction"><option value="ltr" @selected(($language->direction ?? 'ltr')==='ltr')>LTR</option><option value="rtl" @selected(($language->direction ?? 'ltr')==='rtl')>RTL</option></select></td>
                            <td><select form="{{$languageForm}}" name="fallback_locale"><option value="">None</option>@foreach($languages->where('locale','!=',$language->locale) as $fallback)<option value="{{$fallback->locale}}" @selected($language->fallback_locale===$fallback->locale)>{{$fallback->native_name}} ({{$fallback->locale}})</option>@endforeach</select></td>
                            <td><label class="localization-check"><input form="{{$languageForm}}" type="hidden" name="enabled_storefront" value="0"><input form="{{$languageForm}}" type="checkbox" name="enabled_storefront" value="1" @checked((bool)($pivot?->enabled_storefront ?? false))> Enabled</label></td>
                            <td><label class="localization-check"><input form="{{$languageForm}}" type="hidden" name="is_default" value="0"><input form="{{$languageForm}}" type="checkbox" name="is_default" value="1" @checked((bool)($pivot?->is_default ?? false))> Default</label></td>
                            <td><button form="{{$languageForm}}" type="submit">Save</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <form method="post" action="{{route('admin.localization.language')}}" class="localization-add">@csrf
                <label>Locale<input name="locale" placeholder="fr-CA" required></label>
                <label>Name<input name="name" placeholder="French (Canada)" required></label>
                <label>Native name<input name="native_name" placeholder="Français (Canada)" required></label>
                <label>Direction<select name="direction"><option value="ltr">LTR</option><option value="rtl">RTL</option></select></label>
                <label>Fallback<select name="fallback_locale"><option value="">None</option>@foreach($languages as $language)<option value="{{$language->locale}}">{{$language->native_name}}</option>@endforeach</select></label>
                <label class="localization-check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" checked> Active</label>
                <label class="localization-check"><input type="hidden" name="enabled_storefront" value="0"><input type="checkbox" name="enabled_storefront" value="1"> Storefront</label>
                <button type="submit">Add Language</button>
            </form>
        </section>

        <section class="localization-card">
            <h2>Currencies</h2>
            <p class="localization-muted">Base currency: <strong>{{$baseCurrency}}</strong>. Enable any ISO currency after a valid exchange rate is available.</p>
            <div class="localization-scroll">
                <table class="localization-table">
                    <thead><tr><th>Code</th><th>Name</th><th>Symbol</th><th>Decimals</th><th>Position</th><th>Storefront</th><th>Base</th><th></th></tr></thead>
                    <tbody>
                    @foreach($currencies as $currency)
                        @php($pivot = $currencyPivots->get($currency->code)?->pivot)
                        @php($currencyForm = 'currency-row-'.$loop->index)
                        <tr>
                            <td>
                                <form id="{{$currencyForm}}" method="post" action="{{route('admin.localization.currency')}}">@csrf</form>
                                <input form="{{$currencyForm}}" name="code" value="{{$currency->code}}" readonly>
                                <input form="{{$currencyForm}}" type="hidden" name="active" value="1">
                            </td>
                            <td><input form="{{$currencyForm}}" name="name" value="{{$currency->name}}" required></td>
                            <td><input form="{{$currencyForm}}" name="symbol" value="{{$currency->symbol}}" required></td>
                            <td><input form="{{$currencyForm}}" type="number" name="decimals" min="0" max="4" value="{{$currency->decimals}}"></td>
                            <td><select form="{{$currencyForm}}" name="symbol_position"><option value="before" @selected(($currency->symbol_position ?? 'before')==='before')>Before</option><option value="after" @selected(($currency->symbol_position ?? 'before')==='after')>After</option></select></td>
                            <td><label class="localization-check"><input form="{{$currencyForm}}" type="hidden" name="enabled_storefront" value="0"><input form="{{$currencyForm}}" type="checkbox" name="enabled_storefront" value="1" @checked((bool)($pivot?->enabled_storefront ?? false))> Enabled</label></td>
                            <td><label class="localization-check"><input form="{{$currencyForm}}" type="hidden" name="is_base" value="0"><input form="{{$currencyForm}}" type="checkbox" name="is_base" value="1" @checked((bool)($pivot?->is_base ?? false))> Base</label></td>
                            <td><button form="{{$currencyForm}}" type="submit">Save</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <form method="post" action="{{route('admin.localization.currency')}}" class="localization-add currency-add">@csrf
                <label>Code<input name="code" placeholder="AED" maxlength="3" required></label>
                <label>Name<input name="name" placeholder="UAE Dirham" required></label>
                <label>Symbol<input name="symbol" placeholder="د.إ" required></label>
                <label>Decimals<input type="number" name="decimals" value="2" min="0" max="4"></label>
                <label>Position<select name="symbol_position"><option value="before">Before</option><option value="after">After</option></select></label>
                <input type="hidden" name="active" value="1">
                <label class="localization-check"><input type="hidden" name="enabled_storefront" value="0"><input type="checkbox" name="enabled_storefront" value="1"> Storefront</label>
                <label class="localization-check"><input type="hidden" name="is_base" value="0"><input type="checkbox" name="is_base" value="1"> Base</label>
                <button type="submit">Add Currency</button>
            </form>
        </section>
    </div>

    <section class="localization-card">
        <div class="translation-toolbar">
            <div><h2>Exchange Rates</h2><p class="localization-muted">Rates are dated snapshots. Orders preserve both base values and the customer transaction currency/rate used at checkout.</p></div>
            <form method="post" action="{{route('admin.localization.rates.sync')}}">@csrf<button class="localization-button" type="submit">Sync ECB Rates</button></form>
        </div>
        <div class="rate-grid">@forelse($rates as $code=>$rate)<div class="rate-chip"><strong>{{$baseCurrency}} / {{$code}}</strong><div>{{number_format((float)$rate->rate,8)}}</div><small>{{optional($rate->rate_date)->format('d M Y')}} · {{$rate->source}}</small></div>@empty<div class="status-note">No rates recorded yet.</div>@endforelse</div>
        <form method="post" action="{{route('admin.localization.rate')}}" class="translation-editor">@csrf
            <label>Quote currency<select name="quote_currency">@foreach($currencies->where('code','!=',$baseCurrency) as $currency)<option value="{{$currency->code}}">{{$currency->code}} — {{$currency->name}}</option>@endforeach</select></label>
            <label>1 {{$baseCurrency}} equals<input type="number" name="rate" step="0.00000001" min="0.00000001" required></label>
            <span class="status-note">Manual rates create or replace the current-day snapshot for that pair.</span>
            <button type="submit">Save Rate</button>
        </form>
    </section>

    <section class="localization-card">
        <div class="translation-toolbar">
            <div><h2>Storefront Interface Translation</h2><p class="localization-muted">Every enabled language uses the same database-driven translation engine. No language-specific application code is required.</p></div>
            <form method="get" action="{{route('admin.localization.index')}}"><label>Editing language<select name="locale" onchange="this.form.submit()">@foreach($languages as $language)<option value="{{$language->locale}}" @selected($selectedLocale===$language->locale)>{{$language->native_name}} ({{$language->locale}})</option>@endforeach</select></label></form>
        </div>
        <div class="localization-scroll">
            <table class="localization-table">
                <thead><tr><th>Key</th><th>Source text</th><th>{{$languages->firstWhere('locale',$selectedLocale)?->native_name ?? $selectedLocale}}</th><th>Namespace</th><th></th></tr></thead>
                <tbody>
                @foreach($translations as $translation)
                    @php($translationForm = 'translation-row-'.$translation->id)
                    <tr>
                        <td>
                            <form id="{{$translationForm}}" method="post" action="{{route('admin.localization.translation')}}">@csrf</form>
                            <input form="{{$translationForm}}" type="hidden" name="locale" value="{{$selectedLocale}}">
                            <input form="{{$translationForm}}" type="hidden" name="active" value="1">
                            <input form="{{$translationForm}}" name="translation_key" value="{{$translation->translation_key}}" required>
                        </td>
                        <td><textarea form="{{$translationForm}}" name="source_text" required>{{$translation->source_text}}</textarea></td>
                        <td><textarea form="{{$translationForm}}" name="translation" required>{{$translation->translation}}</textarea></td>
                        <td><input form="{{$translationForm}}" name="namespace" value="{{$translation->namespace}}" required></td>
                        <td><button form="{{$translationForm}}" type="submit">Save</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <h3>Add translatable phrase</h3>
        <form method="post" action="{{route('admin.localization.translation')}}" class="translation-editor">@csrf
            <input type="hidden" name="locale" value="{{$selectedLocale}}"><input type="hidden" name="active" value="1">
            <label>Key<input name="translation_key" placeholder="ui.free_shipping" required></label>
            <label>English/source text<input name="source_text" placeholder="Free shipping" required></label>
            <label>Translation<input name="translation" required></label>
            <label>Namespace<input name="namespace" value="ui" required></label>
            <button type="submit">Add Translation</button>
        </form>
    </section>

    <section class="localization-card">
        <h2>Catalogue Content Translation — {{$languages->firstWhere('locale',$selectedLocale)?->native_name ?? $selectedLocale}}</h2>
        <p class="localization-muted">Product and category names/descriptions have per-language values with automatic fallback to the configured fallback/default language.</p>
        <div class="content-grid">
            <div>
                <h3>Categories</h3>
                <div class="content-list">
                    @foreach($categories as $category)
                        @php($translated=(array)data_get($category->translations,$selectedLocale,[]))
                        <div class="content-row"><strong>{{$category->getRawOriginal('name') ?? $category->name}}</strong><form method="post" action="{{route('admin.localization.content-translation')}}">@csrf<input type="hidden" name="entity_type" value="category"><input type="hidden" name="entity_id" value="{{$category->id}}"><input type="hidden" name="locale" value="{{$selectedLocale}}"><input name="name" value="{{data_get($translated,'name')}}" placeholder="Translated category name"><textarea name="description" placeholder="Translated description">{{data_get($translated,'description')}}</textarea><button type="submit">Save</button></form></div>
                    @endforeach
                </div>
            </div>
            <div>
                <h3>Products</h3>
                <div class="content-list">
                    @foreach($products as $product)
                        @php($translated=(array)data_get($product->translations,$selectedLocale,[]))
                        <div class="content-row"><strong>{{$product->getRawOriginal('name') ?? $product->name}}</strong><form method="post" action="{{route('admin.localization.content-translation')}}">@csrf<input type="hidden" name="entity_type" value="product"><input type="hidden" name="entity_id" value="{{$product->id}}"><input type="hidden" name="locale" value="{{$selectedLocale}}"><input name="name" value="{{data_get($translated,'name')}}" placeholder="Translated product name"><textarea name="description" placeholder="Translated description">{{data_get($translated,'description')}}</textarea><button type="submit">Save</button></form></div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
@endsection