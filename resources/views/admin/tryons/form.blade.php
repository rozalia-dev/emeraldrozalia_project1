@php($editing = isset($record) && $record)
@php($embedded = $embedded ?? false)
<form class="sd-editor {{ $embedded?'sd-editor--embedded':'' }}" method="post" action="{{ $editing?route('admin.tryons.update',$record->id):route('admin.tryons.store') }}" enctype="multipart/form-data" data-tryon-form>
@csrf @if($editing) @method('PATCH') @endif
<div class="to-edit-grid">
<section class="sd-card sd-create-card">
    <h2>{{ $editing?'Update Try-On Asset':'Upload Try-On Asset' }}</h2>
    @if(!$editing && ($scopedProduct ?? null))
        <p class="sd-product-context" data-scoped-product-context>Managing Try-On media for <strong>{{ $scopedProduct->name }}</strong> · SKU {{ $scopedProduct->sku }}</p>
    @endif
    <div class="sd-core-fields">
        <label>Title<input name="title" required maxlength="160" value="{{ old('title',$record?->title??'') }}"></label>
        <label>Product / SKU<select name="product_id" required data-scoped-product-select><option value="">Select a product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((int) old('product_id',$record?->product_id ?? $scopedProductId ?? 0)===(int) $product->id)>{{ $product->name }} · {{ $product->sku }}</option>@endforeach</select></label>
    </div>
    <label class="sd-drop"><x-icon name="upload" size="28" /><strong>{{ $editing?'Replace Try-On files':'Drag & drop try-on files here' }}</strong><span>or</span><input type="file" name="asset" accept=".zip,.png,.jpg,.jpeg,.webp,.glb,.usdz" @required(!$editing)><span data-file-note>Choose Files</span></label>
    <small>Supports: ZIP, GLB, USDZ, JPG, PNG, WebP. Maximum upload size: 20 MB.</small>
    <small>For storefront publishing, include a browser preview overlay (PNG, JPG or WebP). ZIP may also contain one GLB/USDZ model.</small>
    <details class="sd-guide"><summary>Upload Guidelines</summary><p>Use a transparent front-facing hat/cap overlay for best fitting results. Keep the product centered, tightly cropped and free of unrelated logos or backgrounds. A published asset must include a browser preview overlay.</p></details>
    @if($embedded)<button class="sd-button sd-card-action" type="submit">{{ $editing?'Save Try-On Asset':'Create Try-On Asset' }}</button>@endif
</section>

