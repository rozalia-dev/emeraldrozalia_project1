@extends('layouts.admin')

@php
    $selectedRegions = is_array($selected?->regions ?? null) ? $selected->regions : $defaults;
    $regionsJson = json_encode($selectedRegions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $statusLabel = fn (?string $status): string => match ($status) {
        'pending_approval' => 'Pending approval',
        default => str($status ?: 'draft')->headline(),
    };
    $regions = [
        'header' => ['Header', 'Announcement, approved logo and public header controls.'],
        'header.primary_menu' => ['Primary Menu', 'The public menu shown beside the approved wordmark.'],
        'header.utility_menu' => ['Utility Menu', 'Search, account and cart destinations.'],
        'footer' => ['Footer', 'Brand description, newsletter destination and legal policy.'],
        'footer.columns' => ['Footer Columns', 'Customer-facing grouped links; contact remains footer-only.'],
        'footer.social_links' => ['Social Links', 'Only configured safe HTTPS profiles are rendered as links.'],
    ];
@endphp

@section('title', 'Shared Site Layout')

@push('styles')
<style>
    .layout-manager{max-width:1380px;margin:0 auto;color:#17271d;font-family:Inter,Arial,sans-serif;font-size:13px}.layout-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;padding:4px 0 18px;border-bottom:1px solid #dbe5dd}.layout-heading h1{margin:0;color:#14261b;font-size:28px}.layout-heading p{max-width:760px;margin:7px 0 0;color:#68776e}.layout-layout{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:16px;padding-top:16px}.layout-card{border:1px solid #dbe5dd;border-radius:8px;background:#fff;box-shadow:0 2px 9px rgba(20,48,29,.035)}.layout-card-heading{display:flex;justify-content:space-between;gap:12px;padding:15px 16px;border-bottom:1px solid #edf2ee}.layout-card-heading h2{margin:0;color:#183524;font-size:17px}.layout-card-heading p{margin:4px 0 0;color:#718077}.layout-region-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:14px}.layout-region{border:1px solid #e0e9e2;border-radius:6px;background:#fbfdfb}.layout-region summary{padding:11px;cursor:pointer;color:#2f553b;font-weight:700}.layout-region summary::marker{color:#197340}.layout-region p{margin:0;padding:0 11px 11px;color:#748279;line-height:1.45}.layout-editor{padding:16px}.layout-editor label{display:grid;gap:6px;margin-bottom:12px;color:#43564a;font-weight:700}.layout-editor input,.layout-editor textarea,.layout-editor select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd9ce;border-radius:5px;background:#fff;color:#1c3023;font:inherit;font-size:13px}.layout-editor textarea{min-height:420px;resize:vertical;font-family:Consolas,monospace;font-size:12px;line-height:1.5}.layout-editor small{color:#75847a;font-weight:400}.layout-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:36px;padding:0 12px;border:1px solid #08733b;border-radius:5px;background:#08733b;color:#fff;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}.layout-button--soft{border-color:#b8d4be;background:#fff;color:#197340}.layout-button--danger{border-color:#d9a29a;background:#fff5f3;color:#9a463e}.layout-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.layout-status{display:inline-flex;padding:4px 8px;border-radius:14px;background:#eaf5ec;color:#187140;font-size:12px}.layout-status--pending_approval{background:#fff5dd;color:#916616}.layout-status--disabled{background:#fbe9e7;color:#9a463e}.layout-status--superseded{background:#eef1ef;color:#617066}.layout-list{display:grid;gap:0;padding:0 14px}.layout-version{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:12px 0;border-bottom:1px solid #edf2ee}.layout-version:last-child{border-bottom:0}.layout-version a{color:#176f3c;text-decoration:none}.layout-version small{display:block;margin-top:3px;color:#78867c}.layout-side{display:grid;gap:14px;align-content:start}.layout-side .layout-card{padding:14px}.layout-side h2{margin:0 0 6px;color:#183524;font-size:16px}.layout-side p{margin:0;color:#718077;line-height:1.5}.layout-preview{display:grid;gap:8px;margin-top:12px;padding:12px;border:1px solid #dce7de;border-radius:6px;background:linear-gradient(140deg,#092719,#0c6038);color:#fff}.layout-preview strong{font-size:16px}.layout-preview span{color:#d5ead8}.layout-empty{padding:30px 16px;text-align:center;color:#718077}.layout-json-error{display:none;margin:0 0 12px;padding:9px;border-radius:5px;background:#fff1ef;color:#9a463e}.layout-json-error.is-visible{display:block}@media(max-width:980px){.layout-layout{grid-template-columns:1fr}.layout-side{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.layout-heading{display:block}.layout-region-grid,.layout-side{grid-template-columns:1fr}.layout-editor textarea{min-height:320px}.layout-actions>*{flex:1}}
</style>
@endpush

@section('content')
<div class="layout-manager" data-layout-manager>
    <header class="layout-heading">
        <div><p class="pages-eyebrow">WEBSITE &amp; PRODUCTS / SHARED PUBLIC SHELL</p><h1>Shared Site Layout</h1><p>Edit the public Header, Footer, Primary Menu, Utility Menu, Social Links and footer columns from the same versioned preview/publish lifecycle used by Page Manager.</p></div>
        <div class="layout-actions"><a class="layout-button layout-button--soft" href="{{ url('/') }}" target="_blank" rel="noopener"><x-icon name="eye" size="15" /> Open live storefront</a></div>
    </header>

    <div class="layout-layout">
        <main>
            <section class="layout-card">
                <div class="layout-card-heading"><div><h2>{{ $selected ? 'Edit layout version '.$selected->version : 'Create the first layout draft' }}</h2><p>JSON is constrained by the server to safe links and approved regions. Save creates an auditable version.</p></div>@if($selected)<span class="layout-status layout-status--{{ $selected->status }}">{{ $statusLabel($selected->status) }}</span>@endif</div>
                @if($selected && $selected->status === 'draft')
                    <form class="layout-editor" method="post" action="{{ route('admin.pages.layouts.update', $selected) }}" data-layout-json-form>
                        @csrf @method('PATCH')
                        <label>Layout name <input name="name" required maxlength="180" value="{{ old('name', $selected->name) }}"></label>
                        <label>Notes <textarea name="notes" rows="3">{{ old('notes', $selected->notes) }}</textarea></label>
                        <p class="layout-json-error" data-layout-json-error role="alert">The layout JSON must be valid before it can be saved.</p>
                        <label>Editable shared regions <small>Change each supported Header/Footer/Menu element here. Links must be local paths or HTTPS URLs; use an empty social-links array until a profile is approved.</small><textarea name="regions_json" required data-layout-regions-json>{{ old('regions_json', $regionsJson) }}</textarea></label>
                        <div class="layout-actions"><button class="layout-button" type="submit"><x-icon name="check" size="15" /> Save draft</button><a class="layout-button layout-button--soft" href="{{ route('admin.pages.layouts', ['version' => $selected->uuid]) }}">Reset editor</a></div>
                    </form>
                @elseif(!$selected)
                    <form class="layout-editor" method="post" action="{{ route('admin.pages.layouts.store') }}" data-layout-json-form>
                        @csrf
                        <label>Layout name <input name="name" required maxlength="180" value="{{ old('name', 'Emerald Rozalia Public Shell') }}"></label>
                        <div class="layout-actions"><label style="flex:1;margin:0">Environment<select name="environment"><option value="production">Production</option><option value="staging">Staging</option><option value="development">Development</option></select></label><label style="flex:1;margin:0">Locale<input name="locale" value="{{ old('locale', $locale) }}" maxlength="12"></label></div>
                        <label>Notes <textarea name="notes" rows="3">{{ old('notes') }}</textarea></label>
                        <p class="layout-json-error" data-layout-json-error role="alert">The layout JSON must be valid before it can be saved.</p>
                        <label>Editable shared regions <small>The initial snapshot uses only the approved Emerald Rozalia logo and real storefront destinations.</small><textarea name="regions_json" required data-layout-regions-json>{{ old('regions_json', json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) }}</textarea></label>
                        <div class="layout-actions"><button class="layout-button" type="submit"><x-icon name="plus" size="15" /> Create draft</button></div>
                    </form>
                @else
                    <div class="layout-empty"><strong>This version is {{ $statusLabel($selected->status) }}.</strong><p>Create or select a draft to edit shared regions. The preview below always reads the selected version without publishing it.</p></div>
                @endif
            </section>

            <section class="layout-card" style="margin-top:14px">
                <div class="layout-card-heading"><div><h2>Controlled public regions</h2><p>These regions are deliberately explicit so a visual preview can map every rendered element back to stored data.</p></div></div>
                <div class="layout-region-grid">
                    @foreach($regions as $path => [$label, $description])
                        <details class="layout-region" @if($path === 'header' || $path === 'footer') open @endif><summary>{{ $label }}</summary><p><code>{{ $path }}</code><br>{{ $description }}</p></details>
                    @endforeach
                </div>
            </section>
        </main>

        <aside class="layout-side">
            <section class="layout-card"><h2>Version history</h2><p>Only an approved version can become the public shared shell. Activation supersedes the previous active version.</p><div class="layout-list">@forelse($versions as $version)<div class="layout-version"><div><a href="{{ route('admin.pages.layouts', ['version' => $version->uuid, 'environment' => $version->environment]) }}">v{{ $version->version }} · {{ $version->name }}</a><small>{{ $version->environment }} / {{ $version->locale }} · {{ optional($version->created_at)->format('d M Y, H:i') }}</small></div><span class="layout-status layout-status--{{ $version->status }}">{{ $statusLabel($version->status) }}</span></div>@empty<div class="layout-empty">No shared layout versions yet.</div>@endforelse</div></section>
            <section class="layout-card"><h2>Selected preview</h2><p>This preview renders the Homepage through the public route shell. Public routes continue using the active snapshot until activation.</p><div class="layout-preview"><strong>{{ $selected?->name ?: 'Default Emerald Rozalia shell' }}</strong><span>{{ $selected ? 'Version '.$selected->version.' · '.$statusLabel($selected->status) : 'Default fallback · version 0' }}</span>@if($selected)<a class="layout-button layout-button--soft" href="{{ route('admin.pages.layouts.preview', $selected) }}" target="_blank" rel="noopener">Preview this version</a>@else<a class="layout-button layout-button--soft" href="{{ url('/') }}" target="_blank" rel="noopener">View public route</a>@endif</div></section>
            @if($selected)
                <section class="layout-card"><h2>Lifecycle actions</h2><p>Each action is policy-checked, state-aware and audited.</p><div class="layout-actions">
                    @foreach(['draft'=>['validate','Validate'], 'pending_approval'=>['approve','Approve'], 'approved'=>['activate','Activate'], 'active'=>['disable','Disable'], 'superseded'=>['rollback','Rollback']] as $state => [$action, $label])
                        @if($selected->status === $state)<form method="post" action="{{ route('admin.pages.layouts.action', [$selected, $action]) }}">@csrf<button class="layout-button {{ $action === 'disable' ? 'layout-button--danger' : 'layout-button--soft' }}" type="submit">{{ $label }}</button></form>@endif
                    @endforeach
                    @if(in_array($selected->status, ['approved','active','superseded'], true) && $selected->status !== 'active')<form method="post" action="{{ route('admin.pages.layouts.action', [$selected, 'rollback']) }}">@csrf<button class="layout-button layout-button--soft" type="submit">Rollback to this version</button></form>@endif
                    @if($selected->status === 'draft' && $selected->validated_at)<form method="post" action="{{ route('admin.pages.layouts.action', [$selected, 'submit']) }}">@csrf<button class="layout-button layout-button--soft" type="submit">Request approval</button></form>@endif
                </div></section>
            @endif
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    document.querySelectorAll('[data-layout-json-form]').forEach((form) => form.addEventListener('submit', (event) => {
        const field = form.querySelector('[data-layout-regions-json]');
        const error = form.querySelector('[data-layout-json-error]');
        try { JSON.parse(field.value); error?.classList.remove('is-visible'); }
        catch { event.preventDefault(); error?.classList.add('is-visible'); field?.focus(); }
    }));
})();
</script>
@endpush
