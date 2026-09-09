@php($editing = isset($record) && $record)
@php($embedded = $embedded ?? false)
<form class="sd-editor {{ $embedded?'sd-editor--embedded':'' }}" method="post" action="{{ $editing?route('admin.spins.update',$record->id):route('admin.spins.store') }}" enctype="multipart/form-data" data-spin-form>
@csrf @if($editing) @method('PATCH') @endif
<div class="sd-edit-grid">
<section class="sd-card sd-create-card">
    <h2>{{ $editing?'Update 360° View':'Create 360° View' }}</h2>
    <div class="sd-core-fields">
        <label>Title<input name="title" required maxlength="160" value="{{ old('title',$record?->title??'') }}"></label>
        <label>Product / SKU<select name="product_id" required><option value="">Select a product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('product_id',$record?->product_id)==$product->id)>{{ $product->name }} · {{ $product->sku }}</option>@endforeach</select></label>
    </div>
    <label class="sd-drop"><x-icon name="upload" size="28" /><strong>{{ $editing?'Replace 360° frames (ZIP)':'Drag & drop 360° frames (ZIP)' }}</strong><span>or</span><input type="file" name="archive" accept=".zip,application/zip" @required(!$editing)><span data-file-note>Choose Files</span></label>
    <small>Supports: ZIP (recommended). Image formats: JPG, PNG.</small>
    <small>Recommended: 24–72 equal-sized frames. Maximum compressed ZIP size: 20 MB.</small>
    <details class="sd-guide"><summary>View Creation Guide</summary><p>Photograph the product on a turntable with fixed lighting and camera position. Name frames in rotation order (001.jpg, 002.jpg, …), keep dimensions identical, and ZIP only the JPG/PNG frames.</p></details>
    @if($embedded)<button class="sd-button sd-card-action" type="submit">{{ $editing?'Save 360° View':'Create 360° View' }}</button>@endif
</section>
<section class="sd-card sd-settings-card" data-settings-card><h2>360° Settings <small>{{ $editing?'(Selected View)':'(Default)' }}</small></h2>
    <label id="sd-category-field">Type<select name="category">@foreach(\App\Models\ProductSpin::CATEGORIES as $key=>$label)<option value="{{ $key }}" @selected(old('category',$record?->category??'product')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Status<select name="status">@foreach(\App\Models\ProductSpin::STATUSES as $key=>$label)<option value="{{ $key }}" @selected(old('status',$record?->status??'draft')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Visibility<select name="visibility"><option value="private" @selected(old('visibility',$record?->visibility)==='private')>Private</option><option value="public" @selected(old('visibility',$record?->visibility)==='public')>Public</option></select></label>
    @foreach(['auto_rotate'=>'Auto Rotate','zoom'=>'Zoom In/Out','fullscreen'=>'Fullscreen Mode','hotspots'=>'Hotspot Support','lazy_load'=>'Lazy Load','mobile'=>'Mobile Optimized'] as $key=>$label)
    <label class="sd-toggle"><span>{{ $label }}</span><input type="checkbox" role="switch" name="{{ $key }}" value="1" @checked(old($key,$record?->settings[$key]??\App\Models\ProductSpin::DEFAULTS[$key]))></label>
    @endforeach
    <small>Published + Public views appear on an active product page. Publishing never changes visibility.</small>
    @if($embedded)<button class="sd-button sd-outline sd-card-action" type="submit">Manage Settings</button>@endif
</section>
<section class="sd-card sd-seo-card"><h2>SEO &amp; Accessibility</h2>
    <label>Alt Text (AI)<textarea name="alt" maxlength="500">{{ old('alt',$record?->seo['alt']??'') }}</textarea></label>
    <label>Title (for SEO)<input name="seo_title" maxlength="160" value="{{ old('seo_title',$record?->seo['title']??'') }}"></label>
    <label>ARIA Label<input name="aria" maxlength="200" value="{{ old('aria',$record?->seo['aria']??'') }}"></label>
    <details><summary>Hotspot editor</summary><p>Frame numbers start at 0. X and Y are percentages of the displayed image. Maximum 20 hotspots.</p>
    <label>Hotspots JSON<textarea name="hotspot_data" rows="6" spellcheck="false">{{ old('hotspot_data',json_encode($record?->hotspots??[],JSON_PRETTY_PRINT)) }}</textarea></label>
    <small>Example: [{"frame":0,"x":50,"y":40,"label":"Embroidered logo"}]</small></details>
    @if($embedded)<button class="sd-button sd-outline sd-card-action" type="submit">Save SEO &amp; Accessibility</button>@endif
</section>
</div>
@if(!$embedded)<div class="sd-save"><span data-upload-message role="status"></span><button class="sd-button" type="submit">{{ $editing?'Save Settings, SEO & Accessibility':'Create 360° View' }}</button></div>@else<span class="sd-workbench-status" data-upload-message role="status"></span>@endif
</form>
