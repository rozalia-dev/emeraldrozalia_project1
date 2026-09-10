@extends('layouts.site')
@section('body-class','corporate-order-page')
@section('title','Corporate Orders — Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/corporate-order.css?v=20260908-approved">
<link rel="stylesheet" href="/css/order-fullwidth.css?v=20260910-fullwidth">
@endpush
@section('content')
<div class="corporate-shell" data-reference-contract="CORPORATE ORDERS | HOW IT WORKS | WHAT WE OFFER | REQUEST A QUOTE | WHY CHOOSE EMERALD ROZALIA | TRUSTED BY ORGANISATIONS WORLDWIDE" data-reference-image="/assets/brand/corporate-order-reference.png?v=20260908">
    <section class="corporate-hero" aria-labelledby="corporate-order-title">
        <div class="corporate-hero-copy">
            <h1 id="corporate-order-title">CORPORATE<br><em>ORDERS</em></h1>
            <div class="corporate-ornament"><span></span><x-icon name="clover" size="18" /><span></span></div>
            <h2>Premium Headwear. Professional Impact.</h2>
            <p>From branded caps for your team to custom designs for events, promotions and corporate gifting, we deliver quality headwear that represents your brand with pride.</p>

            <div class="corporate-hero-benefits" aria-label="Corporate order advantages">
                <article><x-icon name="star" size="29" /><strong>Premium<br>Quality</strong></article>
                <article><x-icon name="pencil" size="29" /><strong>Custom Branding<br>&amp; Embroidery</strong></article>
                <article><x-icon name="percent" size="29" /><strong>Bulk Pricing<br>&amp; Discounts</strong></article>
                <article><x-icon name="globe" size="29" /><strong>Reliable<br>Worldwide Delivery</strong></article>
            </div>
        </div>
        <div class="corporate-reference corporate-reference--hero" role="img" aria-label="Premium corporate branded caps and Emerald Rozalia presentation packaging"></div>
    </section>

    <section class="corporate-audiences" aria-label="Corporate order use cases">
        @foreach([
            ['briefcase','Corporate Uniforms'],
            ['clover','Events & Promotions'],
            ['users','Sports Teams'],
            ['shopping-bag','Hospitality & Retail'],
            ['home','Schools & Colleges'],
            ['package','Gifts & Merchandise'],
        ] as [$icon,$label])
            <article><x-icon :name="$icon" size="30" /><span>{{ $label }}</span></article>
        @endforeach
    </section>

    <div class="corporate-main-grid">
        <div class="corporate-main-left">
            <section class="corporate-panel corporate-process-panel">
                <h2>HOW IT WORKS</h2>
                <div class="corporate-process">
                    @php($steps = [
                        ['message','ENQUIRE','Tell us your requirements.'],
                        ['pencil','DESIGN','We create your custom design.'],
                        ['check','APPROVE','Review and approve your sample.'],
                        ['settings','PRODUCE','We manufacture with care.'],
                        ['truck','DELIVER','On-time delivery worldwide.'],
                    ])
                    @foreach($steps as $index => [$icon,$label,$copy])
                        <article>
                            <div class="corporate-step-icon"><x-icon :name="$icon" size="31" /></div>
                            <span class="corporate-step-number">{{ $index + 1 }}</span>
                            <strong>{{ $label }}</strong>
                            <p>{{ $copy }}</p>
                        </article>
                        @if(!$loop->last)<span class="corporate-step-arrow"><x-icon name="arrow-right" size="19" /></span>@endif
                    @endforeach
                </div>
            </section>

            <section class="corporate-panel corporate-offer-panel">
                <h2>WHAT WE OFFER</h2>
                <div class="corporate-offer-grid">
                    @foreach([
                        ['embroidered','EMBROIDERED CAPS','Premium embroidery for a lasting impression.'],
                        ['printed','PRINTED CAPS','High quality print for bold branding.'],
                        ['custom','CUSTOM DESIGNS','Bespoke styles to match your brand identity.'],
                        ['quality','PREMIUM QUALITY','Durable materials. Exceptional comfort.'],
                    ] as [$photo,$label,$copy])
                        <article>
                            <div class="corporate-reference corporate-offer-photo corporate-offer-photo--{{ $photo }}" role="img" aria-label="{{ $label }} example"></div>
                            <strong>{{ $label }}</strong>
                            <p>{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="corporate-panel corporate-why-panel">
                <h2>WHY CHOOSE EMERALD ROZALIA?</h2>
                <div class="corporate-why-grid">
                    <article><x-icon name="clover" size="30" /><strong>IRISH MADE</strong><span>Designed and manufactured in Limerick, Ireland.</span></article>
                    <article><x-icon name="check" size="30" /><strong>PREMIUM QUALITY</strong><span>Finest materials and expert craftsmanship.</span></article>
                    <article><x-icon name="users" size="30" /><strong>EXPERIENCED TEAM</strong><span>Years of experience delivering to global brands.</span></article>
                    <article><x-icon name="package" size="30" /><strong>FLEXIBLE ORDERS</strong><span>No order too big or too small.</span></article>
                    <article><x-icon name="globe" size="30" /><strong>WORLDWIDE DELIVERY</strong><span>Fast and reliable shipping across the globe.</span></article>
                </div>
            </section>
        </div>

        <aside class="corporate-quote-panel" id="corporate-quote">
            <div class="corporate-quote-heading"><x-icon name="clover" size="22" /><h2>REQUEST A QUOTE</h2></div>
            <p>Fill in the form below and our team will get back to you within 24 hours.</p>
            <form method="post" action="{{ route('inquiry') }}" novalidate>
                @csrf
                <input type="hidden" name="type" value="corporate-orders">
                <label class="corporate-field"><x-icon name="user" size="16" /><span>Full Name</span><input name="name" value="{{ old('name') }}" placeholder="Full Name" autocomplete="name" required></label>
                <label class="corporate-field"><x-icon name="briefcase" size="16" /><span>Company Name</span><input name="company" value="{{ old('company') }}" placeholder="Company Name" autocomplete="organization"></label>
                <label class="corporate-field"><x-icon name="mail" size="16" /><span>Email Address</span><input type="email" name="email" value="{{ old('email') }}" placeholder="Email Address" autocomplete="email" required></label>
                <label class="corporate-field"><x-icon name="phone" size="16" /><span>Phone Number</span><input name="phone" value="{{ old('phone') }}" placeholder="Phone Number" autocomplete="tel"></label>
                <label class="corporate-field corporate-field--select"><x-icon name="globe" size="16" /><span>Country</span><select name="country" aria-label="Country"><option value="">Country</option>@foreach(['Ireland','United Kingdom','France','Germany','Spain','Italy','Netherlands','Belgium','United States','Canada','Australia','New Zealand','United Arab Emirates','Saudi Arabia','Other'] as $country)<option value="{{ $country }}" @selected(old('country')===$country)>{{ $country }}</option>@endforeach</select></label>
                <label class="corporate-message"><span>Your requirements</span><textarea name="message" placeholder="Tell us about your requirements&#10;(Product, Quantity, Branding, Delivery Date, etc.)" required>{{ old('message') }}</textarea></label>
                <button class="corporate-submit" type="submit">SEND REQUEST <x-icon name="arrow-right" size="18" /></button>
            </form>
            <div class="corporate-or"><span></span><b>OR</b><span></span></div>
            <a class="corporate-whatsapp" href="https://wa.me/353899788187?text=Hello%20Emerald%20Rozalia%2C%20I%20would%20like%20a%20corporate%20order%20quote." target="_blank" rel="noopener"><x-icon name="message" size="21" /> CHAT ON WHATSAPP</a>
            <div class="corporate-assurances">
                <article><x-icon name="check" size="25" /><span>Secure &amp;<br>Confidential</span></article>
                <article><x-icon name="file-text" size="25" /><span>No Obligation<br>Quote</span></article>
                <article><x-icon name="clock" size="25" /><span>Fast Response<br>Guaranteed</span></article>
            </div>
        </aside>
    </div>

    <section class="corporate-panel corporate-trusted">
        <h2>TRUSTED BY ORGANISATIONS WORLDWIDE</h2>
        <div class="corporate-reference corporate-reference--trust" role="img" aria-label="Corporate client logos from the approved reference"></div>
        <p>Join hundreds of businesses that trust us to represent their brand with quality.</p>
    </section>

    <footer class="corporate-footer">
        <div class="corporate-footer-brand">
            <img src="{{ asset('assets/logo/logo_two_line.png') }}" alt="Emerald Rozalia Limited">
            <p><strong>Irish Made.</strong> Limerick Born.<br><em>Worn Everywhere.</em></p>
            <div class="corporate-socials"><x-icon name="instagram" size="17" /><x-icon name="facebook" size="17" /><x-icon name="linkedin" size="17" /><x-icon name="youtube" size="17" /></div>
        </div>
        <div><h3>SHOP</h3><a href="/shop">All Hats &amp; Caps</a><a href="/irish-traditional">Irish Traditional</a><a href="/irish-heritage">Irish Heritage</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/category/snapbacks">Snapbacks</a><a href="/category/bucket-hats">Bucket Hats</a><a href="/collections">Beanies &amp; More</a></div>
        <div><h3>COLLECTIONS</h3><a href="/irish-traditional">Irish Traditional Flat Caps</a><a href="/irish-heritage">Irish Heritage Hats</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/category/snapbacks">Snapbacks</a><a href="/category/bucket-hats">Bucket Hats</a><a href="/collections">Beanies &amp; More</a></div>
        <div><h3>HELP</h3><a href="/contact">Contact Us</a><a href="/factory">FAQs</a><a href="/factory">Shipping &amp; Returns</a><a href="/factory">Size Guide</a><a href="/account">Track Your Order</a></div>
        <div><h3>COMPANY</h3><a href="/factory">About Us</a><a href="/careers">Careers</a><a href="/franchise">Franchise</a><a href="/global-network">Sustainability</a><a href="/new-arrivals">News</a></div>
        <div class="corporate-footer-contact">
            <h3>LET'S WORK TOGETHER</h3>
            <p>For corporate orders and partnerships, we're here to help your brand stand out.</p>
            <a href="mailto:info@emeraldrozalia.ie"><x-icon name="mail" size="14" /> info@emeraldrozalia.ie</a>
            <a href="https://wa.me/353899788187" target="_blank" rel="noopener"><x-icon name="message" size="14" /> WhatsApp: 0899788187</a>
        </div>
        <div class="corporate-footer-bottom">
            <span>© 2024 Emerald Rozalia Limited. All Rights Reserved.</span>
            <span><a href="/privacy-policy">Privacy Policy</a><i></i><a href="/terms-conditions">Terms &amp; Conditions</a><i></i><a href="/cookie-policy">Cookie Policy</a></span>
        </div>
    </footer>
</div>
@endsection
