@extends('layouts.site')
@section('body-class','bulk-order-page')
@section('title','Bulk Order Solutions — Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/bulk-order.css?v=20260908-approved">
@endpush
@section('content')
<div class="bulk-shell" data-reference-contract="BULK ORDER SOLUTIONS | OUR BULK ORDER PROCESS | REQUEST A BULK QUOTE | WHAT YOU CAN ORDER | QUANTITY, LEAD TIME & PRICING">
    <section class="bulk-hero" aria-labelledby="bulk-order-title">
        <div class="bulk-hero-copy">
            <h1 id="bulk-order-title">BULK ORDER<br><em>SOLUTIONS</em></h1>
            <div class="bulk-ornament"><span></span><x-icon name="clover" size="18" /><span></span></div>
            <h2>Premium Headwear. Made in Limerick.</h2>
            <p>High-quality hats and caps for brands, businesses, events and organisations. Custom designs, expert craftsmanship and reliable worldwide delivery.</p>
            <div class="bulk-hero-benefits" aria-label="Bulk order advantages">
                <article><x-icon name="clover" size="26" /><strong>Premium<br>Quality</strong></article>
                <article><x-icon name="pencil" size="26" /><strong>Custom Branding<br>&amp; Embroidery</strong></article>
                <article><x-icon name="percent" size="26" /><strong>Competitive<br>Bulk Pricing</strong></article>
                <article><x-icon name="globe" size="26" /><strong>Worldwide<br>Delivery</strong></article>
            </div>
        </div>
        <div class="bulk-reference bulk-reference--hero" role="img" aria-label="Custom branded Emerald Rozalia caps and premium flat cap displayed for bulk ordering"></div>
    </section>

    <section class="bulk-value-strip" aria-label="Bulk order service commitments">
        <article><x-icon name="clover" size="36" /><div><strong>IRISH MADE</strong><span>Crafted in Limerick, Ireland with pride.</span></div></article>
        <article><x-icon name="check" size="36" /><div><strong>PREMIUM QUALITY</strong><span>Finest materials and expert craftsmanship.</span></div></article>
        <article><x-icon name="users" size="36" /><div><strong>EXPERT TEAM</strong><span>Skilled professionals ensuring excellence in every order.</span></div></article>
        <article><x-icon name="globe" size="36" /><div><strong>WORLDWIDE DELIVERY</strong><span>Fast, reliable shipping to anywhere in the world.</span></div></article>
    </section>

    <div class="bulk-main-grid">
        <div class="bulk-main-left">
            <section class="bulk-panel bulk-process-panel">
                <h2>OUR BULK ORDER PROCESS</h2>
                <div class="bulk-process">
                    @php($steps = [
                        ['message','ENQUIRE','Tell us your requirements.'],
                        ['pencil','DESIGN & QUOTE','We create designs and provide a competitive quote.'],
                        ['clipboard','APPROVE','You approve the sample, design and final details.'],
                        ['settings','PRODUCE','We manufacture your order with precision and care.'],
                        ['package','DELIVER','On-time delivery to your door, worldwide.'],
                    ])
                    @foreach($steps as $index => [$icon,$label,$copy])
                        <article>
                            <div class="bulk-step-icon"><x-icon :name="$icon" size="32" /></div>
                            <span class="bulk-step-number">{{ $index + 1 }}</span>
                            <strong>{{ $label }}</strong>
                            <p>{{ $copy }}</p>
                        </article>
                        @if(!$loop->last)<span class="bulk-step-arrow"><x-icon name="arrow-right" size="22" /></span>@endif
                    @endforeach
                </div>
            </section>

            <section class="bulk-panel bulk-products-panel">
                <h2>WHAT YOU CAN ORDER</h2>
                <div class="bulk-product-grid">
                    @foreach([
                        ['embroidered','EMBROIDERED CAPS','Premium embroidery for a lasting impression.'],
                        ['printed','PRINTED CAPS','High quality print for bold branding.'],
                        ['custom','CUSTOM DESIGNS','Bespoke styles to match your brand identity.'],
                        ['flatcap','PREMIUM FLAT CAPS','Timeless style with modern craftsmanship.'],
                        ['bucket','BUCKET HATS','Comfortable, durable and perfect for every occasion.'],
                    ] as [$photo,$label,$copy])
                        <article>
                            <div class="bulk-reference bulk-product-photo bulk-product-photo--{{ $photo }}" role="img" aria-label="{{ $label }} example"></div>
                            <strong>{{ $label }}</strong>
                            <p>{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="bulk-panel bulk-pricing-panel">
                <h2>QUANTITY, LEAD TIME &amp; PRICING</h2>
                <div class="bulk-pricing-grid">
                    <article><x-icon name="package" size="31" /><div><strong>QUANTITY</strong><span>Flexible MOQs to suit your needs.<br><br>MOQ from 50 pcs<br>Bulk discounts on larger quantities.</span></div></article>
                    <article><x-icon name="clock" size="31" /><div><strong>LEAD TIME</strong><span>Standard production time 2–4 weeks.<br><br>Rush orders available on request.</span></div></article>
                    <article><x-icon name="percent" size="31" /><div><strong>PRICING</strong><span>Competitive pricing for bulk orders.<br><br>Best value without compromising quality.</span></div></article>
                </div>
            </section>
        </div>

        <aside class="bulk-quote-panel" id="bulk-quote">
            <div class="bulk-quote-heading"><x-icon name="clover" size="22" /><h2>REQUEST A BULK QUOTE</h2></div>
            <p>Fill in the form below and our team will get back to you within 24 hours.</p>
            <form method="post" action="{{ route('inquiry') }}" novalidate>
                @csrf
                <input type="hidden" name="type" value="bulk-orders">
                <label class="bulk-field"><x-icon name="user" size="16" /><span>Full Name</span><input name="name" value="{{ old('name') }}" placeholder="Full Name" autocomplete="name" required></label>
                <label class="bulk-field"><x-icon name="briefcase" size="16" /><span>Company Name</span><input name="company" value="{{ old('company') }}" placeholder="Company Name" autocomplete="organization"></label>
                <label class="bulk-field"><x-icon name="mail" size="16" /><span>Email Address</span><input type="email" name="email" value="{{ old('email') }}" placeholder="Email Address" autocomplete="email" required></label>
                <label class="bulk-field"><x-icon name="phone" size="16" /><span>Phone Number</span><input name="phone" value="{{ old('phone') }}" placeholder="Phone Number" autocomplete="tel"></label>
                <label class="bulk-field bulk-field--select"><x-icon name="globe" size="16" /><span>Country</span><select name="country" aria-label="Country"><option value="">Country</option>@foreach(['Ireland','United Kingdom','France','Germany','Spain','Italy','Netherlands','Belgium','United States','Canada','Australia','New Zealand','United Arab Emirates','Saudi Arabia','Other'] as $country)<option value="{{ $country }}" @selected(old('country')===$country)>{{ $country }}</option>@endforeach</select></label>
                <label class="bulk-message"><span>Your requirements</span><textarea name="message" placeholder="Tell us about your requirements&#10;(Quantity, Product, Branding, Delivery Date, etc.)" required>{{ old('message') }}</textarea></label>
                <button class="bulk-submit" type="submit">SEND REQUEST <x-icon name="arrow-right" size="18" /></button>
            </form>
            <div class="bulk-or"><span></span><b>OR</b><span></span></div>
            <a class="bulk-whatsapp" href="https://wa.me/35361525400?text=Hello%20Emerald%20Rozalia%2C%20I%20would%20like%20a%20bulk%20order%20quote." target="_blank" rel="noopener"><x-icon name="message" size="20" /> CHAT ON WHATSAPP</a>
            <div class="bulk-assurances">
                <article><x-icon name="check" size="25" /><span>Secure &amp;<br>Confidential</span></article>
                <article><x-icon name="file-text" size="25" /><span>No Obligation<br>Quote</span></article>
                <article><x-icon name="clock" size="25" /><span>Fast Response<br>Guaranteed</span></article>
            </div>
        </aside>
    </div>

    <section class="bulk-trust-grid">
        <div class="bulk-panel bulk-trusted">
            <h2>TRUSTED BY ORGANISATIONS WORLDWIDE</h2>
            <div class="bulk-reference bulk-reference--trust" role="img" aria-label="Organisation logos shown in the approved bulk-order reference"></div>
            <p>... and hundreds of businesses that trust us to represent their brand with quality.</p>
        </div>
        <div class="bulk-panel bulk-why">
            <h2>WHY CHOOSE EMERALD ROZALIA?</h2>
            <div class="bulk-why-grid">
                <article><x-icon name="clover" size="29" /><strong>IRISH HERITAGE</strong><span>Limerick born,<br>Irish made.</span></article>
                <article><x-icon name="check" size="29" /><strong>PREMIUM QUALITY</strong><span>The finest materials and attention to every detail.</span></article>
                <article><x-icon name="pencil" size="29" /><strong>CUSTOM BRANDING</strong><span>Embroidery, print and custom labels available.</span></article>
                <article><x-icon name="globe" size="29" /><strong>RELIABLE SERVICE</strong><span>Dedicated support from start to finish.</span></article>
                <article><x-icon name="star" size="29" /><strong>BUILT TO LAST</strong><span>Timeless style, made to endure.</span></article>
            </div>
        </div>
    </section>

    <section class="bulk-factory-panel">
        <div class="bulk-factory-copy">
            <h2>WELCOME TO <em>VISIT OUR FACTORY</em></h2>
            <p>We welcome partners, clients and friends to visit our Limerick factory. See our craftsmanship, meet our team and experience the Emerald Rozalia quality firsthand.</p>
            <a href="/contact" class="bulk-factory-button"><x-icon name="calendar" size="18" /> BOOK A FACTORY VISIT</a>
        </div>
        <div class="bulk-factory-features">
            <article><x-icon name="home" size="34" /><strong>SEE OUR<br>CRAFTSMANSHIP</strong><span>Experience our<br>process up close.</span></article>
            <article><x-icon name="users" size="34" /><strong>MEET OUR TEAM</strong><span>The people behind<br>our quality.</span></article>
            <article><x-icon name="package" size="34" /><strong>EXPLORE<br>OUR RANGE</strong><span>Discover materials,<br>styles &amp; options.</span></article>
        </div>
        <div class="bulk-reference bulk-reference--factory" role="img" aria-label="Emerald Rozalia factory in Limerick"></div>
    </section>

    <footer class="bulk-footer">
        <div class="bulk-footer-brand"><img src="{{ asset('assets/logo/logo_two_line.png') }}" alt="Emerald Rozalia Limited"><p><strong>Irish Made.</strong> Limerick Born.<br><em>Worn Everywhere.</em></p></div>
        <div><h3>SHOP</h3><a href="/shop">All Hats &amp; Caps</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/irish-traditional">Flat Caps</a><a href="/category/bucket-hats">Bucket Hats</a><a href="/collections">Beanies &amp; More</a></div>
        <div><h3>HELP</h3><a href="/factory">FAQs</a><a href="/factory">Shipping &amp; Returns</a><a href="/factory">Size Guide</a><a href="/account">Track Your Order</a></div>
        <div><h3>COMPANY</h3><a href="/factory">About Us</a><a href="/factory">Our Factory</a><a href="/global-network">Sustainability</a><a href="/careers">Careers</a></div>
        <div class="bulk-footer-contact"><h3>GET IN TOUCH</h3><a href="mailto:info@emeraldrozalia.ie"><x-icon name="mail" size="13" /> info@emeraldrozalia.ie</a><a href="tel:+35361525400"><x-icon name="phone" size="13" /> +353 61 525 400</a><span><x-icon name="globe" size="13" /> Limerick, Ireland</span><div class="bulk-socials"><x-icon name="instagram" size="16" /><x-icon name="facebook" size="16" /><x-icon name="linkedin" size="16" /><x-icon name="youtube" size="16" /></div></div>
        <div class="bulk-footer-bottom"><span>© 2024 Emerald Rozalia Limited. All Rights Reserved.</span><span><a href="/privacy-policy">Privacy Policy</a><i></i><a href="/terms-conditions">Terms &amp; Conditions</a><i></i><a href="/cookie-policy">Cookie Policy</a></span></div>
    </footer>
</div>
@endsection
