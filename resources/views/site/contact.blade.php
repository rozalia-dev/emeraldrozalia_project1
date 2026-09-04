@extends('layouts.site')

@section('title', 'Contact Us — Emerald Rozalia')

@php
    $contactMonth = now()->startOfMonth();
    $contactToday = now()->startOfDay();
    $contactWeekdays = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
    $contactLeadingDays = $contactMonth->dayOfWeekIso - 1;
@endphp

@section('content')
<div class="contact-page">
    <nav class="contact-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <x-icon name="chevron-right" size="13" />
        <strong aria-current="page">Contact Us</strong>
    </nav>

    <section class="contact-hero" aria-labelledby="contact-hero-title">
        <div class="contact-hero-copy">
            <p class="eyebrow">CONTACT US</p>
            <h1 id="contact-hero-title">WE'RE HERE<br><em>TO HELP</em></h1>
            <span class="contact-rule" aria-hidden="true"><x-icon name="clover" size="19" /></span>
            <p>Have a question about our hats and caps, an order or a partnership opportunity? Our team in Limerick is ready to assist you.</p>
        </div>
        <figure class="contact-hero-art">
            <img src="{{ asset('assets/brand/contact-hero-reference.png') }}?v=20260904" width="694" height="307" alt="Emerald Rozalia storefront in Limerick" loading="eager" fetchpriority="high">
        </figure>
    </section>

    <section class="contact-options" aria-label="Contact options">
        <a class="contact-option" href="https://wa.me/353899788187" target="_blank" rel="noopener" aria-label="Chat with Emerald Rozalia on WhatsApp">
            <span class="contact-option-icon"><x-icon name="phone" size="27" /></span>
            <span><strong>WHATSAPP</strong><b>0899788187</b><small>Chat with us on WhatsApp</small></span>
        </a>
        <a class="contact-option" href="mailto:urmos@rozalia.ie" aria-label="Email Emerald Rozalia">
            <span class="contact-option-icon"><x-icon name="mail" size="27" /></span>
            <span><strong>EMAIL US</strong><b>urmos@rozalia.ie</b><small>We aim to reply within 24 hours</small></span>
        </a>
        <a class="contact-option" href="https://emeraldrozalia.ie" target="_blank" rel="noopener" aria-label="Visit the Emerald Rozalia website">
            <span class="contact-option-icon"><x-icon name="globe" size="27" /></span>
            <span><strong>WEBSITE</strong><b>emeraldrozalia.ie</b><small>Visit our online shop</small></span>
        </a>
        <a class="contact-option" href="#contact-schedule" aria-label="Choose a live chat or meeting time">
            <span class="contact-option-icon"><x-icon name="message" size="27" /></span>
            <span><strong>LIVE CHAT</strong><b>Chat with our team</b><small>Mon–Fri · 9:00am–5:30pm (Irish Time)</small></span>
        </a>
    </section>

    <section class="contact-layout" aria-label="Message and scheduling">
        <article class="contact-panel contact-form-panel">
            <p class="contact-section-kicker">GET IN TOUCH</p>
            <h2>SEND US A MESSAGE</h2>
            <p class="contact-panel-intro">Fill out the form below and we’ll get back to you as soon as possible.</p>
            <form id="contact-form" class="contact-form" method="post" action="{{ route('inquiry') }}">
                @csrf
                <input type="hidden" name="type" value="contact">
                <input type="hidden" name="meeting_date" data-schedule-date-input>
                <input type="hidden" name="meeting_time" data-schedule-time-input>
                <div class="contact-form-fields">
                    <div class="contact-field">
                        <label for="contact-name">Full Name <span aria-hidden="true">*</span></label>
                        <input id="contact-name" name="name" value="{{ old('name') }}" placeholder="Full Name" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
                    </div>
                    <div class="contact-field">
                        <label for="contact-email">Email Address <span aria-hidden="true">*</span></label>
                        <input id="contact-email" name="email" type="email" value="{{ old('email') }}" placeholder="Email Address" autocomplete="email" required @error('email') aria-invalid="true" @enderror>
                    </div>
                    <div class="contact-field">
                        <label for="contact-phone">Phone Number</label>
                        <input id="contact-phone" name="phone" value="{{ old('phone') }}" placeholder="Phone Number" autocomplete="tel">
                    </div>
                    <div class="contact-field">
                        <label for="contact-subject">Subject <span aria-hidden="true">*</span></label>
                        <select id="contact-subject" name="subject" required @error('subject') aria-invalid="true" @enderror>
                            <option value="">Subject</option>
                            <option value="Order support" @selected(old('subject')==='Order support')>Order support</option>
                            <option value="Product question" @selected(old('subject')==='Product question')>Product question</option>
                            <option value="Wholesale or corporate enquiry" @selected(old('subject')==='Wholesale or corporate enquiry')>Wholesale or corporate enquiry</option>
                            <option value="Factory visit" @selected(old('subject')==='Factory visit')>Factory visit</option>
                        </select>
                    </div>
                    <div class="contact-field contact-field-wide">
                        <label for="contact-message">Your Message <span aria-hidden="true">*</span></label>
                        <textarea id="contact-message" name="message" placeholder="Your Message" rows="6" required @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                    </div>
                </div>
                <label class="contact-consent"><input type="checkbox" name="consent" value="1" required> <span>I agree to the <a href="/factory#privacy-policy">Privacy Policy</a> and <a href="/factory#terms">Terms &amp; Conditions</a>.</span></label>
                <p class="contact-form-schedule" data-schedule-form-summary hidden></p>
                <button class="btn contact-submit" type="submit">SEND MESSAGE <x-icon name="arrow-right" size="18" /></button>
            </form>
        </article>

        <section class="contact-panel contact-schedule" id="contact-schedule" data-contact-scheduler data-contact-month="{{ $contactMonth->format('Y-m') }}" data-contact-today="{{ $contactToday->format('Y-m-d') }}" aria-labelledby="schedule-title">
            <p class="contact-section-kicker">BOOK A CONVERSATION</p>
            <h2 id="schedule-title">PICK A TIME THAT WORKS FOR YOU</h2>
            <p class="contact-panel-intro">Schedule a meeting with our team to discuss your needs.</p>
            <div class="contact-schedule-points">
                <div><span><x-icon name="users" size="21" /></span><p><b>One-to-one consultation</b><small>Talk to our hat experts</small></p></div>
                <div><span><x-icon name="settings" size="21" /></span><p><b>Custom solutions</b><small>For your business or needs</small></p></div>
                <div><span><x-icon name="clock" size="21" /></span><p><b>Quick &amp; easy</b><small>Pick a time that suits you</small></p></div>
            </div>
            <div class="contact-calendar" aria-label="Choose a meeting date">
                <div class="contact-calendar-heading">
                    <button type="button" data-schedule-prev aria-label="Previous month"><x-icon name="chevron-right" size="17" /></button>
                    <strong data-schedule-month-label>{{ $contactMonth->format('F Y') }}</strong>
                    <button type="button" data-schedule-next aria-label="Next month"><x-icon name="chevron-right" size="17" /></button>
                </div>
                <div class="contact-weekdays" aria-hidden="true">
                    @foreach($contactWeekdays as $weekday)<span>{{ $weekday }}</span>@endforeach
                </div>
                <div class="contact-calendar-grid" data-schedule-days>
                    @for($blank = 0; $blank < $contactLeadingDays; $blank++)<span class="contact-date-spacer" aria-hidden="true"></span>@endfor
                    @for($day = 1; $day <= $contactMonth->daysInMonth; $day++)
                        @php($date = $contactMonth->copy()->day($day))
                        <button type="button" class="contact-date-button" data-schedule-date="{{ $date->format('Y-m-d') }}" @disabled($date->lt($contactToday) || $date->isWeekend()) aria-label="{{ $date->format('l j F Y') }}">{{ $day }}</button>
                    @endfor
                </div>
            </div>
            <div class="contact-time-picker">
                <div class="contact-time-heading"><strong>SELECT TIME</strong><small>(Irish Time)</small></div>
                <div class="contact-time-options" role="group" aria-label="Choose a meeting time">
                    @foreach(['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'] as $time)
                        <button type="button" data-schedule-time="{{ $time }}" aria-pressed="false">{{ $time }}</button>
                    @endforeach
                </div>
            </div>
            <p class="contact-schedule-summary" data-schedule-summary role="status" aria-live="polite">Choose a date and time, then add it to your message.</p>
            <button class="btn contact-schedule-apply" type="button" data-schedule-apply>SCHEDULE MEETING <x-icon name="calendar" size="17" /></button>
        </section>
    </section>

    <section class="contact-info-grid" aria-label="Emerald Rozalia information">
        <article class="contact-info-card contact-location-card">
            <p class="contact-section-kicker">VISIT OUR HOME</p>
            <h2>WE’RE BASED IN LIMERICK</h2>
            <figure>
                <img src="{{ asset('assets/brand/contact-location-reference.png') }}?v=20260904" width="399" height="176" alt="Limerick, Ireland, home of Emerald Rozalia manufacturing">
            </figure>
            <p>Our manufacturing and support team is based in Limerick, Ireland.</p>
        </article>
        <article class="contact-info-card">
            <span class="contact-info-icon"><x-icon name="clock" size="25" /></span>
            <h2>OFFICE HOURS</h2>
            <p><b>Monday – Friday</b><br>9:00am – 5:30pm (Irish Time)</p>
            <p><b>Saturday – Sunday</b><br>Closed</p>
            <div class="contact-card-divider"></div>
            <p><x-icon name="clover" size="22" /> Emerald Rozalia Limited<br><small>Proudly based in Limerick, Ireland</small></p>
        </article>
        <article class="contact-info-card" id="connect-with-us">
            <h2>CONNECT WITH US</h2>
            <p>Follow us for new arrivals, behind the scenes and Irish made stories.</p>
            <div class="contact-social-links" aria-label="Social networks">
                <a href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook"><x-icon name="facebook" size="20" /></a>
                <a href="https://www.instagram.com/" target="_blank" rel="noopener" aria-label="Instagram"><x-icon name="instagram" size="20" /></a>
                <a href="https://www.tiktok.com/" target="_blank" rel="noopener" aria-label="TikTok"><x-icon name="music" size="20" /></a>
                <a href="https://www.youtube.com/" target="_blank" rel="noopener" aria-label="YouTube"><x-icon name="youtube" size="20" /></a>
                <a href="https://www.linkedin.com/" target="_blank" rel="noopener" aria-label="LinkedIn"><x-icon name="linkedin" size="20" /></a>
            </div>
            <blockquote>“From Limerick to the world. Irish made. Irish proud.”</blockquote>
        </article>
    </section>

    <section class="contact-faq" aria-labelledby="contact-faq-title">
        <p class="contact-section-kicker">NEED TO KNOW</p>
        <h2 id="contact-faq-title">FREQUENTLY ASKED QUESTIONS</h2>
        <div class="contact-faq-grid">
            <details><summary>How long does delivery take?</summary><p>Delivery timing depends on the product and destination. We’ll confirm the expected delivery date with your order.</p></details>
            <details><summary>Can I return or exchange an item?</summary><p>Yes. Contact our team with your order details and we’ll guide you through the available return or exchange options.</p></details>
            <details><summary>Do you offer bulk or corporate orders?</summary><p>Yes. Visit our Corporate Order or Bulk Order page and send us your requirements for a tailored response.</p></details>
            <details><summary>Do you ship internationally?</summary><p>We deliver worldwide. Delivery options and costs are shown for your destination during the order process.</p></details>
            <details><summary>How do I track my order?</summary><p>Once your order has been dispatched, our team will share the available delivery tracking details.</p></details>
            <details><summary>Do you have a physical store?</summary><p>Our team is proudly based in Limerick. Contact us to arrange a factory visit or discuss a retail partnership.</p></details>
        </div>
    </section>

    <section class="contact-benefits" aria-label="Emerald Rozalia benefits">
        <div><x-icon name="clover" size="28" /><span><b>IRISH MADE</b><small>Proudly made in Limerick</small></span></div>
        <div><x-icon name="star" size="28" /><span><b>PREMIUM QUALITY</b><small>Finest materials, built to last</small></span></div>
        <div><x-icon name="truck" size="28" /><span><b>FAST DISPATCH</b><small>Worldwide delivery</small></span></div>
        <div><x-icon name="refresh" size="28" /><span><b>EASY RETURNS</b><small>30-day returns</small></span></div>
        <div><x-icon name="credit-card" size="28" /><span><b>SECURE PAYMENT</b><small>100% secure checkout</small></span></div>
    </section>
</div>
@endsection
