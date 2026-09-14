@extends('layouts.factory-reference')
@section('title','How We Work — Emerald Rozalia')
@php
    $factoryLogo = app(\App\Services\PublicMediaResolver::class)->forLegacyPath('assets/logo/logo_one_line.png', 'Emerald Rozalia Limited');
    $factorySteps = [
        ['Design & Development', 'We translate a clear brief into a practical hat or cap design.'],
        ['Pattern Making & Cutting', 'Patterns and material selections are prepared for a consistent fit.'],
        ['Shaping & Forming', 'Each piece is shaped with care and checked against the approved specification.'],
        ['Embroidery & Details', 'Branding, trim and finishing details are applied to the agreed design.'],
        ['Sewing & Assembly', 'Skilled makers assemble the components into a durable finished product.'],
        ['Quality Inspection', 'Every order is checked for finish, fit, presentation and traceability.'],
        ['Packing & Labelling', 'Approved products are packed and labelled for reliable dispatch.'],
        ['Ready to Deliver', 'Completed orders leave our Limerick workflow ready for customers worldwide.'],
    ];
@endphp
@section('content')
<div class="factory-page" data-public-media-register="factory" data-public-media-state="awaiting-approved-media">
    <header class="factory-page-header">
        <a class="factory-page-brand" href="/" aria-label="Emerald Rozalia home">
            @if($factoryLogo)
                <img src="{{ $factoryLogo['url'] }}" @if($factoryLogo['srcset']) srcset="{{ $factoryLogo['srcset'] }}" sizes="{{ $factoryLogo['sizes'] }}" @endif width="{{ $factoryLogo['width'] ?: '' }}" height="{{ $factoryLogo['height'] ?: '' }}" alt="{{ $factoryLogo['alt'] }}">
            @else
                <span>Emerald Rozalia Limited</span>
            @endif
        </a>
        <nav aria-label="Emerald Rozalia navigation">
            <a href="/">Home</a>
            <a href="/shop">Shop</a>
            <a href="/collections">Collections</a>
            <a href="/new-arrivals">New Arrivals</a>
            <a href="/corporate-orders">Corporate Order</a>
            <a href="/bulk-orders">Bulk Order</a>
            <a href="/contact">Contact Us</a>
        </nav>
    </header>

    <section class="factory-hero" aria-labelledby="factory-page-title">
        <div class="factory-hero-copy">
            <p class="factory-eyebrow">IRISH MADE. LIMERICK BORN.</p>
            <h1 id="factory-page-title">HOW WE <em>WORK</em></h1>
            <p class="factory-hero-intro">Precision. Passion. Tradition. Every Emerald Rozalia hat and cap is developed and finished with care in our Limerick manufacturing workflow.</p>
            <div class="factory-hero-actions">
                <a class="factory-button" href="/contact">BOOK A FACTORY VISIT</a>
                <a class="factory-button factory-button--ghost" href="/collections">EXPLORE OUR COLLECTIONS</a>
            </div>
        </div>
        <div class="factory-hero-media" data-public-media-state="awaiting-approved-media" role="img" aria-label="Approved manufacturing photography is not configured">
            <span>Approved manufacturing photography is not configured.</span>
        </div>
    </section>

    <section class="factory-process" aria-labelledby="factory-process-title">
        <div class="factory-section-heading">
            <p class="factory-eyebrow">FROM CONCEPT TO CREATION</p>
            <h2 id="factory-process-title">A clear process, made to last.</h2>
            <p>Our team combines Irish craft knowledge with disciplined production and quality controls at every stage.</p>
        </div>
        <ol class="factory-step-grid">
            @foreach($factorySteps as $index => [$title, $copy])
                <li>
                    <span class="factory-step-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <div><h3>{{ $title }}</h3><p>{{ $copy }}</p></div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="factory-visit" aria-labelledby="factory-visit-title">
        <div>
            <p class="factory-eyebrow">WELCOME TO VISIT OUR FACTORY</p>
            <h2 id="factory-visit-title">See the craft behind the finished piece.</h2>
            <p>Partners, clients and friends are welcome to arrange a factory visit in Limerick. Contact our team to discuss a suitable time.</p>
        </div>
        <a class="factory-button" href="/contact">ARRANGE A VISIT <x-icon name="arrow-right" size="18" /></a>
    </section>

    <footer class="factory-page-footer">
        <span>Emerald Rozalia Limited · Designed &amp; manufactured in Limerick, Ireland.</span>
        <nav aria-label="Factory page footer links">
            <a href="/contact">Contact Us</a>
            <a href="/careers">Careers</a>
            <a href="/franchise">Franchise</a>
            <a href="/global-network">Global Network</a>
        </nav>
    </footer>
</div>
@endsection
