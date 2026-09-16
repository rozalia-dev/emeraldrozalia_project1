@extends('layouts.site')
@section('title','Franchise Requirements & Document Checklist — Emerald Rozalia')
@push('styles')
<style>
.frreq{max-width:1100px;margin:0 auto;padding:48px 22px 70px;color:#173125}.frreq-hero{padding:38px;border-radius:20px;background:#082a1d;color:#fff}.frreq-hero p{max-width:760px;color:#d8e9df;line-height:1.7}.frreq-hero h1{margin:0 0 12px;font-size:clamp(30px,5vw,52px)}.frreq-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:22px}.frreq-card{border:1px solid #dbe6df;border-radius:16px;padding:24px;background:#fff}.frreq-card h2{margin-top:0;color:#0b6a45}.frreq-card li{margin:10px 0;line-height:1.55}.frreq-note{margin-top:20px;padding:18px;border-radius:14px;background:#f4f8f5;border-left:4px solid #7fbd42;line-height:1.6}.frreq-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}.frreq-actions a,.frreq-actions button{border:0;border-radius:10px;padding:12px 16px;font-weight:800;text-decoration:none;cursor:pointer}.frreq-actions a{background:#0b6a45;color:#fff}.frreq-actions .secondary,.frreq-actions button{background:#eef3ef;color:#244535}.frreq-process{counter-reset:step}.frreq-process li{list-style:none;position:relative;padding-left:40px}.frreq-process li:before{counter-increment:step;content:counter(step);position:absolute;left:0;top:-2px;width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:#0b6a45;color:#fff;font-weight:800}.frreq-small{color:#66766c;font-size:13px}@media(max-width:760px){.frreq-grid{grid-template-columns:1fr}.frreq-hero{padding:26px}.frreq{padding:28px 14px 52px}}@media print{.frreq-actions,.site-header,.site-footer,.chat24{display:none!important}.frreq{max-width:none;padding:0}.frreq-hero{color:#000;background:#fff;border:1px solid #ccc}.frreq-hero p{color:#333}}
</style>
@endpush
@section('content')
<main class="frreq">
    <section class="frreq-hero">
        <p><strong>EMERALD ROZALIA LIMITED</strong></p>
        <h1>Franchise Requirements &amp; Document Checklist</h1>
        <p>This page explains the information needed for an initial franchise or franchise retail-store application and the supporting documents that may be requested during verification. Exact requirements can vary by applicant and territory; the franchise team will confirm them before requesting sensitive documents.</p>
    </section>

    <div class="frreq-grid">
        <section class="frreq-card">
            <h2>Initial application information</h2>
            <ul>
                <li>Applicant full name and contact details.</li>
                <li>Preferred city, region or proposed retail-store territory.</li>
                <li>Business or retail experience and relevant background.</li>
                <li>Expected opening timeline and business objectives.</li>
                <li>Whether the enquiry is for a franchise opportunity or a franchise retail store.</li>
            </ul>
        </section>

        <section class="frreq-card">
            <h2>Supporting documents that may be requested</h2>
            <ul>
                <li>Proof of identity for applicant verification.</li>
                <li>Proof of address where appropriate.</li>
                <li>Company registration details when applying through a business entity.</li>
                <li>Information supporting financial capacity or proposed investment, when required for assessment.</li>
                <li>Proposed location, store or territory information when available.</li>
            </ul>
            <p class="frreq-small">Do not send sensitive documents through public chat. Wait until the franchise team confirms the secure submission method and the exact documents required for your application.</p>
        </section>

        <section class="frreq-card">
            <h2>Application process</h2>
            <ol class="frreq-process">
                <li>Submit the online franchise or retail-store enquiry.</li>
                <li>Franchise team reviews the initial application.</li>
                <li>Book a meeting to discuss territory, store format and business expectations.</li>
                <li>Provide any additional verification documents requested by the team.</li>
                <li>Proceed to commercial review, agreement and onboarding when approved.</li>
            </ol>
        </section>

        <section class="frreq-card">
            <h2>Before you apply</h2>
            <ul>
                <li>Choose the preferred location or territory you are interested in.</li>
                <li>Prepare a short summary of your business/retail experience.</li>
                <li>Consider your intended opening timeframe.</li>
                <li>Use the appointment calendar if you want to discuss the opportunity before submitting.</li>
            </ul>
        </section>
    </div>

    <div class="frreq-note">
        <strong>Privacy &amp; verification:</strong> this checklist is an initial guide, not a request to upload sensitive documentation publicly. The Emerald Rozalia franchise team confirms the exact verification requirements and secure submission method after reviewing the application.
    </div>

    <div class="frreq-actions">
        <a href="{{ route('franchise') }}#franchise-enquiry">Apply for Franchise</a>
        <a href="{{ route('store.owner') }}#franchise-enquiry">Apply for Franchise Retail Store</a>
        <a href="{{ route('contact') }}#contact-schedule">Book Appointment</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
</main>
@endsection
