@extends('layouts.site')
@section('body-class','franchise-reference-page')
@section('title','Franchise Opportunity | Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/franchise.css?v=20260908-approved-reference">
@endpush
@section('content')
<div class="franchise-reference" data-approved-reference="franchise page.png">
    <section class="fr-hero" aria-labelledby="franchise-title">
        <div class="fr-hero-copy">
            <div class="fr-breadcrumb"><a href="/">Home</a><span>›</span><span>Franchise Opportunity</span></div>
            <p class="fr-kicker">JOIN THE LEGACY.</p>
            <h1 id="franchise-title">FRANCHISE WITH<br><em>EMERALD ROZALIA</em></h1>
            <p class="fr-intro">Partner with Ireland's premium hat brand and be part of a growing global legacy. Authentic Irish craftsmanship, timeless style, proven business.</p>
            <div class="fr-hero-points" aria-label="Franchise highlights">
                <article><x-icon name="clover" size="32" /><strong>Authentic<br>Irish Brand</strong></article>
                <article><x-icon name="star" size="32" /><strong>Premium<br>Quality</strong></article>
                <article><x-icon name="chart" size="32" /><strong>Proven<br>Business Model</strong></article>
                <article><x-icon name="globe" size="32" /><strong>Global<br>Opportunity</strong></article>
            </div>
        </div>
        <div class="fr-store-image fr-store-image--hero" role="img" aria-label="Emerald Rozalia franchise retail store"></div>
    </section>

    <section class="fr-main-band" id="franchise-enquiry">
        <div class="fr-partner-area">
            <h2>WHY PARTNER WITH US?</h2>
            <div class="fr-partner-grid">
                <article><x-icon name="star" size="42" /><h3>STRONG BRAND HERITAGE</h3><p>Built on Irish heritage, quality and timeless style loved by customers worldwide.</p></article>
                <article><x-icon name="chart" size="42" /><h3>PROVEN BUSINESS MODEL</h3><p>Established systems, marketing support and operational guidance for your success.</p></article>
                <article><x-icon name="users" size="42" /><h3>COMPREHENSIVE SUPPORT</h3><p>From site selection to training, we're with you every step of the way.</p></article>
                <article><x-icon name="package" size="42" /><h3>PREMIUM PRODUCTS</h3><p>High-quality, Irish made hats and caps with strong margins and repeat demand.</p></article>
                <article><x-icon name="message" size="42" /><h3>MARKETING SUPPORT</h3><p>National &amp; local marketing campaigns, in-store branding and digital support.</p></article>
                <article><x-icon name="globe" size="42" /><h3>GROWING GLOBAL MARKET</h3><p>Join a growing brand with expanding demand across Ireland and worldwide.</p></article>
            </div>
        </div>

        <aside class="fr-form-card" aria-label="Franchise application form">
            <h2>INTERESTED IN OWNING YOUR<br><em>EMERALD ROZALIA</em> STORE?</h2>
            <p>Fill out the form and our franchise team will get in touch with you.</p>
            <form method="post" action="{{ route('inquiry') }}">
                @csrf
                <input type="hidden" name="type" value="franchise">
                <input name="name" value="{{ old('name') }}" placeholder="Full Name *" autocomplete="name" required>
                <input name="email" type="email" value="{{ old('email') }}" placeholder="Email Address *" autocomplete="email" required>
                <input name="phone" value="{{ old('phone') }}" placeholder="Phone Number *" autocomplete="tel" required>
                <div class="fr-form-row">
                    <select name="country" aria-label="Country" required>
                        <option value="">Country *</option>
                        @foreach(['Ireland','United Kingdom','United States','France','Germany','Spain','Italy','Netherlands','Belgium','United Arab Emirates','Canada','Australia','Other'] as $country)
                            <option value="{{ $country }}" @selected(old('country')===$country)>{{ $country }}</option>
                        @endforeach
                    </select>
                    <input name="company" value="{{ old('company') }}" placeholder="Preferred City / Region *" required>
                </div>
                <textarea name="message" placeholder="Tell us about yourself and your interest in franchising with us *" required>{{ old('message') }}</textarea>
                <label class="fr-consent"><input type="checkbox" name="consent" value="1" @checked(old('consent')) required><span>I agree to the <a href="/privacy-policy">Privacy Policy</a> and <a href="/terms-conditions">Terms &amp; Conditions</a>.</span></label>
                <button type="submit">SUBMIT ENQUIRY</button>
            </form>
            <div class="fr-secure"><x-icon name="check" size="18" /><span>Your information is 100% secure and confidential.</span></div>
        </aside>
    </section>

    <section class="fr-advantage">
        <h2>THE EMERALD ROZALIA ADVANTAGE</h2>
        <div class="fr-metrics">
            <article><x-icon name="home" size="34" /><strong>35+</strong><span>Retail Partners<br>Worldwide</span></article>
            <article><x-icon name="globe" size="34" /><strong>12</strong><span>Countries<br>Represented</span></article>
            <article><x-icon name="tag" size="34" /><strong>100+</strong><span>Premium Styles<br>and Counting</span></article>
            <article><x-icon name="calendar" size="34" /><strong>10+</strong><span>Years of Heritage<br>&amp; Experience</span></article>
        </div>
    </section>

    <section class="fr-lower-grid">
        <article class="fr-list-card">
            <h2>WHAT WE PROVIDE</h2>
            <ul>
                <li><x-icon name="check" size="15" />Exclusive territory opportunities</li>
                <li><x-icon name="check" size="15" />Store design &amp; fit-out guidance</li>
                <li><x-icon name="check" size="15" />Staff training &amp; product knowledge</li>
                <li><x-icon name="check" size="15" />Retail operations manual</li>
                <li><x-icon name="check" size="15" />Ongoing business development support</li>
                <li><x-icon name="check" size="15" />Access to new collections &amp; innovations</li>
            </ul>
        </article>
        <div class="fr-store-image fr-store-image--interior" role="img" aria-label="Emerald Rozalia store interior"></div>
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
            <div class="fr-world-image" role="img" aria-label="Limerick and Irish heritage"></div>
            <blockquote>“ From Limerick to the world.<br>A brand. A legacy. An opportunity.<br>Let's build it together. ”</blockquote>
        </div>
    </section>

    <section class="fr-journey">
        <div><strong>BE PART OF OUR JOURNEY. BUILD YOUR FUTURE WITH <em>EMERALD ROZALIA.</em></strong><span>Apply today and take the first step towards owning your Emerald Rozalia store.</span></div>
        <a href="#franchise-enquiry">APPLY NOW <x-icon name="arrow-right" size="20" /></a>
    </section>

    <section class="fr-service-strip" aria-label="Service benefits">
        <article><x-icon name="clover" size="28" /><div><strong>IRISH MADE</strong><span>Proudly made in Limerick</span></div></article>
        <article><x-icon name="star" size="28" /><div><strong>PREMIUM QUALITY</strong><span>Finest materials, built to last</span></div></article>
        <article><x-icon name="truck" size="28" /><div><strong>FAST DISPATCH</strong><span>Worldwide delivery</span></div></article>
        <article><x-icon name="refresh" size="28" /><div><strong>EASY RETURNS</strong><span>30-day returns</span></div></article>
        <article><x-icon name="credit-card" size="28" /><div><strong>SECURE PAYMENT</strong><span>100% secure checkout</span></div></article>
    </section>

    <footer class="fr-compact-footer">
        <div class="fr-footer-brand"><img src="{{ asset('assets/logo/logo_two_line.png') }}" alt="Emerald Rozalia Limited"><span>© {{ date('Y') }} All Rights Reserved.</span></div>
        <nav aria-label="Franchise footer links"><a href="/factory">About Us</a><a href="/contact">Contact Us</a><a href="/factory">FAQs</a><a href="/factory">Shipping &amp; Returns</a><a href="/terms-conditions">Terms &amp; Conditions</a><a href="/privacy-policy">Privacy Policy</a></nav>
        <div class="fr-social"><span>FOLLOW US</span><x-icon name="facebook" size="18" /><x-icon name="instagram" size="18" /><x-icon name="music" size="18" /><x-icon name="youtube" size="18" /></div>
    </footer>
</div>
@endsection
