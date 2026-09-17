@extends('layouts.admin')
@section('title','Language & Currency Manager')
@section('content')
<div style="max-width:1200px;margin:0 auto;padding:22px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:18px">
        <div><div style="color:#13884d;font-weight:800;letter-spacing:.12em">SETTINGS · LOCALIZATION</div><h1 style="margin:5px 0">Language & Currency Manager</h1><p style="margin:0;color:#66756c">Manage the storefront languages, currencies and manual exchange rates used by Project 1.</p></div>
        <a href="{{ route('admin.settings.page','localization') }}" class="btn">Localization Settings</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{session('success')}}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{$errors->first()}}</div>@endif
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
        <section class="card" style="padding:18px"><h2>Languages</h2><p>Active languages appear in the public header.</p>
            @foreach($languages as $language)
            <form method="post" action="{{route('admin.localization.language')}}" style="display:grid;grid-template-columns:90px 1fr 1fr auto auto;gap:8px;margin:8px 0">@csrf
                <input name="locale" value="{{$language->locale}}" readonly><input name="name" value="{{$language->name}}"><input name="native_name" value="{{$language->native_name}}">
                <label><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($language->active)> Active</label><button type="submit">Save</button>
            </form>@endforeach
            <form method="post" action="{{route('admin.localization.language')}}" style="display:grid;grid-template-columns:90px 1fr 1fr auto auto;gap:8px;margin-top:14px">@csrf
                <input name="locale" placeholder="fr" required><input name="name" placeholder="French" required><input name="native_name" placeholder="Français" required><label><input type="checkbox" name="active" value="1" checked> Active</label><button type="submit">Add</button>
            </form>
        </section>
        <section class="card" style="padding:18px"><h2>Currencies</h2><p>Only enabled storefront currencies should have a current exchange rate.</p>
            @foreach($currencies as $currency)
                @php($storefrontEnabled = $currency->code === $baseCurrency || (bool) optional($company?->currencies()->where('currencies.code',$currency->code)->first())->pivot?->enabled_storefront)
                <form method="post" action="{{route('admin.localization.currency')}}" style="display:grid;grid-template-columns:70px 1fr 70px 70px auto auto;gap:8px;margin:8px 0">@csrf
                    <input name="code" value="{{$currency->code}}" readonly><input name="name" value="{{$currency->name}}"><input name="symbol" value="{{$currency->symbol}}"><input type="number" name="decimals" min="0" max="4" value="{{$currency->decimals}}">
                    <input type="hidden" name="active" value="1"><label><input type="hidden" name="enabled_storefront" value="0"><input type="checkbox" name="enabled_storefront" value="1" @checked($storefrontEnabled)> Storefront</label><button type="submit">Save</button>
                </form>
            @endforeach
        </section>
    </div>
    <section class="card" style="padding:18px;margin-top:18px"><h2>Exchange Rates</h2><p>Base currency: <strong>{{$baseCurrency}}</strong>. Rates are manual snapshots; review them before accepting non-{{$baseCurrency}} payments.</p>
        <div style="display:flex;gap:22px;flex-wrap:wrap;margin-bottom:12px">@foreach($rates as $code=>$rate)<span><strong>{{$baseCurrency}}/{{$code}}</strong> {{number_format((float)$rate->rate,8)}} <small>{{optional($rate->rate_date)->format('d M Y')}} · {{$rate->source}}</small></span>@endforeach</div>
        <form method="post" action="{{route('admin.localization.rate')}}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">@csrf
            <label>Quote currency <select name="quote_currency">@foreach($currencies->where('code','!=',$baseCurrency) as $currency)<option value="{{$currency->code}}">{{$currency->code}} — {{$currency->name}}</option>@endforeach</select></label>
            <label>1 {{$baseCurrency}} equals <input type="number" name="rate" step="0.00000001" min="0.00000001" required></label><button type="submit">Save Rate</button>
        </form>
    </section>
</div>
@endsection
