@extends('layouts.site')
@section('body-class','careers-reference-page')
@section('title','Careers — Build Your Career with Emerald Rozalia')
@push('styles')
<link rel="stylesheet" href="/css/careers.css?v=20260909-approved-reference">
@endpush
@section('content')
<div class="careers-reference" data-approved-reference="build career with us.png">
    <section class="career-hero" aria-labelledby="career-title">
        <div class="career-hero-photo">
            <img src="{{ asset('assets/brand/careers-reference.png') }}" alt="Emerald Rozalia team and workplace">
        </div>
        <div class="career-hero-copy">
            <p class="career-kicker">CAREER WITH US</p>
            <h1 id="career-title">BUILD YOUR<br>CAREER WITH<br><em>EMERALD ROZALIA</em></h1>
            <p>Join a growing Irish brand connecting craftsmanship, creativity, retail and global opportunity.</p>
            <div class="career-hero-actions">
                <a href="#open-positions" class="career-btn">VIEW OPEN POSITIONS <x-icon name="arrow-right" size="18" /></a>
                <a href="#career-form" class="career-btn career-btn--ghost">JOIN OUR TEAM</a>
            </div>
        </div>
    </section>

    <section class="career-value-strip" aria-label="Why work with Emerald Rozalia">
        <article><x-icon name="clover" size="31" /><div><strong>IRISH HERITAGE</strong><span>Be part of a proud Irish brand.</span></div></article>
        <article><x-icon name="chart" size="31" /><div><strong>GROW WITH US</strong><span>Training, mentoring and development.</span></div></article>
        <article><x-icon name="users" size="31" /><div><strong>GREAT TEAM</strong><span>Passionate, skilled and supportive people.</span></div></article>
        <article><x-icon name="globe" size="31" /><div><strong>GLOBAL OPPORTUNITY</strong><span>Build a career with worldwide potential.</span></div></article>
    </section>

    <section class="career-openings" id="open-positions">
        <div class="career-section-head">
            <div><p>JOIN THE TEAM</p><h2>CURRENT OPEN POSITIONS</h2></div>
            <span>4 POSITIONS AVAILABLE</span>
        </div>
        <div class="career-job-grid">
            @foreach([
                ['Retail Store Manager','Limerick City Centre','Full-time','Lead our retail experience, support the team and represent Emerald Rozalia with pride.'],
                ['Sales & Customer Experience','Limerick, Ireland','Full-time','Create memorable customer experiences and help customers find the right Emerald Rozalia products.'],
                ['Digital Marketing Executive','Limerick / Hybrid','Full-time','Grow our digital presence through content, campaigns, ecommerce and community engagement.'],
                ['Warehouse & Fulfilment Assistant','Limerick, Ireland','Full-time','Support accurate order fulfilment, stock movement and worldwide dispatch.'],
            ] as [$title,$location,$type,$copy])
                <article class="career-job-card">
                    <div class="career-job-icon"><x-icon name="briefcase" size="23" /></div>
                    <div class="career-job-body">
                        <h3>{{ $title }}</h3>
                        <p><x-icon name="home" size="14" /> {{ $location }}</p>
                        <p><x-icon name="clock" size="14" /> {{ $type }}</p>
                        <span>{{ $copy }}</span>
                    </div>
                    <a href="#career-form" data-position="{{ $title }}" class="career-job-apply">APPLY NOW <x-icon name="arrow-right" size="16" /></a>
                </article>
            @endforeach
        </div>
    </section>

    <section class="career-application-grid">
        <div class="career-apply-panel" id="career-form">
            <div class="career-section-head career-section-head--compact">
                <div><p>YOUR NEXT STEP</p><h2>APPLY TO JOIN EMERALD ROZALIA</h2></div>
            </div>
            <p class="career-form-intro">Tell us a little about yourself and the role you are interested in. Our team will review your application and contact suitable candidates.</p>
            <form method="post" action="{{ route('inquiry') }}" novalidate>
                @csrf
                <input type="hidden" name="type" value="careers">
                <div class="career-form-grid">
                    <label><span>Full Name</span><input name="name" value="{{ old('name') }}" placeholder="Full Name *" autocomplete="name" required></label>
                    <label><span>Email Address</span><input name="email" type="email" value="{{ old('email') }}" placeholder="Email Address *" autocomplete="email" required></label>
                    <label><span>Phone Number</span><input name="phone" value="{{ old('phone') }}" placeholder="Phone Number" autocomplete="tel"></label>
                    <label><span>Applying For</span><select name="company" id="career-position" aria-label="Applying For"><option value="">Applying For</option>@foreach(['Retail Store Manager','Sales & Customer Experience','Digital Marketing Executive','Warehouse & Fulfilment Assistant','Other / General Application'] as $position)<option value="{{ $position }}" @selected(old('company')===$position)>{{ $position }}</option>@endforeach</select></label>
                    <label class="career-form-message"><span>About You</span><textarea name="message" placeholder="Tell us about yourself, your experience or why you would like to join us (optional)">{{ old('message') }}</textarea></label>
                </div>
                <button class="career-submit" type="submit">SUBMIT APPLICATION <x-icon name="arrow-right" size="18" /></button>
            </form>
            <p class="career-form-note"><x-icon name="check" size="15" /> Your application is sent securely to our Communication Centre for review.</p>
        </div>

        <aside class="career-why-panel">
            <p class="career-kicker">WHY EMERALD ROZALIA?</p>
            <h2>MORE THAN A JOB.<br>BUILD SOMETHING<br><em>MEANINGFUL.</em></h2>
            <p>We are building an Irish brand for the world. Join a team where craftsmanship, service, creativity and ambition come together.</p>
            <div class="career-why-grid">
                <article><x-icon name="star" size="26" /><div><strong>Meaningful Work</strong><span>See the impact of what you do.</span></div></article>
                <article><x-icon name="users" size="26" /><div><strong>Supportive Team</strong><span>Work with people who care.</span></div></article>
                <article><x-icon name="chart" size="26" /><div><strong>Career Growth</strong><span>Learn, develop and progress.</span></div></article>
                <article><x-icon name="globe" size="26" /><div><strong>Global Brand</strong><span>Irish roots, worldwide ambition.</span></div></article>
            </div>
        </aside>
    </section>

    <section class="career-story-grid">
        <div class="career-story-photo career-story-photo--factory"><img src="{{ asset('assets/brand/careers-reference.png') }}" alt="Emerald Rozalia workplace and production team"></div>
        <article class="career-story-copy">
            <p class="career-kicker">MADE IN LIMERICK</p>
            <h2>WORK WITH A BRAND<br>BUILT ON <em>CRAFT.</em></h2>
            <p>From product and retail to ecommerce, customer experience and fulfilment, every role helps carry Emerald Rozalia from Limerick to customers around the world.</p>
            <ul>
                <li><x-icon name="check" size="15" /> Proud Irish manufacturing heritage</li>
                <li><x-icon name="check" size="15" /> Quality-first culture</li>
                <li><x-icon name="check" size="15" /> Customer-focused teamwork</li>
                <li><x-icon name="check" size="15" /> Opportunity to grow with the brand</li>
            </ul>
        </article>
        <div class="career-story-photo career-story-photo--team"><img src="{{ asset('assets/brand/careers-reference.png') }}" alt="Emerald Rozalia people and team culture"></div>
    </section>

    <section class="career-cta">
        <div><strong>READY TO BUILD YOUR FUTURE WITH <em>EMERALD ROZALIA?</em></strong><span>Choose an open position or send us a general application today.</span></div>
        <a href="#career-form" class="career-btn">APPLY NOW <x-icon name="arrow-right" size="18" /></a>
    </section>

    <section class="career-service-strip" aria-label="Emerald Rozalia commitments">
        <article><x-icon name="clover" size="27" /><div><strong>IRISH MADE</strong><span>Proudly made in Limerick</span></div></article>
        <article><x-icon name="star" size="27" /><div><strong>PREMIUM QUALITY</strong><span>Quality in every detail</span></div></article>
        <article><x-icon name="users" size="27" /><div><strong>PEOPLE FIRST</strong><span>Supportive team culture</span></div></article>
        <article><x-icon name="chart" size="27" /><div><strong>GROWTH</strong><span>Learn and develop with us</span></div></article>
        <article><x-icon name="globe" size="27" /><div><strong>GLOBAL REACH</strong><span>Limerick born. Worn everywhere.</span></div></article>
    </section>

    <footer class="career-footer">
        <div class="career-footer-brand"><img src="{{ asset('assets/logo/logo_two_line.png') }}" alt="Emerald Rozalia Limited"><span>© {{ date('Y') }} Emerald Rozalia Limited. All Rights Reserved.</span></div>
        <nav aria-label="Careers footer"><a href="/factory">About Us</a><a href="/contact">Contact Us</a><a href="/franchise">Franchise</a><a href="/terms-conditions">Terms &amp; Conditions</a><a href="/privacy-policy">Privacy Policy</a></nav>
        <div class="career-follow"><span>FOLLOW US</span><x-icon name="facebook" size="18" /><x-icon name="instagram" size="18" /><x-icon name="linkedin" size="18" /><x-icon name="youtube" size="18" /></div>
    </footer>
</div>
<script>
document.querySelectorAll('.career-job-apply').forEach(function(link){link.addEventListener('click',function(){var select=document.getElementById('career-position');if(select){select.value=this.dataset.position||'';}});});
</script>
@endsection
