@extends('layouts.admin')

@section('title', 'Theme & Colors')

@push('styles')
    <link rel="stylesheet" href="/css/theme-reference.css?v=20260913-batch16">
@endpush

@php
    $tokens = $selected?->token_payload ?: $defaults;
    $token = fn (string $path, mixed $fallback = '') => data_get($tokens, $path, $fallback);
    $statusLabel = fn (string $status): string => \Illuminate\Support\Str::headline($status);
    $statusTone = fn (string $status): string => match ($status) {
        'active' => 'healthy',
        'approved', 'pending_approval' => 'warning',
        'superseded' => 'neutral',
        'disabled' => 'danger',
        default => 'info',
    };
    $previewStyle = implode('; ', [
        '--theme-preview-primary: '.$token('colors.primary', '#075b2f'),
        '--theme-preview-secondary: '.$token('colors.secondary', '#0b1711'),
        '--theme-preview-accent: '.$token('colors.accent', '#7fbd42'),
        '--theme-preview-surface: '.$token('colors.surface', '#ffffff'),
        '--theme-preview-text: '.$token('colors.text', '#0b1711'),
        '--theme-preview-border: '.$token('colors.border', '#d9e3db'),
        '--theme-preview-radius: '.$token('spacing.radius', '12px'),
    ]);
@endphp

