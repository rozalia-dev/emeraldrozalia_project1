@extends('layouts.site')
@section('body-class','corporate-order-page')
@section('title','Corporate Orders — Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/corporate-order.css?v=20260908-approved">
<link rel="stylesheet" href="/css/order-fullwidth.css?v=20260910-fullwidth">
<link rel="stylesheet" href="/css/corporate-order-functional.css?v=20260915-generated-hero">
@endpush
@php($corporateLogo = app(\App\Services\PublicMediaResolver::class)->forLegacyPath('assets/logo/logo_two_line.png', 'Emerald Rozalia Limited'))
@section('content')
<div class="corporate-shell" data-reference-contract="CORPORATE ORDERS | HOW IT WORKS | WHAT WE OFFER | REQUEST A QUOTE | WHY CHOOSE EMERALD ROZALIA | TRUSTED BY ORGANISATIONS WORLDWIDE" data-page-runtime-source="{{ $corporatePage ? 'managed-page' : 'approved-reference-fallback' }}">
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
        @if($corporateHeroMedia)
            <figure class="corporate-reference corporate-reference--hero corporate-media-ready" data-public-media-state="approved" aria-label="Approved Emerald Rozalia corporate headwear">
                <img src="{{ $corporateHeroMedia['url'] }}" @if($corporateHeroMedia['srcset']) srcset="{{ $corporateHeroMedia['srcset'] }}" sizes="{{ $corporateHeroMedia['sizes'] }}" @endif @if($corporateHeroMedia['width']) width="{{ $corporateHeroMedia['width'] }}" @endif @if($corporateHeroMedia['height']) height="{{ $corporateHeroMedia['height'] }}" @endif alt="{{ $corporateHeroMedia['alt'] }}" loading="eager" fetchpriority="high">
            </figure>
        @else
            <div class="corporate-reference corporate-reference--hero" data-public-media-state="awaiting-approved-media" role="img" aria-label="Approved corporate hero media is not configured"><span class="corporate-media-empty">Approved corporate media is not configured.</span></div>
        @endif
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
            <article><a href="#corporate-quote" aria-label="Request a quote for {{ $label }}"><x-icon :name="$icon" size="30" /><span>{{ $label }}</span></a></article>
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
                        @php($offerMedia = $corporateOfferMedia[$photo] ?? null)
                        <article>
                            @if($offerMedia)
                                <div class="corporate-reference corporate-offer-photo corporate-offer-photo--{{ $photo }} corporate-media-ready" data-public-media-state="approved">
                                    <img src="{{ $offerMedia['url'] }}" @if($offerMedia['srcset']) srcset="{{ $offerMedia['srcset'] }}" sizes="{{ $offerMedia['sizes'] }}" @endif @if($offerMedia['width']) width="{{ $offerMedia['width'] }}" @endif @if($offerMedia['height']) height="{{ $offerMedia['height'] }}" @endif alt="{{ $offerMedia['alt'] }}" loading="lazy">
                                </div>
                            @else
                                <div class="corporate-reference corporate-offer-photo corporate-offer-photo--{{ $photo }}" data-public-media-state="awaiting-approved-media" role="img" aria-label="Approved {{ $label }} media is not configured"><span class="corporate-media-empty">Approved media is not configured.</span></div>
                            @endif
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

        <aside class="corporate-quote-panel" id="corporate-quote" tabindex="-1">
            <div class="corporate-quote-heading"><x-icon name="clover" size="22" /><h2>REQUEST A QUOTE</h2></div>
            <p>Fill in the form below and our team will get back to you within 24 hours.</p>

            @if(session('success'))
                <div class="corporate-form-alert corporate-form-alert--success" role="status" tabindex="-1">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="corporate-form-alert corporate-form-alert--error" role="alert" tabindex="-1">
                    <strong>Please check your quote request.</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="post" action="{{ route('inquiry') }}" data-corporate-quote-form>
                @csrf
                <input type="hidden" name="type" value="corporate-orders">
                <input type="hidden" name="idempotency_key" value="{{ $corporateIdempotencyKey }}">
                <label class="corporate-field @error('name') is-invalid @enderror"><x-icon name="user" size="16" /><span>Full Name</span><input name="name" value="{{ old('name') }}" placeholder="Full Name" autocomplete="name" required maxlength="120" @error('name') aria-invalid="true" aria-describedby="corporate-name-error" @enderror></label>
                @error('name')<small class="corporate-field-error" id="corporate-name-error">{{ $message }}</small>@enderror

                <label class="corporate-field @error('company') is-invalid @enderror"><x-icon name="briefcase" size="16" /><span>Company Name</span><input name="company" value="{{ old('company') }}" placeholder="Company Name" autocomplete="organization" maxlength="180" @error('company') aria-invalid="true" aria-describedby="corporate-company-error" @enderror></label>
                @error('company')<small class="corporate-field-error" id="corporate-company-error">{{ $message }}</small>@enderror

                <label class="corporate-field @error('email') is-invalid @enderror"><x-icon name="mail" size="16" /><span>Email Address</span><input type="email" name="email" value="{{ old('email') }}" placeholder="Email Address" autocomplete="email" required maxlength="190" @error('email') aria-invalid="true" aria-describedby="corporate-email-error" @enderror></label>
                @error('email')<small class="corporate-field-error" id="corporate-email-error">{{ $message }}</small>@enderror

                <label class="corporate-field @error('phone') is-invalid @enderror"><x-icon name="phone" size="16" /><span>Phone Number</span><input name="phone" value="{{ old('phone') }}" placeholder="Phone Number" autocomplete="tel" maxlength="60" @error('phone') aria-invalid="true" aria-describedby="corporate-phone-error" @enderror></label>
                @error('phone')<small class="corporate-field-error" id="corporate-phone-error">{{ $message }}</small>@enderror

                <label class="corporate-field corporate-field--select @error('country') is-invalid @enderror"><x-icon name="globe" size="16" /><span>Country</span><select name="country" aria-label="Country" @error('country') aria-invalid="true" aria-describedby="corporate-country-error" @enderror><option value="">Country</option>@foreach(['Ireland','United Kingdom','France','Germany','Spain','Italy','Netherlands','Belgium','United States','Canada','Australia','New Zealand','United Arab Emirates','Saudi Arabia','Other'] as $country)<option value="{{ $country }}" @selected(old('country')===$country)>{{ $country }}</option>@endforeach</select></label>
                @error('country')<small class="corporate-field-error" id="corporate-country-error">{{ $message }}</small>@enderror

                <label class="corporate-message @error('message') is-invalid @enderror"><span>Your requirements</span><textarea name="message" placeholder="Tell us about your requirements&#10;(Product, Quantity, Branding, Delivery Date, etc.)" required maxlength="5000" @error('message') aria-invalid="true" aria-describedby="corporate-message-error" @enderror>{{ old('message') }}</textarea></label>
                @error('message')<small class="corporate-field-error" id="corporate-message-error">{{ $message }}</small>@enderror

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
        @if($corporateTrustedMedia->isNotEmpty())
            <div class="corporate-trusted-media" data-public-media-state="approved">
                @foreach($corporateTrustedMedia as $clientMedia)
                    <img src="{{ $clientMedia['url'] }}" @if($clientMedia['srcset']) srcset="{{ $clientMedia['srcset'] }}" sizes="180px" @endif @if($clientMedia['width']) width="{{ $clientMedia['width'] }}" @endif @if($clientMedia['height']) height="{{ $clientMedia['height'] }}" @endif alt="{{ $clientMedia['alt'] }}" loading="lazy">
                @endforeach
            </div>
        @elseif($corporateTrustedNames->isNotEmpty())
            <div class="corporate-trusted-names" data-public-data-state="published-client-records">
                @foreach($corporateTrustedNames as $clientName)<span>{{ $clientName }}</span>@endforeach
            </div>
        @else
            <div class="corporate-reference corporate-reference--trust" data-public-media-state="awaiting-approved-media" role="img" aria-label="Approved corporate client-logo media is not configured"><span class="corporate-media-empty">Approved client media is not configured.</span></div>
            <p data-public-data-state="awaiting-approved-client-records">Approved client records will appear here when they are published.</p>
        @endif
    </section>

    <footer class="corporate-footer">
        <div class="corporate-footer-brand">
            @if($corporateLogo)<img src="{{ $corporateLogo['url'] }}" @if($corporateLogo['srcset']) srcset="{{ $corporateLogo['srcset'] }}" sizes="{{ $corporateLogo['sizes'] }}" @endif width="{{ $corporateLogo['width'] ?: '' }}" height="{{ $corporateLogo['height'] ?: '' }}" alt="{{ $corporateLogo['alt'] }}">@else<span class="public-media-missing">Emerald Rozalia Limited</span>@endif
            <p><strong>Irish Made.</strong> Limerick Born.<br><em>Worn Everywhere.</em></p>
            <div class="corporate-socials" aria-hidden="true"><x-icon name="instagram" size="17" /><x-icon name="facebook" size="17" /><x-icon name="linkedin" size="17" /><x-icon name="youtube" size="17" /></div>
        </div>
        <div><h3>SHOP</h3><a href="/shop">All Hats &amp; Caps</a><a href="/irish-traditional">Irish Traditional</a><a href="/irish-heritage">Irish Heritage</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/category/snapbacks">Snapbacks</a><a href="/category/bucket-hats">Bucket Hats</a><a href="/collections">Beanies &amp; More</a></div>
        <div><h3>COLLECTIONS</h3><a href="/irish-traditional">Irish Traditional Flat Caps</a><a href="/irish-heritage">Irish Heritage Hats</a><a href="/category/baseball-caps">Baseball Caps</a><a href="/category/snapbacks">Snapbacks</a><a href="/category/bucket-hats">Bucket Hats</a><a href="/collections">Beanies &amp; More</a></div>
        <div><h3>HELP</h3><a href="/contact">Contact Us</a><a href="/factory">FAQs</a><a href="/factory">Shipping &amp; Returns</a><a href="/factory">Size Guide</a><a href="/account">Track Your Order</a></div>
        <div><h3>COMPANY</h3><a href="/factory">About Us</a><a href="/careers">Careers</a><a href="/franchise">Franchise</a><a href="/global-network">Sustainability</a><a href="/new-arrivals">News</a></div>
        <div class="corporate-footer-contact">
            <h3>LET'S WORK TOGETHER</h3>
            <p>For corporate orders and partnerships, we're here to help your brand stand out.</p>
            <a href="mailto:urmos@rozalia.ie"><x-icon name="mail" size="14" /> urmos@rozalia.ie</a>
            <a href="https://wa.me/353899788187" target="_blank" rel="noopener"><x-icon name="message" size="14" /> WhatsApp: 0899788187</a>
        </div>
        <div class="corporate-footer-bottom">
            <span>© {{ now()->year }} Emerald Rozalia Limited. All Rights Reserved.</span>
            <span><a href="/privacy-policy">Privacy Policy</a><i></i><a href="/terms-conditions">Terms &amp; Conditions</a><i></i><a href="/cookie-policy">Cookie Policy</a></span>
        </div>
    </footer>
</div>
@endsection