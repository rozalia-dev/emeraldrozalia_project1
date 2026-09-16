@php
    $countryPayload = $countryOptions->map(fn ($country) => [
        'code' => $country->code,
        'name' => $country->name,
    ])->values();
    $clubPayload = $clubOptions->map(fn ($club) => [
        'slug' => $club->slug,
        'name' => $club->name,
        'country' => $club->country?->code,
    ])->values();
@endphp
<style id="shop-country-club-filter-style">
.shop-select-label{display:grid;gap:5px;padding-bottom:11px;color:#9eada2;font-size:8px}.shop-filter-select{width:100%;height:35px;padding:0 30px 0 9px;border:1px solid #414d39;border-radius:4px;background:#07130e;color:#f2f3ef;font-size:10px;outline:none}.shop-filter-select:focus{border-color:#91bc1c;box-shadow:0 0 0 1px #91bc1c}.shop-filter-select:disabled{opacity:.5;cursor:not-allowed}.shop-filter-select option{background:#07130e;color:#f2f3ef}.shop-filter-summary-caret{color:#90b91e;font-size:11px;transition:transform .15s}.shop-filter-group[open] .shop-filter-summary-caret{transform:rotate(180deg)}
</style>
<script>
(() => {
    const form = document.querySelector('[data-shop-filters] form');
    const searchBox = form?.querySelector('.shop-search-box');
    if (!form || !searchBox || form.querySelector('[data-shop-country-filter]')) return;

    const countries = @json($countryPayload);
    const clubs = @json($clubPayload);
    const selectedCountry = @json($selectedCountryCode);
    const selectedClub = @json($selectedClubSlug);
    const showClub = @json((bool) $showClubFilter);
    const taxonomy = @json($taxonomy);

    const createGroup = (label) => {
        const details = document.createElement('details');
        details.className = 'shop-filter-group';
        details.open = true;
        const summary = document.createElement('summary');
        summary.append(document.createTextNode(label + ' '));
        const caret = document.createElement('span');
        caret.className = 'shop-filter-summary-caret';
        caret.setAttribute('aria-hidden', 'true');
        caret.textContent = '⌄';
        summary.append(caret);
        const body = document.createElement('div');
        body.className = 'shop-filter-options';
        details.append(summary, body);
        return {details, body};
    };

    const makeLabel = (caption) => {
        const label = document.createElement('label');
        label.className = 'shop-select-label';
        const span = document.createElement('span');
        span.textContent = caption;
        label.append(span);
        return label;
    };

    const countryGroup = createGroup('COUNTRY');
    const countryLabel = makeLabel(taxonomy === 'uefa' ? 'Select UEFA Country / Association' : (taxonomy === 'traditional' || taxonomy === 'heritage' ? 'Select EU Country' : 'Select Country'));
    const countrySelect = document.createElement('select');
    countrySelect.name = 'country';
    countrySelect.className = 'shop-filter-select';
    countrySelect.setAttribute('data-shop-country-filter', '');
    countrySelect.setAttribute('aria-label', 'Select country');
    countrySelect.add(new Option('All Countries', ''));
    countries.forEach((country) => countrySelect.add(new Option(country.name, country.code, false, country.code === selectedCountry)));
    countryLabel.append(countrySelect);
    countryGroup.body.append(countryLabel);
    searchBox.after(countryGroup.details);

    let clubSelect = null;
    let clubCaption = null;
    if (showClub) {
        const clubGroup = createGroup('CLUB');
        const clubLabel = makeLabel('Select a country first');
        clubCaption = clubLabel.querySelector('span');
        clubSelect = document.createElement('select');
        clubSelect.name = 'club';
        clubSelect.className = 'shop-filter-select';
        clubSelect.setAttribute('data-shop-club-filter', '');
        clubSelect.setAttribute('aria-label', 'Select club');
        clubLabel.append(clubSelect);
        clubGroup.body.append(clubLabel);
        countryGroup.details.after(clubGroup.details);
    }

    const rebuildClubs = (preserveSelection = false) => {
        if (!clubSelect) return;
        const country = countrySelect.value;
        const previous = preserveSelection ? selectedClub : clubSelect.value;
        clubSelect.replaceChildren(new Option('All Clubs', ''));
        clubs.filter((club) => club.country === country).forEach((club) => {
            clubSelect.add(new Option(club.name, club.slug, false, club.slug === previous));
        });
        clubSelect.disabled = !country;
        if (clubCaption) clubCaption.textContent = country ? 'Select Club' : 'Select a country first';
        if (!country) clubSelect.value = '';
    };

    rebuildClubs(true);
    countrySelect.addEventListener('change', () => rebuildClubs(false));

    const mobileFilterButton = document.querySelector('[data-shop-filter-toggle]');
    const extraCount = Number(Boolean(selectedCountry)) + Number(Boolean(selectedClub));
    if (mobileFilterButton && extraCount > 0) {
        let badge = mobileFilterButton.querySelector('span');
        if (!badge) {
            badge = document.createElement('span');
            badge.textContent = '0';
            mobileFilterButton.append(badge);
        }
        badge.textContent = String(Number(badge.textContent || 0) + extraCount);
    }
})();
</script>
