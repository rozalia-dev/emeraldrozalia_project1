@extends('layouts.admin')

@section('title', 'cPanel Appearance')

@php
    $tokens = $selected?->token_payload ?: data_get($activeSnapshot, 'tokens', $defaults);
    $token = fn (string $path, mixed $fallback = '') => data_get($tokens, $path, $fallback);
    $statusLabel = fn (string $status): string => \Illuminate\Support\Str::headline($status);
    $colorFields = [
        'sidebar_start' => 'Sidebar start', 'sidebar_end' => 'Sidebar end', 'sidebar_text' => 'Sidebar text',
        'topbar' => 'Top bar', 'topbar_text' => 'Top bar text', 'accent' => 'Accent / active',
        'background' => 'Page background', 'surface' => 'Card surface', 'text' => 'Main text',
        'muted' => 'Muted text', 'border' => 'Borders', 'focus' => 'Focus ring',
        'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger', 'info' => 'Info',
    ];
    $previewStyle = implode(';', [
        '--cp-preview-sidebar-a:'.$token('colors.sidebar_start', '#052617'),
        '--cp-preview-sidebar-b:'.$token('colors.sidebar_end', '#02170e'),
        '--cp-preview-sidebar-text:'.$token('colors.sidebar_text', '#ffffff'),
        '--cp-preview-topbar:'.$token('colors.topbar', '#063020'),
        '--cp-preview-topbar-text:'.$token('colors.topbar_text', '#ffffff'),
        '--cp-preview-accent:'.$token('colors.accent', '#7fbd42'),
        '--cp-preview-bg:'.$token('colors.background', '#f3f5f1'),
        '--cp-preview-surface:'.$token('colors.surface', '#ffffff'),
        '--cp-preview-text:'.$token('colors.text', '#15221b'),
        '--cp-preview-muted:'.$token('colors.muted', '#647168'),
        '--cp-preview-border:'.$token('colors.border', '#d8dfda'),
        '--cp-preview-radius:'.$token('spacing.radius', '8px'),
    ]);
@endphp

