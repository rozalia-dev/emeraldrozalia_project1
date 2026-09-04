@extends('layouts.factory-reference')
@section('title','How We Work — Emerald Rozalia')
@section('content')
<section class="factory-reference" aria-labelledby="factory-reference-title">
    <div class="factory-reference-canvas">
        <img src="{{ asset('assets/brand/how-we-work-reference.png') }}?v=20260904" width="1024" height="1536" alt="Emerald Rozalia How We Work: Irish-made Limerick manufacturing, nine craft stages, factory visit and contact details." fetchpriority="high" decoding="async">
        <a class="factory-reference-hotspot factory-reference-hotspot--home" href="/" aria-label="Emerald Rozalia home"></a>
        <a class="factory-reference-hotspot factory-reference-hotspot--made" href="/factory" aria-label="Irish made in Limerick"></a>
        <a class="factory-reference-hotspot factory-reference-hotspot--quality" href="/factory" aria-label="Premium quality craftsmanship"></a>
        <a class="factory-reference-hotspot factory-reference-hotspot--delivery" href="/global-network" aria-label="Worldwide delivery"></a>
        <a class="factory-reference-hotspot factory-reference-hotspot--visit" href="/contact" aria-label="Book a factory visit"></a>
        <a class="factory-reference-hotspot factory-reference-hotspot--factory" href="/contact" aria-label="Contact our factory"></a>
        <a class="factory-reference-hotspot factory-reference-hotspot--email" href="mailto:urmos@rozalia.ie" aria-label="Email Emerald Rozalia"></a>
        <a class="factory-reference-hotspot factory-reference-hotspot--website" href="https://emeraldrozalia.ie" aria-label="Visit emeraldrozalia.ie"></a>
    </div>
    <div class="factory-reference-screen-reader sr-only">
        <h1 id="factory-reference-title">HOW WE WORK</h1>
        <p>Precision. Passion. Tradition. Every Emerald Rozalia hat and cap is crafted in our Limerick factory.</p>
        <nav aria-label="Emerald Rozalia navigation">
            <a href="/">Home</a>
            <a href="/shop">Shop</a>
            <a href="/collections">Collections</a>
            <a href="/new-arrivals">New Arrivals</a>
            <a href="/corporate-orders">Corporate Order</a>
            <a href="/bulk-orders">Bulk Order</a>
            <a href="/franchise">Franchise Apply</a>
            <a href="/careers">Hiring Apply</a>
            <a href="/contact">Contact Us</a>
        </nav>
        <h2>FROM CONCEPT TO CREATION</h2>
        <ol>
            <li>Design &amp; Development</li>
            <li>Pattern Making &amp; Cutting</li>
            <li>Shaping &amp; Steaming</li>
            <li>Embroidery &amp; Details</li>
            <li>Sewing &amp; Assembly</li>
            <li>Quality Inspection</li>
            <li>Finishing &amp; Steam</li>
            <li>Packing &amp; Labelling</li>
            <li>Ready to Deliver</li>
        </ol>
        <h2>WELCOME TO VISIT OUR FACTORY</h2>
        <p>Partners, clients and friends are welcome to arrange a factory visit in Limerick.</p>
    </div>
</section>
@endsection
