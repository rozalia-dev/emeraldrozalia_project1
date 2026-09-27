@extends('layouts.admin')
@section('title','AI Fashion Model')
@section('content')
<div class="sd">
<header class="sd-heading"><div><p class="sd-breadcrumb">Website &amp; Products › Product Media Manager › AI Fashion Model</p><h1>AI Fashion Model Studio</h1><p>Generate with FASHN Product to Model, review the saved result, approve it, then publish it to the selected product gallery.</p></div></header>
@if(session('status'))<div class="sd-card" style="margin-bottom:16px"><strong>{{ session('status') }}</strong></div>@endif
@if($errors->any())<div class="sd-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="sd-layout"><main>
<section class="sd-card">
<h2>Generate Fashion Model</h2>
<form data-fashion-form method="post" action="{{ route('admin.ai-fashion-model.generate') }}">@csrf
<div class="sd-core-fields">
<label>Product / SKU<select name="product_id" required><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id')==$product->id)>{{ $product->name }} · {{ $product->sku }}</option>@endforeach</select></label>
<label>Fashion Model<select name="model"><option value="female">Female</option><option value="male">Male</option><option value="unisex">Unisex</option><option value="kids">Kids</option></select></label>
<label>Pose / View<select name="pose"><option value="front">Front</option><option value="left">Left Side</option><option value="right">Right Side</option><option value="back">Back</option><option value="rear_three_quarter">Rear 3/4</option><option value="portrait">Portrait</option><option value="full_body">Full Body</option><option value="walking">Walking / Wearing Product</option></select></label>
<label>Scene<select name="scene"><option value="studio">Premium Studio</option><option value="luxury">Luxury Fashion</option><option value="irish_heritage">Irish Heritage</option><option value="runway">Runway</option><option value="outdoor">Outdoor</option></select></label>
</div>
<label>Creative Direction<textarea name="notes" maxlength="800" placeholder="Optional styling instructions. Product identity and branding remain locked to the selected reference."></textarea></label>
<div style="margin:14px 0;padding:14px;border:1px solid #d9c38c;border-radius:10px"><strong>PRODUCT PRESERVATION</strong><p style="margin:6px 0 0">The selected catalogue image is sent to FASHN as the product reference. AI output must be reviewed for product, logo and embroidery accuracy before approval.</p></div>
<button class="sd-button" type="submit">GENERATE WITH FASHN</button> <span data-fashion-status role="status"></span>
</form>
<div data-live-preview style="display:none;margin-top:18px"><h3>Generated Preview</h3><img data-live-image alt="Generated AI fashion model preview" style="max-width:100%;max-height:640px;object-fit:contain;border-radius:12px"></div>
</section>

<section class="sd-card">
<h2>Generation Review Queue</h2>
@if($generations->isEmpty())<p>No generations yet.</p>@else
<div style="display:grid;gap:16px">
@foreach($generations as $generation)
<article style="display:grid;grid-template-columns:minmax(120px,220px) minmax(0,1fr);gap:16px;border:1px solid #ddd;border-radius:12px;padding:14px">
<div>@if($generation->result_path)<img src="{{ Storage::disk($generation->result_disk ?: 'public')->url($generation->result_path) }}" alt="AI fashion model preview" style="width:100%;max-height:260px;object-fit:contain;border-radius:10px">@else<div style="min-height:120px;display:grid;place-items:center;background:#f4f4f4;border-radius:10px">Awaiting image</div>@endif</div>
<div style="min-width:0"><strong>{{ $generation->product?->name ?? 'Product unavailable' }}</strong><p>FASHN · {{ str_replace('_',' ',$generation->pose) }} · {{ str_replace('_',' ',$generation->scene) }}</p><p>Status: <strong>{{ strtoupper($generation->status) }}</strong></p>
@if($generation->provider_error)<p style="overflow-wrap:anywhere">{{ $generation->provider_error }}</p>@endif
@if($generation->status === 'completed')
<form method="post" action="{{ route('admin.ai-fashion-model.approve',$generation) }}">@csrf<button class="sd-button" type="submit">APPROVE</button></form>
@elseif($generation->status === 'approved')
<form method="post" action="{{ route('admin.ai-fashion-model.publish',$generation) }}">@csrf<button class="sd-button" type="submit">PUBLISH TO PRODUCT GALLERY</button></form>
@elseif($generation->status === 'published')
<p><strong>Published to product gallery.</strong></p>
@endif
</div></article>
@endforeach
</div>
@endif
</section>
</main>
<aside class="sd-sidebar"><section class="sd-card"><h2>Reference Product</h2>@if($selected)<strong>{{ $selected->name }}</strong><small>SKU {{ $selected->sku }}</small>@else<p>Select a catalogue product to begin.</p>@endif</section><section class="sd-card"><h2>Workflow</h2><p>Generate → Poll FASHN → Save Preview → Approve → Publish.</p><p>Published images use the existing approved Product Media gallery.</p></section></aside></div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const f=document.querySelector('[data-fashion-form]'),s=document.querySelector('[data-fashion-status]'),preview=document.querySelector('[data-live-preview]'),img=document.querySelector('[data-live-image]');if(!f)return;
const poll=async url=>{const r=await fetch(url,{headers:{Accept:'application/json'}}),d=await r.json();if(!r.ok)throw new Error(d.message||d.error||'Status check failed.');s.textContent='FASHN: '+d.status;if(d.status==='completed'){if(d.preview_url){img.src=d.preview_url;preview.style.display='block';}s.textContent='Generation complete. Review the preview, then reload to approve.';setTimeout(()=>location.reload(),1200);return;}if(d.status==='failed')throw new Error(d.error||'FASHN generation failed.');setTimeout(()=>poll(url).catch(x=>s.textContent=x.message),3000);};
f.addEventListener('submit',async e=>{e.preventDefault();const b=f.querySelector('button[type=submit]');b.disabled=true;s.textContent='Submitting to FASHN…';try{const r=await fetch(f.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':f.querySelector('[name=_token]').value},body:new FormData(f)}),d=await r.json();if(!r.ok)throw new Error(d.message||'Generation failed.');s.textContent=d.message;poll(d.status_url).catch(x=>s.textContent=x.message);}catch(x){s.textContent=x.message;b.disabled=false;}});});});
</script>
@endsection