@push('styles')
<style>
.cp-theme{display:grid;gap:18px}.cp-theme-header{display:flex;justify-content:space-between;align-items:flex-start;gap:18px}.cp-theme-header h1{margin:0 0 6px;font-size:27px}.cp-theme-header p{margin:0;max-width:760px}.cp-theme-actions{display:flex;gap:8px;flex-wrap:wrap}.cp-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:38px;padding:8px 13px;border:1px solid var(--cpanel-border,#d8dfda);border-radius:var(--cpanel-button-radius,6px);background:var(--cpanel-surface,#fff);color:var(--cpanel-text,#15221b);font-weight:700;text-decoration:none;cursor:pointer}.cp-btn--primary{background:var(--cpanel-accent,#7fbd42);border-color:var(--cpanel-accent,#7fbd42);color:var(--cpanel-sidebar-end,#02170e)}.cp-btn--danger{color:var(--cpanel-danger,#b42318)}.cp-theme-note{padding:12px 14px;border:1px solid var(--cpanel-border,#d8dfda);border-left:4px solid var(--cpanel-accent,#7fbd42);border-radius:var(--cpanel-radius,8px);background:var(--cpanel-surface,#fff)}.cp-theme-grid{display:grid;grid-template-columns:minmax(250px,.7fr) minmax(0,1.3fr);gap:18px}.cp-card{background:var(--cpanel-surface,#fff);border:1px solid var(--cpanel-border,#d8dfda);border-radius:var(--cpanel-radius,8px);padding:18px}.cp-card h2{margin:0 0 5px;font-size:17px}.cp-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}.cp-version-list{display:grid;gap:7px}.cp-version{display:grid;grid-template-columns:1fr auto;gap:8px;padding:11px;border:1px solid var(--cpanel-border,#d8dfda);border-radius:7px;color:var(--cpanel-text,#15221b);text-decoration:none}.cp-version:hover,.cp-version.is-selected{border-color:var(--cpanel-accent,#7fbd42);background:color-mix(in srgb,var(--cpanel-accent,#7fbd42) 8%,var(--cpanel-surface,#fff))}.cp-version strong,.cp-version small{display:block}.cp-status{display:inline-flex;align-items:center;height:24px;padding:0 8px;border-radius:999px;background:#edf2ee;color:#405247;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.cp-status--active{background:#ddf2e5;color:#12633a}.cp-status--draft{background:#e9f1ff;color:#285a9e}.cp-status--pending_approval,.cp-status--approved{background:#fff1d9;color:#8b5608}.cp-preview{overflow:hidden;border:1px solid var(--cp-preview-border);border-radius:var(--cp-preview-radius);background:var(--cp-preview-bg);min-height:315px}.cp-preview-top{height:48px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;background:var(--cp-preview-topbar);color:var(--cp-preview-topbar-text);font-size:11px;font-weight:800}.cp-preview-body{display:grid;grid-template-columns:128px 1fr;min-height:267px}.cp-preview-side{padding:15px 10px;background:linear-gradient(180deg,var(--cp-preview-sidebar-a),var(--cp-preview-sidebar-b));color:var(--cp-preview-sidebar-text)}.cp-preview-side b{display:block;margin-bottom:14px;font-size:10px}.cp-preview-side span{display:block;padding:7px 8px;border-radius:5px;font-size:9px}.cp-preview-side span.is-active{background:var(--cp-preview-accent);color:var(--cp-preview-sidebar-b)}.cp-preview-main{padding:18px;color:var(--cp-preview-text)}.cp-preview-main p{margin:4px 0 14px;color:var(--cp-preview-muted);font-size:10px}.cp-preview-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.cp-preview-kpi{padding:12px;border:1px solid var(--cp-preview-border);border-radius:var(--cp-preview-radius);background:var(--cp-preview-surface)}.cp-preview-kpi small{display:block;color:var(--cp-preview-muted);font-size:8px}.cp-preview-kpi strong{font-size:15px}.cp-form{display:grid;gap:18px}.cp-form fieldset{margin:0;padding:16px;border:1px solid var(--cpanel-border,#d8dfda);border-radius:var(--cpanel-radius,8px)}.cp-form legend{padding:0 7px;font-weight:800}.cp-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.cp-field{display:grid;gap:6px}.cp-field>span{font-size:11px;font-weight:800}.cp-field small{font-size:10px}.cp-field input,.cp-field select,.cp-field textarea{width:100%;min-height:39px;padding:8px 10px}.cp-field textarea{min-height:70px;resize:vertical}.cp-wide{grid-column:1/-1}.cp-colour{display:grid;grid-template-columns:38px 1fr;gap:7px}.cp-colour input[type=color]{width:38px;height:39px;padding:2px}.cp-validation{padding:13px;border:1px solid #efc9c5;border-radius:7px;background:#fff3f1;color:#8c3028}.cp-validation ul{margin:7px 0 0}.cp-lifecycle{display:flex;gap:8px;flex-wrap:wrap;padding-top:14px;border-top:1px solid var(--cpanel-border,#d8dfda)}.cp-empty{padding:22px;text-align:center;border:1px dashed var(--cpanel-border,#d8dfda);border-radius:8px}.cp-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}.cp-meta span{padding:9px;border:1px solid var(--cpanel-border,#d8dfda);border-radius:6px;font-size:10px}.cp-meta b{display:block;margin-bottom:3px}@media(max-width:1000px){.cp-theme-grid{grid-template-columns:1fr}.cp-preview-body{grid-template-columns:105px 1fr}}@media(max-width:650px){.cp-theme-header{display:grid}.cp-fields,.cp-preview-kpis,.cp-meta{grid-template-columns:1fr}.cp-wide{grid-column:auto}}
</style>
@endpush

@section('content')
<div class="cp-theme" data-cpanel-theme-editor>
    <header class="cp-theme-header">
        <div><h1>cPanel Appearance</h1><p>Edit the administration shell independently from the public storefront. Changes are versioned and do not affect the live cPanel until the draft is validated, approved and activated.</p></div>
        <div class="cp-theme-actions"><a class="cp-btn" href="{{route('admin.settings.theme.index')}}">Public Theme Manager</a><a class="cp-btn" href="{{route('admin.settings.overview')}}">Settings</a></div>
    </header>

    <div class="cp-theme-note"><strong>Safe activation contract.</strong> Draft colours and layout values are preview-only. The cPanel shell reads only the active <code>admin</code>-scope theme snapshot.</div>

    @if($selected && $selected->validation_errors)
        <div class="cp-validation"><strong>Validation needs attention.</strong><ul>@foreach($selected->validation_errors as $path => $message)<li><code>{{$path}}</code> — {{$message}}</li>@endforeach</ul></div>
    @endif

    <div class="cp-theme-grid">
        <section class="cp-card">
            <div class="cp-card-head"><div><h2>cPanel theme versions</h2><small>{{$environment}} · {{$locale}}</small></div><span class="cp-status">{{count($versions)}} versions</span></div>
            @if($versions->isEmpty())
                <div class="cp-empty"><strong>No cPanel theme versions yet.</strong><p>Create the first draft using the approved defaults.</p></div>
            @else
                <div class="cp-version-list">
                    @foreach($versions as $version)
                        <a class="cp-version {{$selected?->is($version) ? 'is-selected' : ''}}" href="{{route('admin.settings.cpanel-theme.index',['environment'=>$version->environment,'version'=>$version->uuid])}}">
                            <span><strong>{{$version->name}}</strong><small>v{{$version->version}} · {{$version->uuid}}</small></span>
                            <span class="cp-status cp-status--{{$version->status}}">{{$statusLabel($version->status)}}</span>
                        </a>
                    @endforeach
                </div>
            @endif
            <div class="cp-meta">
                <span><b>Live source</b>{{data_get($activeSnapshot,'meta.source')}}</span>
                <span><b>Live version</b>v{{data_get($activeSnapshot,'meta.version',0)}}</span>
                <span><b>Scope</b>admin</span>
            </div>
        </section>

        <section class="cp-card">
            <div class="cp-card-head"><div><h2>cPanel preview</h2><small>{{$selected && $selected->status !== 'active' ? 'Previewing selected version' : 'Current appearance'}}</small></div>@if($selected)<span class="cp-status cp-status--{{$selected->status}}">{{$statusLabel($selected->status)}}</span>@endif</div>
            <div class="cp-preview" data-cpanel-preview style="{{$previewStyle}}">
                <div class="cp-preview-top"><span>Project 1 Control Panel</span><span>Search · Alerts · Admin</span></div>
                <div class="cp-preview-body"><aside class="cp-preview-side"><b>EMERALD ROZALIA</b><span class="is-active">Dashboard</span><span>Website &amp; Products</span><span>Orders</span><span>Franchise</span><span>Communication</span><span>Reports</span><span>Settings</span></aside><main class="cp-preview-main"><strong>Dashboard</strong><p>Live preview of the cPanel shell colour and surface contract.</p><div class="cp-preview-kpis"><div class="cp-preview-kpi"><small>ONLINE SALES</small><strong>€12.4k</strong></div><div class="cp-preview-kpi"><small>FRANCHISE</small><strong>18</strong></div><div class="cp-preview-kpi"><small>ACTIONS</small><strong>7</strong></div></div></main></div>
            </div>
        </section>
    </div>

    @if(!$selected || $selected->status === 'draft')
        <section class="cp-card">
            <div class="cp-card-head"><div><h2>{{$selected ? 'Edit cPanel theme draft' : 'Create cPanel theme draft'}}</h2><small>Server-persisted, tenant-scoped and audited.</small></div><span class="cp-status cp-status--draft">Draft editor</span></div>
            <form class="cp-form" method="POST" action="{{$selected ? route('admin.settings.cpanel-theme.update',$selected) : route('admin.settings.cpanel-theme.store')}}">
                @csrf
                @if($selected) @method('PATCH') @endif
                <input type="hidden" name="locale" value="{{$locale}}">
                <div class="cp-fields">
                    <label class="cp-field"><span>Theme name</span><input name="name" required maxlength="180" value="{{old('name',$selected?->name ?: 'Emerald Rozalia cPanel Theme')}}"></label>
                    @if($selected)
                        <label class="cp-field"><span>Environment</span><input value="{{$selected->environment}}" readonly></label>
                    @else
                        <label class="cp-field"><span>Environment</span><select name="environment">@foreach(\App\Services\CpanelThemeVersionService::ENVIRONMENTS as $option)<option value="{{$option}}" @selected($environment===$option)>{{\Illuminate\Support\Str::headline($option)}}</option>@endforeach</select></label>
                    @endif
                    <label class="cp-field cp-wide"><span>Notes</span><textarea name="notes" maxlength="2000" placeholder="Reason for this cPanel appearance revision">{{old('notes',$selected?->notes)}}</textarea></label>
                </div>

                <fieldset><legend>cPanel colour system</legend><div class="cp-fields">
                    @foreach($colorFields as $key => $label)
                        <label class="cp-field"><span>{{$label}}</span><span class="cp-colour"><input type="color" value="{{$token('colors.'.$key)}}" data-cpanel-colour-picker data-target="cp-colour-{{$key}}"><input id="cp-colour-{{$key}}" type="text" name="tokens[colors][{{$key}}]" value="{{old('tokens.colors.'.$key,$token('colors.'.$key))}}" pattern="#[0-9a-fA-F]{6}" maxlength="7" data-cpanel-colour-text data-preview-key="{{$key}}"></span></label>
                    @endforeach
                </div></fieldset>

                <fieldset><legend>Typography &amp; geometry</legend><div class="cp-fields">
                    <label class="cp-field"><span>Body font</span><select name="tokens[typography][font_family]">@foreach(['Inter, Arial, sans-serif'=>'Inter / Arial','system-ui, sans-serif'=>'System UI','Georgia, serif'=>'Georgia'] as $value=>$label)<option value="{{$value}}" @selected($token('typography.font_family')===$value)>{{$label}}</option>@endforeach</select></label>
                    <label class="cp-field"><span>Heading font</span><select name="tokens[typography][heading_family]">@foreach(['Inter, Arial, sans-serif'=>'Inter / Arial','system-ui, sans-serif'=>'System UI','Georgia, serif'=>'Georgia'] as $value=>$label)<option value="{{$value}}" @selected($token('typography.heading_family')===$value)>{{$label}}</option>@endforeach</select></label>
                    <label class="cp-field"><span>Base font size</span><input name="tokens[typography][base_size]" value="{{old('tokens.typography.base_size',$token('typography.base_size'))}}" pattern="\d{1,4}px"><small>11px–20px</small></label>
                    <label class="cp-field"><span>Card radius</span><input name="tokens[spacing][radius]" value="{{old('tokens.spacing.radius',$token('spacing.radius'))}}" pattern="\d{1,4}px"></label>
                    <label class="cp-field"><span>Sidebar width</span><input name="tokens[spacing][sidebar_width]" value="{{old('tokens.spacing.sidebar_width',$token('spacing.sidebar_width'))}}" pattern="\d{1,4}px"><small>190px–360px</small></label>
                    <label class="cp-field"><span>Button radius</span><input name="tokens[controls][button_radius]" value="{{old('tokens.controls.button_radius',$token('controls.button_radius'))}}" pattern="\d{1,4}px"></label>
                    <label class="cp-field"><span>Input radius</span><input name="tokens[controls][input_radius]" value="{{old('tokens.controls.input_radius',$token('controls.input_radius'))}}" pattern="\d{1,4}px"></label>
                </div></fieldset>

                <div class="cp-lifecycle"><button class="cp-btn cp-btn--primary" type="submit">{{$selected ? 'Save draft' : 'Create draft'}}</button><span style="align-self:center"><small>Saving a draft never changes the live cPanel.</small></span></div>
            </form>
        </section>
    @elseif($selected)
        <section class="cp-card">
            <div class="cp-card-head"><div><h2>Immutable cPanel theme snapshot</h2><small>Version {{$selected->version}} is {{$statusLabel($selected->status)}}.</small></div><span class="cp-status cp-status--{{$selected->status}}">{{$statusLabel($selected->status)}}</span></div>
            <p>Approved and activated versions are immutable. Use the lifecycle controls below or create a new draft to change the cPanel appearance.</p>
        </section>
    @endif

    <section class="cp-card">
        <div class="cp-card-head"><div><h2>Lifecycle controls</h2><small>Validation → approval → activation. Only an active admin-scope snapshot styles the cPanel.</small></div></div>
        <div class="cp-lifecycle">
            @if(!$selected)
                <span>No theme version selected yet.</span>
            @elseif($selected->status === 'draft')
                <form method="POST" action="{{route('admin.settings.cpanel-theme.action',[$selected,'validate'])}}">@csrf<button class="cp-btn" type="submit">Validate</button></form>
                @if($selected->validated_at)<form method="POST" action="{{route('admin.settings.cpanel-theme.action',[$selected,'submit'])}}">@csrf<button class="cp-btn cp-btn--primary" type="submit">Submit for approval</button></form>@endif
            @elseif($selected->status === 'pending_approval')
                <form method="POST" action="{{route('admin.settings.cpanel-theme.action',[$selected,'approve'])}}">@csrf<button class="cp-btn cp-btn--primary" type="submit">Approve</button></form>
            @elseif($selected->status === 'approved')
                <form method="POST" action="{{route('admin.settings.cpanel-theme.action',[$selected,'activate'])}}">@csrf<button class="cp-btn cp-btn--primary" type="submit">Activate cPanel theme</button></form>
                <form method="POST" action="{{route('admin.settings.cpanel-theme.action',[$selected,'disable'])}}">@csrf<button class="cp-btn cp-btn--danger" type="submit">Disable</button></form>
            @elseif($selected->status === 'active')
                <form method="POST" action="{{route('admin.settings.cpanel-theme.action',[$selected,'rollback'])}}">@csrf<button class="cp-btn" type="submit">Rollback to previous</button></form>
                <form method="POST" action="{{route('admin.settings.cpanel-theme.action',[$selected,'disable'])}}">@csrf<button class="cp-btn cp-btn--danger" type="submit">Disable active theme</button></form>
            @elseif($selected->status === 'superseded')
                <form method="POST" action="{{route('admin.settings.cpanel-theme.action',[$selected,'rollback'])}}">@csrf<button class="cp-btn cp-btn--primary" type="submit">Restore this version</button></form>
            @else
                <span>This version has no available lifecycle action.</span>
            @endif
            @if($selected && $selected->status !== 'draft')<a class="cp-btn" href="{{route('admin.settings.cpanel-theme.index',['environment'=>$environment])}}">Open latest draft / active</a>@endif
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root=document.querySelector('[data-cpanel-theme-editor]'); if(!root)return;
    const preview=root.querySelector('[data-cpanel-preview]');
    const map={sidebar_start:'--cp-preview-sidebar-a',sidebar_end:'--cp-preview-sidebar-b',sidebar_text:'--cp-preview-sidebar-text',topbar:'--cp-preview-topbar',topbar_text:'--cp-preview-topbar-text',accent:'--cp-preview-accent',background:'--cp-preview-bg',surface:'--cp-preview-surface',text:'--cp-preview-text',muted:'--cp-preview-muted',border:'--cp-preview-border'};
    root.querySelectorAll('[data-cpanel-colour-picker]').forEach(picker=>{const text=document.getElementById(picker.dataset.target);if(!text)return;picker.addEventListener('input',()=>{text.value=picker.value;text.dispatchEvent(new Event('input',{bubbles:true}))});text.addEventListener('input',()=>{if(/^#[0-9a-fA-F]{6}$/.test(text.value)){picker.value=text.value;if(preview&&map[text.dataset.previewKey])preview.style.setProperty(map[text.dataset.previewKey],text.value)}})});
})();
</script>
@endpush
