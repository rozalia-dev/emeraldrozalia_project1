@php
    $countryOptions = $countryOptions ?? collect();
    $clubOptions = $clubOptions ?? collect();
    $selectedCountryCode = $selectedCountryCode ?? '';
    $selectedClubSlug = $selectedClubSlug ?? '';
@endphp

<details class="shop-filter-group" open>
    <summary>COUNTRY <x-icon name="chevron-down" size="13" /></summary>
    <div class="shop-filter-options">
        <label class="shop-select-label">
            <span>Select Country</span>
            <select name="country" class="shop-filter-select" data-shop-country-filter>
                <option value="">All Countries</option>
                @foreach($countryOptions as $country)
                    <option value="{{ $country->code }}" @selected($selectedCountryCode === $country->code)>{{ $country->name }}</option>
                @endforeach
            </select>
        </label>
    </div>
</details>

@if($showClubFilter ?? false)
<details class="shop-filter-group" open>
    <summary>CLUB <x-icon name="chevron-down" size="13" /></summary>
    <div class="shop-filter-options">
        <label class="shop-select-label">
            <span>{{ $selectedCountryCode ? 'Select Club' : 'Select a country first' }}</span>
            <select name="club" class="shop-filter-select" @disabled(!$selectedCountryCode)>
                <option value="">All Clubs</option>
                @foreach($clubOptions as $club)
                    <option value="{{ $club->slug }}" @selected($selectedClubSlug === $club->slug)>{{ $club->name }}</option>
                @endforeach
            </select>
        </label>
    </div>
</details>
@endif
