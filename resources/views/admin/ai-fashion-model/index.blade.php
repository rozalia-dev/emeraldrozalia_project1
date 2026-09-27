@extends('layouts.admin')
@section('title','AI Fashion Model')
@section('content')
<div class="sd">
<header class="sd-heading"><div><p class="sd-breadcrumb">Website &amp; Products › Product Media Manager › AI Fashion Model</p><h1>AI Fashion Model Studio</h1><p>Create merchandising images using the selected catalogue product as the reference. Generated images must be reviewed before publishing.</p></div></header>
@if($errors->any())<div class="sd-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="sd-layout"><main>
<section class="sd-card">
<h2>Generate Fashion Model</h2>
<form data-fashion-form method="post" action="{{ route('admin.ai-fashion-model.generate') }}">@csrf
<div class="sd-core-fields">
<label>Product / SKU<select name="product_id" required><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id')==$product->id)>{{ $product->name }} · {{ $product->sku }}</option>@endforeach</select></label>
<label>Fashion Model<select name="model"><option value="female">Female</option><option value="male">Male</option><option value="unisex">Unisex</option><option value="kids">Kids</option></select></label>
<label>Pose<select name="pose"><option value="front">Front</option><option value="left">Left Side</option><option value="right">Right Side</option><option value="back">Back</option><option value="portrait">Portrait</option><option value="full_body">Full Body</option></select></label>
<label>Scene<select name="scene"><option value="studio">Premium Studio</option><option value="luxury">Luxury Fashion</option><option value="irish_heritage">Irish Heritage</option><option value="runway">Runway</option><option value="outdoor">Outdoor</option></select></label>
</div>
<label>Creative Direction<textarea name="notes" maxlength="800" placeholder="Optional styling instructions. Product identity and branding remain locked to the selected reference."></textarea></label>
<div style="margin:14px 0;padding:14px;border:1px solid #d9c38c;border-radius:10px"><strong>PRODUCT PRESERVATION</strong><p style="margin:6px 0 0">The selected catalogue image is sent as the product reference. Generation instructions require preservation of shape, colour, embroidery, logo and branding. Every result must be reviewed before it is published.</p></div>
<button class="sd-button" type="submit">GENERATE AI FASHION MODEL</button> <span data-fashion-status role="status"></span>
</form>
</section>
<section class="sd-card"><h2>Publishing Workflow</h2><p><strong>Generate → Preview → Review → Approve → Publish to Product Gallery.</strong></p><p>Generation and publishing remain separate so an AI result cannot automatically replace or misrepresent an approved product image.</p></section>
</main>
<aside class="sd-sidebar"><section class="sd-card"><h2>Reference Product</h2>@if($selected)<strong>{{ $selected->name }}</strong><small>SKU {{ $selected->sku }}</small>@else<p>Select a catalogue product to begin.</p>@endif</section><section class="sd-card"><h2>Recommended Views</h2><p>Front · Left · Right · Back · Portrait · Full Body</p></section></aside></div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const f=document.querySelector('[data-fashion-form]'),s=document.querySelector('[data-fashion-status]');if(!f)return;f.addEventListener('submit',async e=>{e.preventDefault();const b=f.querySelector('button[type=submit]');b.disabled=true;s.textContent='Generating…';try{const r=await fetch(f.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':f.querySelector('[name=_token]').value},body:new FormData(f)}),d=await r.json();if(!r.ok)throw new Error(d.message||'Generation failed.');s.textContent=d.message;}catch(x){s.textContent=x.message;}finally{b.disabled=false;}});});
</script>
@endsection