<section class="sd-card sd-settings-card" data-settings-card>
    <h2>Try-On Settings <small>{{ $editing?'(Selected Asset)':'(Default)' }}</small></h2>
    <label>Type<select name="type">@foreach(\App\Models\TryOnAsset::TYPES as $key=>$label)<option value="{{ $key }}" @selected(old('type',$record?->type??'ar_ai')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Status<select name="status">@foreach(\App\Models\TryOnAsset::STATUSES as $key=>$label)<option value="{{ $key }}" @selected(old('status',$record?->status??'draft')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Visibility<select name="visibility"><option value="private" @selected(old('visibility',$record?->visibility)==='private')>Private</option><option value="public" @selected(old('visibility',$record?->visibility??'public')==='public')>Public</option></select></label>
    @foreach(['auto_fit'=>'Auto Fit to Head','face_detection'=>'Face Detection','realistic_lighting'=>'Realistic Lighting','shadow_rendering'=>'Shadow Rendering','occlusion'=>'Occlusion Handling','high_quality'=>'High Quality Mode','mobile'=>'Mobile Optimized'] as $key=>$label)
    <label class="sd-toggle"><span>{{ $label }}</span><input type="checkbox" role="switch" name="{{ $key }}" value="1" @checked(old($key,$record?->settings[$key]??\App\Models\TryOnAsset::DEFAULTS[$key]))></label>
    @endforeach
    <div class="sd-core-fields">
        <label>3D Model Scale<input type="number" name="model_scale" min="0.2" max="3" step="0.05" value="{{ old('model_scale',$record?->settings['model_scale']??1) }}"></label>
        <label>3D Vertical Offset<input type="number" name="model_y" min="-2" max="2" step="0.05" value="{{ old('model_y',$record?->settings['model_y']??0) }}"></label>
        <label>3D Rotation Offset<input type="number" name="model_rotation" min="-180" max="180" step="1" value="{{ old('model_rotation',$record?->settings['model_rotation']??0) }}"></label>
    </div>
    @php($has3dModel = $editing && $record?->modelPath())
    <div class="sd-3d-status" role="status" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;margin:10px 0;border:1px solid {{ $has3dModel ? '#5f9f36' : '#8a6b2b' }};border-radius:10px;background:{{ $has3dModel ? 'rgba(95,159,54,.10)' : 'rgba(138,107,43,.10)' }};">
        <span><strong>3D MODEL</strong><br><small>{{ $has3dModel ? '3D READY — GLB/USDZ model is attached to this product.' : 'MODEL MISSING — upload a GLB or a ZIP containing a GLB to enable the storefront 3D Model button.' }}</small></span>
        <span style="white-space:nowrap;font-weight:800;">{{ $has3dModel ? 'READY' : 'MISSING' }}</span>
    </div>
    @if($editing)
    <div data-ai3d-panel data-start-url="{{ route('admin.tryons.generate-3d',$record) }}" data-status-url="{{ route('admin.tryons.generate-3d.status',$record) }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0">
        <button type="button" class="sd-button sd-outline" data-ai3d-generate @disabled($has3dModel)>AI GENERATE 3D MODEL</button>
        <small data-ai3d-message>{{ $has3dModel ? '3D model ready for storefront.' : 'Uses the saved product preview to generate a textured GLB automatically.' }}</small>
    </div>
    @endif
    <small>Upload a GLB (or ZIP containing preview + GLB) for interactive browser 3D. USDZ remains available as an Apple AR asset. Calibrate scale, vertical offset and rotation per product.</small>
    <small>Published + Public assets are automatically available in the storefront Virtual Try-On Studio for the linked active product.</small>
    @if($embedded)<button class="sd-button sd-outline sd-card-action" type="submit">Manage Settings</button>@endif
</section>

<section class="sd-card to-target-card" id="to-target-field">
    <h2>Target Models</h2>
    <label>Model / Target<select name="target">@foreach(\App\Models\TryOnAsset::TARGETS as $key=>$label)<option value="{{ $key }}" @selected(old('target',$record?->target??'unisex')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Age Range<input name="age_range" maxlength="40" placeholder="e.g. 18–35 yrs or All Ages" value="{{ old('age_range',$record?->age_range??'All Ages') }}"></label>
    <div class="to-target-preview" aria-hidden="true"><span>Male</span><span>Female</span><span>Unisex</span><span>Kids</span></div>
    <small>Target metadata helps operators keep fit presets and creative assets organized. It does not infer a customer's age or gender.</small>
    @if($embedded)<button class="sd-button sd-outline sd-card-action" type="submit">Manage Models</button>@endif
</section>

<section class="sd-card sd-seo-card">
    <h2>Accessibility &amp; SEO</h2>
    <label>Alt Text (AI)<textarea name="alt" maxlength="500">{{ old('alt',$record?->seo['alt']??'') }}</textarea></label>
    <label>Title (for SEO)<input name="seo_title" maxlength="160" value="{{ old('seo_title',$record?->seo['title']??'') }}"></label>
    <label>Tags<input name="tags" maxlength="500" placeholder="try-on, cap, emerald, virtual" value="{{ old('tags',implode(', ',$record?->seo['tags']??[])) }}"></label>
    <small>Alt text is used by the browser preview. Tags are stored as metadata and are never exposed as executable markup.</small>
    @if($embedded)<button class="sd-button sd-outline sd-card-action" type="submit">Save SEO</button>@endif
</section>
</div>
@if(!$embedded)<div class="sd-save"><span data-upload-message role="status"></span><button class="sd-button" type="submit">{{ $editing?'Save Try-On Asset':'Create Try-On Asset' }}</button></div>@else<span class="sd-workbench-status" data-upload-message role="status"></span>@endif
</form>

@if($editing)
<script>
document.addEventListener('DOMContentLoaded',()=>{const p=document.querySelector('[data-ai3d-panel]');if(!p)return;const b=p.querySelector('[data-ai3d-generate]'),m=p.querySelector('[data-ai3d-message]');let timer;
const poll=async()=>{try{const r=await fetch(p.dataset.statusUrl,{headers:{Accept:'application/json'}}),d=await r.json();if(!r.ok)throw new Error(d.message||'Unable to check 3D generation.');m.textContent=(d.status||'PROCESSING')+(d.progress!=null?' · '+d.progress+'%':'');if(d.ready){m.textContent='3D READY — refreshing…';clearInterval(timer);location.reload();}else if(['FAILED','CANCELED','EXPIRED'].includes(d.status)){clearInterval(timer);b.disabled=false;m.textContent=d.error||('3D generation '+d.status.toLowerCase()+'.');}}catch(e){clearInterval(timer);b.disabled=false;m.textContent=e.message;}};
b?.addEventListener('click',async()=>{b.disabled=true;m.textContent='Starting AI 3D generation…';try{const r=await fetch(p.dataset.startUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'{{ csrf_token() }}'}}),d=await r.json();if(!r.ok)throw new Error(d.message||'Unable to start AI 3D generation.');m.textContent=d.message;timer=setInterval(poll,5000);poll();}catch(e){b.disabled=false;m.textContent=e.message;}});
});
</script>
@endif