@section('content')
<div class="theme-page" data-theme-page data-theme-environment="{{$environment}}">
    <div class="theme-breadcrumb"><a href="{{route('admin.dashboard')}}">Project 1 Control Panel</a><span>›</span><a href="{{route('admin.settings.page', 'company-branding')}}">Company &amp; Branding</a><span>›</span><strong>Theme &amp; Colors</strong></div>
    <header class="theme-heading">
        <div class="theme-heading-copy"><span class="theme-heading-icon"><x-icon name="briefcase" size="22" /></span><div><span class="theme-eyebrow">PREMIUM THEME MANAGEMENT</span><h1>Theme &amp; Colors</h1><p>Versioned public tokens, approved assets and an audited activation lifecycle.</p></div></div>
        <div class="theme-heading-actions"><a class="theme-button theme-button--soft" href="{{route('admin.settings.page', ['section' => 'company-branding', 'tab' => 'theme'])}}"><x-icon name="arrow-left" size="14" /> Branding settings</a><a class="theme-button theme-button--primary" href="{{route('home')}}" target="_blank" rel="noopener"><x-icon name="eye" size="14" /> Open public preview</a></div>
    </header>
    <div class="theme-contract-note" role="status"><x-icon name="check" size="16" /><span><strong>One source of truth.</strong> Public routes consume only the active approved snapshot. Draft edits are preview-only until validation, approval and activation.</span></div>

    <div class="theme-layout">
        <section class="theme-card theme-versions-card">
            <div class="theme-card-heading"><div><h2>Theme versions</h2><p>Environment: <strong>{{$environment}}</strong> · Locale: <strong>{{$locale}}</strong></p></div><span class="theme-count">{{count($versions)}} version{{count($versions) === 1 ? '' : 's'}}</span></div>
            @if($versions->isEmpty())
                <div class="theme-empty"><x-icon name="settings" size="22" /><strong>No theme versions yet.</strong><span>Create a draft to begin the approved theme workflow.</span></div>
            @else
                <div class="theme-version-list" role="list">
                    @foreach($versions as $version)
                        <a class="theme-version-row {{$selected?->is($version) ? 'is-selected' : ''}}" href="{{route('admin.settings.theme.index', ['environment' => $version->environment, 'version' => $version->uuid])}}" role="listitem">
                            <span class="theme-version-mark theme-version-mark--{{$statusTone($version->status)}}"><x-icon name="palette" size="15" /></span><span class="theme-version-copy"><strong>{{$version->name}}</strong><small>v{{$version->version}} · {{$version->uuid}}</small></span><span class="theme-status theme-status--{{$statusTone($version->status)}}">{{$statusLabel($version->status)}}</span><span class="theme-version-arrow"><x-icon name="chevron-right" size="14" /></span>
                        </a>
                    @endforeach
                </div>
            @endif
            <div class="theme-card-footer"><span><x-icon name="file-text" size="14" /> Every lifecycle mutation is written to the audit log.</span><a href="{{route('admin.settings.page', ['section' => 'audit-logs', 'tab' => 'activity'])}}">View audit trail <x-icon name="arrow-right" size="13" /></a></div>
        </section>

        <section class="theme-card theme-preview-card">
            <div class="theme-card-heading"><div><h2>Render-faithful preview</h2><p>Safe live preview of the public shell token contract.</p></div><span class="theme-preview-badge"><i></i> {{$selected?->status === 'active' ? 'Active snapshot' : 'Draft preview'}}</span></div>
            <div class="theme-preview" data-theme-preview style="{{$previewStyle}}">
                <div class="theme-preview-top"><span class="theme-preview-logo">EMERALD ROZALIA</span><span>SHOP &nbsp; COLLECTIONS &nbsp; FRANCHISE</span><button type="button" aria-label="Preview menu"><x-icon name="menu" size="15" /></button></div>
                <div class="theme-preview-body"><span class="theme-eyebrow">IRISH MADE · LIMERICK BORN</span><h3>Headwear with character.</h3><p>Preview uses the same approved public token families and logo references used by the storefront shell.</p><div class="theme-preview-actions"><button type="button">Explore collection</button><button class="is-ghost" type="button">View story</button></div></div>
                <div class="theme-preview-footer"><span>Approved theme tokens</span><span>Footer contact policy retained</span></div>
            </div>
            <div class="theme-preview-meta"><span><b>Source</b>{{$selected ? 'Theme version '.$selected->version : 'Default theme fallback'}}</span><span><b>Public state</b>{{$selected?->status === 'active' ? 'Published' : 'Not published'}}</span><span><b>Motion</b>{{$token('motion.enabled', true) ? 'Enabled' : 'Disabled'}} · reduced-motion supported</span></div>
        </section>
    </div>

    @if($selected && $selected->validation_errors)
        <div class="theme-validation-errors" role="alert"><strong>Validation needs attention.</strong><ul>@foreach($selected->validation_errors as $path => $message)<li><code>{{$path}}</code> — {{$message}}</li>@endforeach</ul></div>
    @endif

    <div class="theme-editor-layout">
        @if(!$selected || $selected->status === 'draft')
            <section class="theme-card theme-editor-card">
                <div class="theme-card-heading"><div><h2>{{$selected ? 'Edit draft' : 'Create a theme draft'}}</h2><p>Only approved token fields are accepted. Custom CSS and unregistered assets are not supported.</p></div><span class="theme-status theme-status--info">Server persisted</span></div>
                <form method="POST" action="{{$selected ? route('admin.settings.theme.update', $selected) : route('admin.settings.theme.store')}}" data-theme-form>
                    @csrf
                    @if($selected) @method('PATCH') @endif
                    <input type="hidden" name="locale" value="{{$locale}}">
                    <div class="theme-form-grid theme-form-grid--identity"><label><span>Theme name</span><input required name="name" value="{{old('name', $selected?->name ?: 'Emerald Rozalia Premium Theme')}}"></label>@if($selected)<label><span>Environment</span><input value="{{$environment}}" readonly aria-describedby="theme-environment-note"><small id="theme-environment-note">Environment is immutable after version creation.</small></label>@else<label><span>Environment</span><select name="environment"><option value="production" @selected($environment === 'production')>Production</option><option value="staging" @selected($environment === 'staging')>Staging</option><option value="development" @selected($environment === 'development')>Development</option></select></label>@endif<label class="theme-field-wide"><span>Notes</span><textarea name="notes" rows="2" placeholder="Reason for this theme revision">{{old('notes', $selected?->notes)}}</textarea></label></div>
                    <div class="theme-token-groups">
                        <fieldset><legend>Colour system</legend><div class="theme-color-grid">
                            @foreach(['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent', 'surface' => 'Surface', 'surface_muted' => 'Muted surface', 'text' => 'Text', 'text_muted' => 'Muted text', 'border' => 'Border', 'focus' => 'Focus', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger', 'info' => 'Info'] as $key => $label)
                                <label><span>{{$label}}</span><span class="theme-color-input"><input type="color" name="tokens[colors][{{$key}}]" value="{{$token('colors.'.$key)}}" data-theme-token data-preview-variable="--theme-preview-{{in_array($key, ['primary', 'secondary', 'accent', 'surface', 'text', 'border'], true) ? $key : 'accent'}}"><input type="text" value="{{$token('colors.'.$key)}}" data-theme-color-text="tokens[colors][{{$key}}]" maxlength="7" pattern="#[0-9a-fA-F]{6}" aria-label="{{$label}} hexadecimal value"></span></label>
                            @endforeach
                        </div></fieldset>
                        <fieldset><legend>Typography, spacing &amp; controls</legend><div class="theme-form-grid">
                            <label><span>Body font stack</span><select name="tokens[typography][font_family]" data-theme-token><option value="Inter, Arial, sans-serif" @selected($token('typography.font_family') === 'Inter, Arial, sans-serif')>Inter / Arial</option><option value="system-ui, sans-serif" @selected($token('typography.font_family') === 'system-ui, sans-serif')>System UI</option></select></label><label><span>Heading font stack</span><select name="tokens[typography][heading_family]" data-theme-token><option value="Georgia, serif" @selected($token('typography.heading_family') === 'Georgia, serif')>Georgia</option><option value="Inter, Arial, sans-serif" @selected($token('typography.heading_family') === 'Inter, Arial, sans-serif')>Inter</option></select></label><label><span>Base size</span><input name="tokens[typography][base_size]" value="{{$token('typography.base_size')}}" data-theme-token pattern="\d{1,4}px"></label><label><span>Heading weight</span><select name="tokens[typography][heading_weight]" data-theme-token>@foreach([400,500,600,700,800,900] as $weight)<option value="{{$weight}}" @selected((int)$token('typography.heading_weight') === $weight)>{{$weight}}</option>@endforeach</select></label><label><span>Section vertical space</span><input name="tokens[spacing][section_y]" value="{{$token('spacing.section_y')}}" data-theme-token pattern="\d{1,4}px"></label><label><span>Container max width</span><input name="tokens[spacing][container_max]" value="{{$token('spacing.container_max')}}" data-theme-token pattern="\d{1,4}px"></label><label><span>Surface radius</span><input name="tokens[spacing][radius]" value="{{$token('spacing.radius')}}" data-theme-token pattern="\d{1,4}px"></label><label><span>Button radius</span><input name="tokens[controls][button_radius]" value="{{$token('controls.button_radius')}}" data-theme-token pattern="\d{1,4}px"></label><label><span>Input radius</span><input name="tokens[controls][input_radius]" value="{{$token('controls.input_radius')}}" data-theme-token pattern="\d{1,4}px"></label><label><span>Button height</span><input name="tokens[controls][button_height]" value="{{$token('controls.button_height')}}" data-theme-token pattern="\d{1,4}px"></label>
                        </div></fieldset>
                        <fieldset><legend>Motion, responsive &amp; variants</legend><div class="theme-form-grid"><label class="theme-toggle"><span><strong>Motion enabled</strong><small>Public transitions may animate when enabled.</small></span><input type="hidden" name="tokens[motion][enabled]" value="0"><input type="checkbox" name="tokens[motion][enabled]" value="1" @checked((bool)$token('motion.enabled')) data-theme-token></label><label class="theme-toggle"><span><strong>Reduced-motion support</strong><small>Respect the visitor preference at all times.</small></span><input type="hidden" name="tokens[motion][reduced_motion]" value="0"><input type="checkbox" name="tokens[motion][reduced_motion]" value="1" @checked((bool)$token('motion.reduced_motion')) data-theme-token></label><label><span>Motion duration (ms)</span><input type="number" min="0" max="1000" name="tokens[motion][duration_ms]" value="{{$token('motion.duration_ms')}}" data-theme-token></label><label><span>Easing</span><select name="tokens[motion][easing]" data-theme-token><option value="ease-out" @selected($token('motion.easing') === 'ease-out')>Ease out</option><option value="ease-in-out" @selected($token('motion.easing') === 'ease-in-out')>Ease in/out</option><option value="linear" @selected($token('motion.easing') === 'linear')>Linear</option></select></label><label><span>Mobile breakpoint</span><input name="tokens[breakpoints][mobile]" value="{{$token('breakpoints.mobile')}}" data-theme-token pattern="\d{1,4}px"></label><label><span>Tablet breakpoint</span><input name="tokens[breakpoints][tablet]" value="{{$token('breakpoints.tablet')}}" data-theme-token pattern="\d{1,4}px"></label><label><span>Desktop breakpoint</span><input name="tokens[breakpoints][desktop]" value="{{$token('breakpoints.desktop')}}" data-theme-token pattern="\d{1,4}px"></label><label><span>Button variant</span><select name="tokens[variants][button]" data-theme-token><option value="emerald" @selected($token('variants.button') === 'emerald')>Emerald</option><option value="outline" @selected($token('variants.button') === 'outline')>Outline</option></select></label><label><span>Card variant</span><select name="tokens[variants][card]" data-theme-token><option value="soft" @selected($token('variants.card') === 'soft')>Soft</option><option value="outlined" @selected($token('variants.card') === 'outlined')>Outlined</option></select></label><label><span>Header variant</span><select name="tokens[variants][header]" data-theme-token><option value="wordmark" @selected($token('variants.header') === 'wordmark')>Approved wordmark</option><option value="compact" @selected($token('variants.header') === 'compact')>Compact wordmark</option></select></label></div></fieldset>
                    </div>
                    <div class="theme-form-footer"><span><x-icon name="lock" size="14" /> Approved tokens only · no custom CSS</span><button class="theme-button theme-button--primary" type="submit"><x-icon name="check" size="14" /> {{$selected ? 'Save draft' : 'Create draft'}}</button></div>
                </form>
            </section>
        @else
            <section class="theme-card theme-snapshot-card"><div class="theme-card-heading"><div><h2>Immutable snapshot</h2><p>Version {{$selected->version}} is {{$statusLabel($selected->status)}} and cannot be edited in place.</p></div><span class="theme-status theme-status--{{$statusTone($selected->status)}}">{{$statusLabel($selected->status)}}</span></div><div class="theme-snapshot-grid"><div><strong>Theme UUID</strong><code>{{$selected->uuid}}</code></div><div><strong>Created</strong><span>{{$selected->created_at?->format('d M Y, H:i') ?: 'Not recorded'}}</span></div><div><strong>Activated</strong><span>{{$selected->activated_at?->format('d M Y, H:i') ?: 'Not activated'}}</span></div><div><strong>Source version</strong><span>{{$selected->source_version_uuid ?: 'Original composition'}}</span></div></div><div class="theme-readonly-note"><x-icon name="info" size="15" /><span>Create a new draft for further edits. Activation will supersede the current active version atomically.</span></div></section>
        @endif

        <aside class="theme-side-column">
            <section class="theme-card theme-actions-card"><div class="theme-card-heading"><div><h2>Lifecycle actions</h2><p>Each action is state-aware and audited.</p></div><x-icon name="refresh" size="17" /></div>
                @if(!$selected)<p class="theme-side-empty">Create a draft to unlock validation and approval actions.</p>@else
                    <div class="theme-action-list">
                        @if($selected->status === 'draft')<form method="POST" action="{{route('admin.settings.theme.action', ['theme' => $selected, 'action' => 'validate'])}}">@csrf<button type="submit"><x-icon name="check" size="14" /> Validate tokens</button></form>@if($selected->validated_at)<form method="POST" action="{{route('admin.settings.theme.action', ['theme' => $selected, 'action' => 'submit'])}}">@csrf<button type="submit"><x-icon name="arrow-right" size="14" /> Request approval</button></form>@endif
                        @elseif($selected->status === 'pending_approval')<form method="POST" action="{{route('admin.settings.theme.action', ['theme' => $selected, 'action' => 'approve'])}}">@csrf<button type="submit"><x-icon name="check" size="14" /> Approve revision</button></form>
                        @elseif($selected->status === 'approved')<form method="POST" action="{{route('admin.settings.theme.action', ['theme' => $selected, 'action' => 'activate'])}}">@csrf<button type="submit" data-theme-confirm="Activate this theme for the public routes?"><x-icon name="check" size="14" /> Activate publicly</button></form><form method="POST" action="{{route('admin.settings.theme.action', ['theme' => $selected, 'action' => 'disable'])}}">@csrf<button type="submit" class="is-danger" data-theme-confirm="Disable this approved theme version?"><x-icon name="minus" size="14" /> Disable revision</button></form>
                        @elseif($selected->status === 'active')@if(count($versions) > 1)<form method="POST" action="{{route('admin.settings.theme.action', ['theme' => $selected, 'action' => 'rollback'])}}">@csrf<button type="submit" data-theme-confirm="Create a new active version from the previous approved snapshot?"><x-icon name="rotate-ccw" size="14" /> Roll back previous</button></form>@endif<form method="POST" action="{{route('admin.settings.theme.action', ['theme' => $selected, 'action' => 'disable'])}}">@csrf<button type="submit" class="is-danger" data-theme-confirm="Disable the active theme? Public routes will use the safe default fallback."><x-icon name="minus" size="14" /> Disable active theme</button></form>
                        @elseif($selected->status === 'superseded')<form method="POST" action="{{route('admin.settings.theme.action', ['theme' => $selected, 'action' => 'rollback'])}}">@csrf<button type="submit" data-theme-confirm="Create a new active version from this snapshot?"><x-icon name="rotate-ccw" size="14" /> Roll back to this version</button></form>@endif
                    </div>
                @endif
            </section>
            <section class="theme-card theme-assets-card"><div class="theme-card-heading"><div><h2>Approved brand assets</h2><p>References are restricted to the approved Emerald Rozalia identity.</p></div><x-icon name="image" size="17" /></div><div class="theme-assets-list">@foreach($assetReferences as $asset)<div><span class="theme-asset-icon"><x-icon name="image" size="14" /></span><span><strong>{{$asset['role']}}</strong><code>{{$asset['path']}}</code></span><span class="theme-status theme-status--healthy">Approved</span></div>@endforeach</div><p class="theme-assets-note">Public media picker and UUID-backed derivatives are delivered in Batch 19. This theme cannot register a private filesystem path.</p></section>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
    <script src="/js/theme-reference.js?v=20260913-batch16" defer></script>
@endpush
