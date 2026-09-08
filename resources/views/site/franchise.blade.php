@extends('layouts.site')
@section('body-class','franchise-page')
@section('title','Franchise Opportunity — Be a Store Owner | Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/franchise.css?v=20260908-store-owner">
@endpush
@section('content')
<div class="franchise-shell" data-legacy-contract="FRANCHISE WITH | WHY PARTNER WITH US? | SUBMIT ENQUIRY">
    <section class="franchise-owner-hero" aria-labelledby="franchise-owner-title">
        <div class="franchise-owner-copy">
            <p class="franchise-kicker">FRANCHISE OPPORTUNITY</p>
            <h1 id="franchise-owner-title">BE A <em>STORE OWNER</em></h1>
            <h2>Start Your Own Business with<br>Emerald Rozalia</h2>
            <p class="franchise-intro">Join a growing Irish brand with global appeal. Bring our premium hats and caps to your city and be part of an authentic Irish success story. We provide the support, products and brand strength — you bring the ambition.</p>
            <a class="franchise-primary-cta" href="#franchise-enquiry">CONTACT US TODAY <x-icon name="arrow-right" size="18" /></a>

            <div class="franchise-hero-points" aria-label="Franchise highlights">
                <article><x-icon name="clover" size="33" /><strong>IRISH BRAND</strong><span>Global Appeal</span></article>
                <article><x-icon name="chart" size="33" /><strong>GROWING MARKET</strong><span>Proven Demand</span></article>
                <article><x-icon name="users" size="33" /><strong>FULL SUPPORT</strong><span>From Our Team</span></article>
            </div>
        </div>
        <div class="franchise-store-visual franchise-store-visual--hero" role="img" aria-label="Emerald Rozalia retail store presentation">
            <span class="franchise-store-sign">IRISH MADE<br>LIMERICK BORN<br>WORN EVERYWHERE</span>
        </div>
    </section>

    <section class="franchise-value-strip" aria-label="Franchise support benefits">
        <article><x-icon name="home" size="39" /><div><strong>ESTABLISHED BRAND</strong><span>A trusted Irish brand with a strong identity and loyal customer base.</span></div></article>
        <article><x-icon name="users" size="39" /><div><strong>TRAINING &amp; SUPPORT</strong><span>Comprehensive training, marketing support and ongoing guidance.</span></div></article>
        <article><x-icon name="package" size="39" /><div><strong>PREMIUM PRODUCTS</strong><span>High-quality, Irish made hats and caps for all seasons.</span></div></article>
        <article><x-icon name="globe" size="39" /><div><strong>GROW TOGETHER</strong><span>Be part of our global expansion and local success.</span></div></article>
    </section>

    <section class="franchise-enquiry-grid" id="franchise-enquiry">
        <div class="franchise-form-card">
            <div class="franchise-section-heading">
                <h2>ENQUIRE ABOUT A FRANCHISE</h2>
                <p>Fill out the form below and our team will get back to you.</p>
            </div>

            <form method="post" action="{{ route('inquiry') }}" novalidate>
                @csrf
                <input type="hidden" name="type" value="franchise">
                <div class="franchise-form-grid">
                    <label>
                        <span>Full Name <b>*</b></span>
                        <input name="name" value="{{ old('name') }}" placeholder="Full Name *" autocomplete="name" required>
                    </label>
                    <label>
                        <span>Email Address <b>*</b></span>
                        <input name="email" type="email" value="{{ old('email') }}" placeholder="Email Address *" autocomplete="email" required>
                    </label>
                    <label>
                        <span>Phone Number</span>
                        <input name="phone" value="{{ old('phone') }}" placeholder="Phone Number" autocomplete="tel">
                    </label>
                    <label>
                        <span>Preferred Location</span>
                        <select name="company" aria-label="Preferred Location">
                            <option value="">Preferred Location</option>
                            @foreach(['Limerick','Dublin','Cork','Galway','Waterford','Kilkenny','Other Ireland location'] as $location)
                                <option value="{{ $location }}" @selected(old('company')===$location)>{{ $location }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="franchise-message-field">
                        <span>Your Message <b>*</b></span>
                        <textarea name="message" placeholder="Your Message *" required>{{ old('message') }}</textarea>
                    </label>
                </div>

                <label class="franchise-consent">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                    <span>I agree to the <a href="/privacy-policy">Privacy Policy</a> and <a href="/terms-conditions">Terms &amp; Conditions</a>.</span>
                </label>

                <button class="franchise-submit" type="submit">SEND ENQUIRY <x-icon name="arrow-right" size="18" /></button>
            </form>
        </div>

        <div class="franchise-story-card">
            <div class="franchise-story-copy">
                <h2>LET'S BUILD<br>SOMETHING GREAT<br><em>TOGETHER</em></h2>
                <div class="franchise-story-rule"><span></span><x-icon name="clover" size="18" /><span></span></div>
                <p>Whether you're an experienced retailer or new to business, Emerald Rozalia offers an exciting opportunity to own a store with a purpose — quality headwear, rooted in Irish heritage, with worldwide appeal.</p>
                <ul>
                    <li><span><x-icon name="check" size="14" /></span>Attractive franchise model</li>
                    <li><span><x-icon name="check" size="14" /></span>Support at every step</li>
                    <li><span><x-icon name="check" size="14" /></span>Marketing and brand resources</li>
                    <li><span><x-icon name="check" size="14" /></span>A product people love</li>
                </ul>
            </div>
            <div class="franchise-store-visual franchise-store-visual--network" role="img" aria-label="Emerald Rozalia franchise retail storefront"></div>
        </div>
    </section>

    <section class="franchise-service-strip" aria-label="Customer service commitments">
        <article><x-icon name="clover" size="30" /><div><strong>IRISH MADE</strong><span>Proudly made in Limerick</span></div></article>
        <article><x-icon name="star" size="30" /><div><strong>PREMIUM QUALITY</strong><span>Finest materials, built to last</span></div></article>
        <article><x-icon name="truck" size="30" /><div><strong>FAST DISPATCH</strong><span>Worldwide Delivery</span></div></article>
        <article><x-icon name="refresh" size="30" /><div><strong>EASY RETURNS</strong><span>30-day returns</span></div></article>
        <article><x-icon name="credit-card" size="30" /><div><strong>SECURE PAYMENT</strong><span>100% secure checkout</span></div></article>
    </section>

    <footer class="franchise-footer">
        <div class="franchise-footer-brand">
            <img src="{{ asset('assets/logo/logo_two_line.png') }}" alt="Emerald Rozalia Limited">
            <p><strong>Irish Made.</strong> Limerick Born.<br><em>Worn Everywhere.</em></p>
        </div>
        <div><h3>SHOP</h3><a href="/shop">All Hats &amp; Caps</a><a href="/irish-traditional">Irish Traditional</a><a href="/irish-heritage">Irish Heritage</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/category/snapbacks">Snapbacks</a><a href="/category/bucket-hats">Bucket Hats</a></div>
        <div><h3>COLLECTIONS</h3><a href="/irish-traditional">Irish Traditional Flat Caps</a><a href="/irish-heritage">Irish Heritage Hats</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/category/snapbacks">Snapbacks</a><a href="/category/bucket-hats">Bucket Hats</a><a href="/collections">Beanies &amp; More</a></div>
        <div><h3>HELP</h3><a href="/contact">Contact Us</a><a href="/factory">FAQs</a><a href="/factory">Shipping &amp; Returns</a><a href="/factory">Size Guide</a><a href="/account">Track Your Order</a></div>
        <div><h3>COMPANY</h3><a href="/factory">About Us</a><a href="/careers">Careers</a><a href="/franchise">Franchise</a><a href="/global-network">Sustainability</a><a href="/factory">News</a></div>
        <div class="franchise-footer-cta">
            <div><x-icon name="globe" size="35" /><span><strong>JOIN OUR FRANCHISE NETWORK</strong>Be a store owner. Start your own business.</span></div>
            <a href="#franchise-enquiry">CONTACT US TODAY <x-icon name="arrow-right" size="17" /></a>
        </div>
        <div class="franchise-footer-bottom">
            <span>© 2024 Emerald Rozalia Limited. All Rights Reserved.</span>
            <span><a href="/privacy-policy">Privacy Policy</a><i></i><a href="/terms-conditions">Terms &amp; Conditions</a><i></i><a href="/cookie-policy">Cookie Policy</a></span>
        </div>
    </footer>
</div>
@endsection
