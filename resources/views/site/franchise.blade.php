@extends('layouts.site')
@section('body-class','franchise-reference-page')
@section('title','Franchise Opportunity | Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/franchise.css?v=20260919-franchise-flow">
@endpush
@section('content')
<div class="franchise-reference" data-public-media-register="franchise" data-public-media-state="awaiting-approved-media">
    <section class="fr-hero" aria-labelledby="franchise-title">
        <div class="fr-hero-copy">
            <div class="fr-breadcrumb"><a href="/">Home</a><span>›</span><span>Franchise Opportunity</span></div>
            <p class="fr-kicker">JOIN THE LEGACY.</p>
            <h1 id="franchise-title">FRANCHISE WITH<br><em>EMERALD ROZALIA</em></h1>
            <p class="fr-intro">Explore an Ireland-first retail opportunity with an Irish manufacturer based in Limerick. Tell us the area you have in mind, your experience and your plans.</p>
            <div class="fr-hero-points" aria-label="Franchise highlights">
                <article><x-icon name="clover" size="32" /><strong>Authentic<br>Irish Brand</strong></article>
                <article><x-icon name="star" size="32" /><strong>Premium<br>Quality</strong></article>
                <article><x-icon name="home" size="32" /><strong>Limerick<br>Headquarters</strong></article>
                <article><x-icon name="map-pin" size="32" /><strong>Ireland-first<br>Enquiries</strong></article>
            </div>
        </div>
        <div class="fr-store-image fr-store-image--hero" data-public-media-state="awaiting-approved-media" role="img" aria-label="Approved franchise retail-store media is not configured"><span class="fr-media-empty">Approved franchise media is not configured.</span></div>
    </section>

    <section class="fr-main-band" id="franchise-enquiry">
        <div class="fr-partner-area">
            <h2>WHY PARTNER WITH US?</h2>
            <p class="fr-section-intro">A first conversation to explore whether Emerald Rozalia could be the right fit for your plans.</p>
            <div class="fr-partner-grid">
                <article><x-icon name="star" size="42" /><h3>IRISH BRAND HERITAGE</h3><p>Our hat and cap business is rooted in Limerick and Irish craftsmanship.</p></article>
                <article><x-icon name="map-pin" size="42" /><h3>IRELAND-FIRST ENQUIRIES</h3><p>We are currently hearing from people interested in locations across Ireland.</p></article>
                <article><x-icon name="users" size="42" /><h3>FRANCHISE GUIDANCE</h3><p>Discuss territory availability, store planning and next steps with our team.</p></article>
                <article><x-icon name="package" size="42" /><h3>PREMIUM PRODUCTS</h3><p>Explore the Emerald Rozalia range of Irish-made hats and caps.</p></article>
                <article><x-icon name="message" size="42" /><h3>BRAND RESOURCES</h3><p>Ask about brand assets and marketing resources during your enquiry.</p></article>
                <article><x-icon name="globe" size="42" /><h3>BUILT IN LIMERICK</h3><p>Start with the story of an Irish manufacturer based in Limerick.</p></article>
            </div>
        </div>

        <aside class="fr-form-card" aria-labelledby="franchise-form-title">
            <h2 id="franchise-form-title">START A FRANCHISE<br><em>CONVERSATION</em></h2>
            <p>Franchise enquiries currently focus on Ireland. Share your preferred area, experience and plans. Investment details are optional at this stage.</p>
            <form method="post" action="{{ route('inquiry') }}">
                @csrf
                <input type="hidden" name="type" value="franchise">
                <input type="hidden" name="country" value="Ireland">
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <label class="fr-field"><span>Full name <b>*</b></span><input name="name" value="{{ old('name') }}" autocomplete="name" required></label>
                <div class="fr-form-row">
                    <label class="fr-field"><span>Email address <b>*</b></span><input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></label>
                    <label class="fr-field"><span>Phone number <b>*</b></span><input name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required></label>
                </div>
                <label class="fr-field"><span>Preferred town or county in Ireland <b>*</b></span><input name="preferred_location" value="{{ old('preferred_location', old('company')) }}" autocomplete="address-level2" maxlength="180" required></label>
                <div class="fr-form-row">
                    <label class="fr-field"><span>Investment range <small>(optional)</small></span><input name="investment_range" value="{{ old('investment_range') }}" maxlength="180" placeholder="Share only what you’re comfortable discussing"></label>
                    <label class="fr-field"><span>Opening timeline <small>(optional)</small></span><select name="opening_timeline">
                        <option value="">Select a timeframe</option>
                        @foreach(['Exploring options','Within 6 months','6–12 months','More than 12 months'] as $timeline)
                            <option value="{{ $timeline }}" @selected(old('opening_timeline')===$timeline)>{{ $timeline }}</option>
                        @endforeach
                    </select></label>
                </div>
                <label class="fr-field"><span>Retail or business experience and plans <b>*</b></span><textarea name="business_experience" maxlength="3000" required>{{ old('business_experience', old('message')) }}</textarea></label>
                <label class="fr-consent"><input type="checkbox" name="consent" value="1" @checked(old('consent')) required><span>I agree to the <a href="/privacy-policy">Privacy Policy</a> and <a href="/terms-conditions">Terms &amp; Conditions</a>.</span></label>
                <button type="submit">SEND FRANCHISE ENQUIRY <x-icon name="arrow-right" size="16" /></button>
            </form>
            <div class="fr-secure"><span>This is an initial enquiry, not a franchise commitment.</span></div>
        </aside>
    </section>

    <section class="fr-process" aria-labelledby="franchise-process-title">
        <h2 id="franchise-process-title">WHAT HAPPENS NEXT</h2>
        <p>Sending an enquiry starts a conversation. It does not make an offer or reserve a territory.</p>
        <div class="fr-process-grid">
            <article><span>01</span><h3>SHARE YOUR PLANS</h3><p>Tell us which Irish area you have in mind and what you hope to build.</p></article>
            <article><span>02</span><h3>INITIAL REVIEW</h3><p>Your application is recorded for the franchise team to review.</p></article>
            <article><span>03</span><h3>DISCUSS NEXT STEPS</h3><p>Talk through location, suitability and any questions before deciding how to proceed.</p></article>
        </div>
    </section>

    <section class="fr-lower-grid">
        <article class="fr-list-card">
            <h2>WHAT WE’LL DISCUSS</h2>
            <ul>
                <li><x-icon name="check" size="15" />Availability in your preferred area</li>
                <li><x-icon name="check" size="15" />Store location and format</li>
                <li><x-icon name="check" size="15" />Product range and retail requirements</li>
                <li><x-icon name="check" size="15" />Training and onboarding needs</li>
                <li><x-icon name="check" size="15" />Brand and marketing resources</li>
                <li><x-icon name="check" size="15" />Possible timeline and next steps</li>
            </ul>
        </article>
        <div class="fr-store-image fr-store-image--interior" data-public-media-state="awaiting-approved-media" role="img" aria-label="Approved franchise store-interior media is not configured"><span class="fr-media-empty">Approved franchise media is not configured.</span></div>
        <article class="fr-list-card fr-ideal">
            <h2>IDEAL PARTNER</h2>
            <ul>
                <li><x-icon name="user" size="23" />Passionate about fashion, quality and customer experience</li>
                <li><x-icon name="briefcase" size="23" />Strong business acumen and entrepreneurial mindset</li>
                <li><x-icon name="users" size="23" />Commitment to building a long-term successful business</li>
                <li><x-icon name="heart" size="23" />Proud to represent an authentic Irish brand</li>
            </ul>
        </article>
        <div class="fr-world-card">
            <div class="fr-world-image" data-public-media-state="awaiting-approved-media" role="img" aria-label="Approved franchise heritage media is not configured"><span class="fr-media-empty">Approved franchise media is not configured.</span></div>
            <blockquote>“ From Limerick to the world.<br>A brand. A legacy. An opportunity.<br>Let's build it together. ”</blockquote>
        </div>
    </section>

    <section class="fr-journey">
        <div><strong>BE PART OF OUR JOURNEY. BUILD YOUR FUTURE WITH <em>EMERALD ROZALIA.</em></strong><span>Apply today and take the first step towards owning your Emerald Rozalia store.</span></div>
        <a href="#franchise-enquiry">APPLY NOW <x-icon name="arrow-right" size="20" /></a>
    </section>


</div>
@endsection
