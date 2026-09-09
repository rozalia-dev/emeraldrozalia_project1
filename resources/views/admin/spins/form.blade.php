@php($editing = isset($record) && $record)
<form class="sd-editor" method="post" action="{{ $editing?route('admin.spins.update',$record->id):route('admin.spins.store') }}" enctype="multipart/form-data" data-spin-form>
@csrf @if($editing) @method('PATCH') @endif
<div class="sd-edit-grid">
<section class="sd-card">
    <h2>{{ $editing?'Edit 360° View':'Create 360° View' }}</h2>
    <label>Title<input name="title" required maxlength="160" value="{{ old('title',$record?->title??'') }}"></label>
    <label>Product / SKU<select name="product_id" required><option value="">Select a product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('product_id',$record?->product_id)==$product->id)>{{ $product->name }} · {{ $product->sku }}</option>@endforeach</select></label>
    <label class="sd-drop"><x-icon name="upload" size="28" /><strong>{{ $editing?'Replace frames (optional)':'Upload frames ZIP' }}</strong><input type="file" name="archive" accept=".zip,application/zip" @required(!$editing)><span data-file-note>Choose or drop a ZIP file</span></label>
    <small>ZIP up to 20 MB. JPG / PNG; 2–72 equal-sized frames, naturally sorted by filename. Recommended: 24–72 frames.</small>
    <small>Frames are optimized to JPEG, up to 2000 px. New uploads replace the complete frame set.</small>
</section>
<section class="sd-card"><h2>360° Settings</h2>
    <label>Type<select name="category">@foreach(\App\Models\ProductSpin::CATEGORIES as $key=>$label)<option value="{{ $key }}" @selected(old('category',$record?->category??'product')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Status<select name="status">@foreach(\App\Models\ProductSpin::STATUSES as $key=>$label)<option value="{{ $key }}" @selected(old('status',$record?->status??'draft')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Visibility<select name="visibility"><option value="private" @selected(old('visibility',$record?->visibility)==='private')>Private</option><option value="public" @selected(old('visibility',$record?->visibility)==='public')>Public</option></select></label>
    @foreach(['auto_rotate'=>'Auto Rotate','zoom'=>'Zoom In/Out','fullscreen'=>'Fullscreen Mode','hotspots'=>'Hotspot Support','lazy_load'=>'Lazy Load','mobile'=>'Mobile Interaction'] as $key=>$label)
    <label class="sd-toggle"><span>{{ $label }}</span><input type="checkbox" role="switch" name="{{ $key }}" value="1" @checked(old($key,$record?->settings[$key]??\App\Models\ProductSpin::DEFAULTS[$key]))></label>
    @endforeach
    <small>Public views appear on the product page only when both the view is Published and the product is active.</small>
</section>
<section class="sd-card"><h2>SEO &amp; Accessibility</h2>
    <label>Alt text<textarea name="alt" maxlength="500">{{ old('alt',$record?->seo['alt']??'') }}</textarea></label>
    <label>Title for SEO<input name="seo_title" maxlength="160" value="{{ old('seo_title',$record?->seo['title']??'') }}"></label>
    <label>ARIA label<input name="aria" maxlength="200" value="{{ old('aria',$record?->seo['aria']??'') }}"></label>
    <details><summary>Hotspot editor</summary><p>Frame numbers start at 0. X and Y are percentages of the displayed image. Maximum 20 hotspots.</p>
    <label>Hotspots JSON<textarea name="hotspot_data" rows="6" spellcheck="false">{{ old('hotspot_data',json_encode($record?->hotspots??[],JSON_PRETTY_PRINT)) }}</textarea></label>
    <small>Example: [{"frame":0,"x":50,"y":40,"label":"Embroidered logo"}]</small></details>
</section>
</div>
<div class="sd-save"><span data-upload-message role="status"></span><button class="sd-button" type="submit">{{ $editing?'Save Settings, SEO & Accessibility':'Create 360° View' }}</button></div>
</form>
