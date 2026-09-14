@extends('layouts.site')
@section('body-class','quality-page')
@section('title','Quality in Every Stitch — Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/quality.css?v=20260914-public-media-contract">
@endpush
@section('content')
<div class="quality-page-frame" data-public-media-register="quality" data-public-media-state="awaiting-approved-media">
    <section class="quality-hero" aria-labelledby="quality-title">
        <div class="quality-hero-copy">
            <p class="quality-eyebrow">QUALITY IN EVERY STITCH.</p>
            <h1 id="quality-title">MADE TO <em>LAST.</em></h1>
            <p>From material selection to final inspection, our Limerick team keeps quality at the centre of every Emerald Rozalia hat and cap.</p>
            <div class="quality-actions">
                <a class="quality-button" href="/collections">EXPLORE COLLECTIONS <x-icon name="arrow-right" size="17" /></a>
                <a class="quality-button quality-button--ghost" href="/factory">SEE HOW WE WORK</a>
            </div>
        </div>
        <div class="quality-media-state" data-public-media-state="awaiting-approved-media" role="img" aria-label="Approved quality and process photography is not configured">
            <span>Approved quality photography is not configured.</span>
        </div>
    </section>

    <section class="quality-principles" aria-labelledby="quality-principles-title">
        <div class="quality-section-heading">
            <p class="quality-eyebrow">OUR STANDARD</p>
            <h2 id="quality-principles-title">Care you can see and feel.</h2>
            <p>We combine Irish manufacturing experience with a clear, repeatable process so the finished piece is ready for everyday wear.</p>
        </div>
        <div class="quality-principle-grid">
            <article><span class="quality-index">01</span><h3>CONSIDERED MATERIALS</h3><p>We choose materials for comfort, character and dependable wear.</p></article>
            <article><span class="quality-index">02</span><h3>SKILLED CRAFT</h3><p>Shaping, sewing and finishing are handled with attention to the agreed design.</p></article>
            <article><span class="quality-index">03</span><h3>FINAL CHECKS</h3><p>Products are checked for finish, fit, presentation and order readiness.</p></article>
        </div>
    </section>

    <section class="quality-checklist" aria-labelledby="quality-checklist-title">
        <div>
            <p class="quality-eyebrow">FROM CONCEPT TO CREATION</p>
            <h2 id="quality-checklist-title">A dependable path from brief to delivery.</h2>
        </div>
        <ol>
            <li><span>DESIGN</span><p>Agree the intended shape, materials and details.</p></li>
            <li><span>MAKE</span><p>Build the piece through our Limerick manufacturing workflow.</p></li>
            <li><span>CHECK</span><p>Review quality and presentation before dispatch.</p></li>
            <li><span>DELIVER</span><p>Prepare the approved order for reliable delivery.</p></li>
        </ol>
    </section>

    <section class="quality-cta" aria-labelledby="quality-cta-title">
        <div><p class="quality-eyebrow">IRISH MADE. LIMERICK BORN.</p><h2 id="quality-cta-title">Want to discuss a custom order?</h2><p>Our team can talk through materials, branding, quantities and delivery requirements.</p></div>
        <a class="quality-button" href="/corporate-orders">START A CONVERSATION <x-icon name="arrow-right" size="17" /></a>
    </section>
</div>
@endsection
