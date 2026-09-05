@extends('layouts.site')

@php
    $titles = ['collections'=>'Our Collections','new-arrivals'=>'New Arrivals','corporate-orders'=>'Corporate Orders','bulk-orders'=>'Bulk Order Solutions','franchise'=>'Franchise Retail Store','careers'=>'Build Your Career With Emerald Rozalia','global-network'=>'Our Global Network','factory'=>'How We Work - Inside Our Factory','contact'=>'Contact Us','virtual-tryon'=>'Virtual Try-On Studio','irish-traditional'=>'Irish Traditional Flat Hats','irish-heritage'=>'Irish Heritage Hats'];
    $pageTitle = $managedPage?->title ?: ($titles[$page] ?? str($page)->headline());
    $hasManagedContent = $managedPage && ($managedPage->body || $managedPage->sections->isNotEmpty());
@endphp

@section('title', $pageTitle . ' - Emerald Rozalia')

@section('content')
<section class="page-hero"><p class="eyebrow">EMERALD ROZALIA LIMITED</p><h1>{{ $pageTitle }}</h1><p>{{ $managedPage?->intro ?: 'Timeless styles. Irish heritage. Made in Limerick.' }}</p></section>

@if($hasManagedContent)
    <section class="section managed-page-content">
        @if($managedPage->body)<div class="managed-page-body">{!! nl2br(e($managedPage->body)) !!}</div>@endif
        @foreach($managedPage->sections as $section)
            @if($section->visible)
                <article class="managed-page-section managed-page-section--{{ $section->type }}"><h2>{{ $section->label ?: str($section->type)->headline() }}</h2><p>{!! nl2br(e(data_get($section->settings, 'content', 'Section content is ready to be edited in the Page Manager.'))) !!}</p></article>
            @endif
        @endforeach
    </section>
@elseif($page === 'virtual-tryon')
    <section class="try-studio"><div class="try-controls"><h3>1. Upload or Take Photo</h3><input type="file" accept="image/*" data-face-upload><h3>2. Adjust Fit</h3><label>Size <input type="range" min="30" max="160" value="85" data-hat-size></label><label>Position X <input type="range" min="0" max="100" value="50" data-hat-x></label><label>Position Y <input type="range" min="0" max="100" value="15" data-hat-y></label></div><div class="try-canvas" data-try-canvas><img data-face-preview alt="Your uploaded photo"><img data-hat-overlay hidden alt="Selected hat overlay"></div><div class="try-note"><b>Implementation note:</b> approved transparent product overlays are selected from product media and remain in-browser with the customer photo.</div></section>
@elseif(in_array($page, ['contact','corporate-orders','bulk-orders','franchise','careers']))
    <section class="form-section"><div><h2>@if($page==='careers')APPLY NOW @elseif($page==='franchise')FRANCHISE ENQUIRY @elseif($page==='contact')SEND US A MESSAGE @else REQUEST A QUOTE @endif</h2><p>Your submission is stored securely and routed through the unified Communication Centre for assignment, follow-up and audit history.</p></div><form method="post" action="{{ route('inquiry') }}">@csrf<input type="hidden" name="type" value="{{ $page }}"><input name="name" placeholder="Full Name" required><input type="email" name="email" placeholder="Email Address" required><input name="phone" placeholder="Phone Number"><input name="company" placeholder="Company / Club"><textarea name="message" placeholder="Tell us how we can help"></textarea><button class="btn">SUBMIT</button></form></section>
@elseif($page === 'factory')
    <section class="process-grid">@foreach(['Design & Development','Pattern Making & Cutting','Shaping & Forming','Embroidery & Details','Programmable Sewing & Assembly','Digital Quality Inspection','Finishing','Packing & Traceability','Ready to Deliver'] as $i=>$step)<article><span>{{ $i + 1 }}</span><h3>{{ $step }}</h3><p>Managed with precision, quality control and traceability in our Limerick manufacturing workflow.</p></article>@endforeach</section><section class="factory-visit"><h2>WELCOME TO VISIT OUR FACTORY</h2><p>Partners, clients and friends are welcome to arrange a factory visit in Limerick.</p><a class="btn" href="/contact">BOOK A FACTORY VISIT</a></section>
@elseif($page === 'global-network')
    <section class="globe"><div class="hologlobe"><span class="hq-dot">LIMERICK<br><small>GLOBAL HEADQUARTERS</small></span></div><div><h2>A GLOBAL PRESENCE, CONNECTED FROM LIMERICK.</h2><p>Use this page to present verified distributors, retail partners and territories. The system intentionally does not invent partner counts or countries.</p></div></section>
@else
    <section class="section"><div class="cards">@foreach(['Irish Traditional Flat Caps','Irish Heritage Hats','Baseball Caps','Bucket Hats','Snapbacks','Beanie Hats','GAA Baseball Caps','GAA Bucket Hats','GAA Beanie Hats'] as $collection)<a class="collection-card" href="/shop"><div class="placeholder-hat"><x-icon name="package" size="48" /></div><h3>{{ $collection }}</h3><span>EXPLORE <x-icon name="arrow-right" /></span></a>@endforeach</div></section>
@endif
@endsection
