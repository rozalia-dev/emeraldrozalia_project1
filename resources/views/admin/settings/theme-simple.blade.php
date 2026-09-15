@extends('layouts.admin')
@section('title','Theme & Appearance')
@push('styles')
<link rel="stylesheet" href="/css/theme-simple.css?v=20260915-v1">
@endpush
@section('content')
@php
    $themeTokens = $effectiveTokens ?? $defaults;
    $activeIsDefault = $active && $active->name === 'Emerald Rozalia Default';
    $publicStatus = $active ? 'Live' : 'Default · Live';
@endphp
<section class="theme-simple" data-theme-manager>
    <div class="theme-simple__heading">
        <div>
            <p class="theme-simple__eyebrow">COMPANY &amp; BRANDING / THEME &amp; COLORS</p>
            <h1>Theme &amp; Appearance</h1>
            <p>Change the public website theme in one clear action. <strong>Apply theme globally</strong> saves, validates and publishes the new version immediately while keeping the full audit history in the background.</p>
        </div>
        <div class="theme-simple__status" aria-label="Public theme status">
            <span class="theme-simple__status-dot" aria-hidden="true"></span>
            <span><small>PUBLIC WEBSITE</small><strong>{{ $publicStatus }}</strong></span>
        </div>
    </div>

    <div class="theme-simple__notice" role="note">
        <x-icon name="info" size="18" />
        <div><strong>Simple workflow</strong><span>Edit → Apply globally. There is no separate draft, approval and publish sequence in the normal UI. Reset restores the Emerald Rozalia baseline theme.</span></div>
    </div>

    <div class="theme-simple__grid">
        <div class="theme-simple__main">
            <section class="theme-simple__card">
                <header class="theme-simple__card-head">
                    <div>
                        <h2>Public website theme</h2>
                        <p>These settings are delivered through the shared public theme contract to the storefront, account/auth pages and the dedicated factory page.</p>
                    </div>
                    <span class="theme-simple__pill {{ $active ? 'is-live' : 'is-default' }}">{{ $active ? ($activeIsDefault ? 'DEFAULT · LIVE' : 'CUSTOM · LIVE') : 'DEFAULT · LIVE' }}</span>
                </header>

                <form method="post" action="{{ route('admin.settings.theme.apply') }}" class="theme-simple__form">
                    @csrf
                    <input type="hidden" name="environment" value="{{ $environment }}">
                    <input type="hidden" name="locale" value="{{ $locale }}">
                    <div class="theme-simple__fields theme-simple__fields--top">
                        <label><span>Theme name</span><input name="name" value="{{ old('name', $active?->name ?: 'Emerald Rozalia Theme') }}" maxlength="180" required></label>
                        <label><span>Environment</span><input value="{{ ucfirst($environment) }}" disabled><small>Only the selected environment is affected.</small></label>
                    </div>

                    <fieldset>
                        <legend>Brand colours</legend>
                        <p class="theme-simple__help">Use six-digit HEX colours. Changes are published together so the website never receives a half-saved theme.</p>
                        <div class="theme-simple__color-grid">
                            @foreach(['primary'=>'Primary','secondary'=>'Secondary','accent'=>'Accent','surface'=>'Surface','surface_muted'=>'Muted surface','text'=>'Text','text_muted'=>'Muted text','border'=>'Border','focus'=>'Focus','success'=>'Success','warning'=>'Warning','danger'=>'Danger','info'=>'Info'] as $key=>$label)
                                @php($colorValue = old('tokens.colors.'.$key, data_get($themeTokens, 'colors.'.$key)))
                                <label class="theme-simple__color"><span>{{ $label }}</span><span class="theme-simple__color-control"><input type="color" value="{{ $colorValue }}" data-color-picker><input name="tokens[colors][{{ $key }}]" value="{{ $colorValue }}" pattern="#[0-9A-Fa-f]{6}" maxlength="7" data-color-text required></span></label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Typography &amp; layout</legend>
                        <div class="theme-simple__fields">
                            <label><span>Body font</span><input name="tokens[typography][font_family]" value="{{ old('tokens.typography.font_family', data_get($themeTokens,'typography.font_family')) }}" required></label>
                            <label><span>Heading font</span><input name="tokens[typography][heading_family]" value="{{ old('tokens.typography.heading_family', data_get($themeTokens,'typography.heading_family')) }}" required></label>
                            <label><span>Base size</span><input name="tokens[typography][base_size]" value="{{ old('tokens.typography.base_size', data_get($themeTokens,'typography.base_size')) }}" required></label>
                            <label><span>Heading weight</span><select name="tokens[typography][heading_weight]">@foreach([400,500,600,700,800,900] as $weight)<option value="{{ $weight }}" @selected((int)old('tokens.typography.heading_weight',data_get($themeTokens,'typography.heading_weight'))===$weight)>{{ $weight }}</option>@endforeach</select></label>
                            <label><span>Section spacing</span><input name="tokens[spacing][section_y]" value="{{ old('tokens.spacing.section_y', data_get($themeTokens,'spacing.section_y')) }}" required></label>
                            <label><span>Container max width</span><input name="tokens[spacing][container_max]" value="{{ old('tokens.spacing.container_max', data_get($themeTokens,'spacing.container_max')) }}" required></label>
                            <label><span>Spacing unit</span><input name="tokens[spacing][unit]" value="{{ old('tokens.spacing.unit', data_get($themeTokens,'spacing.unit')) }}" required></label>
                            <label><span>General radius</span><input name="tokens[spacing][radius]" value="{{ old('tokens.spacing.radius', data_get($themeTokens,'spacing.radius')) }}" required></label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Controls, motion &amp; responsive rules</legend>
                        <div class="theme-simple__fields">
                            <label><span>Button radius</span><input name="tokens[controls][button_radius]" value="{{ old('tokens.controls.button_radius', data_get($themeTokens,'controls.button_radius')) }}" required></label>
                            <label><span>Input radius</span><input name="tokens[controls][input_radius]" value="{{ old('tokens.controls.input_radius', data_get($themeTokens,'controls.input_radius')) }}" required></label>
                            <label><span>Button height</span><input name="tokens[controls][button_height]" value="{{ old('tokens.controls.button_height', data_get($themeTokens,'controls.button_height')) }}" required></label>
                            <label><span>Motion duration (ms)</span><input type="number" min="0" max="1000" name="tokens[motion][duration_ms]" value="{{ old('tokens.motion.duration_ms', data_get($themeTokens,'motion.duration_ms')) }}" required></label>
                            <label><span>Motion easing</span><input name="tokens[motion][easing]" value="{{ old('tokens.motion.easing', data_get($themeTokens,'motion.easing')) }}" required></label>
                            <label><span>Mobile breakpoint</span><input name="tokens[breakpoints][mobile]" value="{{ old('tokens.breakpoints.mobile', data_get($themeTokens,'breakpoints.mobile')) }}" required></label>
                            <label><span>Tablet breakpoint</span><input name="tokens[breakpoints][tablet]" value="{{ old('tokens.breakpoints.tablet', data_get($themeTokens,'breakpoints.tablet')) }}" required></label>
                            <label><span>Desktop breakpoint</span><input name="tokens[breakpoints][desktop]" value="{{ old('tokens.breakpoints.desktop', data_get($themeTokens,'breakpoints.desktop')) }}" required></label>
                            <label><span>Button variant</span><input name="tokens[variants][button]" value="{{ old('tokens.variants.button', data_get($themeTokens,'variants.button')) }}" required></label>
                            <label><span>Card variant</span><input name="tokens[variants][card]" value="{{ old('tokens.variants.card', data_get($themeTokens,'variants.card')) }}" required></label>
                            <label><span>Header variant</span><input name="tokens[variants][header]" value="{{ old('tokens.variants.header', data_get($themeTokens,'variants.header')) }}" required></label>
                        </div>
                        <div class="theme-simple__checks">
                            <input type="hidden" name="tokens[motion][enabled]" value="0"><label><input type="checkbox" name="tokens[motion][enabled]" value="1" @checked((bool)old('tokens.motion.enabled',data_get($themeTokens,'motion.enabled')))><span>Enable motion</span></label>
                            <input type="hidden" name="tokens[motion][reduced_motion]" value="0"><label><input type="checkbox" name="tokens[motion][reduced_motion]" value="1" @checked((bool)old('tokens.motion.reduced_motion',data_get($themeTokens,'motion.reduced_motion')))><span>Respect reduced-motion preference</span></label>
                        </div>
                    </fieldset>

                    <label class="theme-simple__notes"><span>Change note <small>(optional)</small></span><textarea name="notes" rows="3" maxlength="2000" placeholder="Why are you changing the theme?">{{ old('notes') }}</textarea></label>

                    <div class="theme-simple__actions">
                        <button class="theme-simple__primary" type="submit"><x-icon name="check" size="16" /> Apply theme globally</button>
                        <span>Creates one audited version and makes it live immediately.</span>
                    </div>
                </form>

                <form method="post" action="{{ route('admin.settings.theme.reset-default') }}" class="theme-simple__reset" onsubmit="return confirm('Reset the public website to the Emerald Rozalia default theme?');">
                    @csrf
                    <input type="hidden" name="environment" value="{{ $environment }}">
                    <button type="submit"><x-icon name="refresh" size="15" /> Reset to default</button>
                    <span>Restores the approved Emerald Rozalia baseline colours, typography, spacing and controls.</span>
                </form>
            </section>

            <section class="theme-simple__card">
                <header class="theme-simple__card-head"><div><h2>cPanel appearance</h2><p>Separate from the public website. Changing this only changes the administration interface.</p></div><span class="theme-simple__pill">ADMIN ONLY</span></header>
                <form method="post" action="{{ route('admin.settings.cpanel-theme.update') }}" class="theme-simple__appearance">
                    @csrf
                    @foreach($cpanelThemeOptions as $value=>$label)
                        <label class="theme-simple__appearance-option"><input type="radio" name="cpanel_theme" value="{{ $value }}" @checked($cpanelTheme===$value)><span><strong>{{ $label }}</strong><small>{{ $value === 'guide-dark' ? 'Default guide-aligned dark cPanel shell and header.' : 'Light cPanel canvas with the same structure and accessibility behaviour.' }}</small></span></label>
                    @endforeach
                    <button type="submit">Apply cPanel theme</button>
                </form>
            </section>
        </div>

        <aside class="theme-simple__side">
            <section class="theme-simple__card theme-simple__summary">
                <h2>Current status</h2>
                <dl>
                    <div><dt>Public theme</dt><dd>{{ $active?->name ?: 'Emerald Rozalia Default' }}</dd></div>
                    <div><dt>Status</dt><dd><span class="theme-simple__pill is-live">{{ $active ? 'LIVE' : 'DEFAULT · LIVE' }}</span></dd></div>
                    <div><dt>Version</dt><dd>{{ $active?->version ?: 'Baseline' }}</dd></div>
                    <div><dt>Environment</dt><dd>{{ ucfirst($environment) }}</dd></div>
                    <div><dt>Locale</dt><dd>{{ strtoupper($locale) }}</dd></div>
                    <div><dt>cPanel</dt><dd>{{ $cpanelThemeOptions[$cpanelTheme] ?? $cpanelTheme }}</dd></div>
                </dl>
            </section>

            <section class="theme-simple__card">
                <h2>Version history</h2>
                @if($versions->isEmpty())
                    <p class="theme-simple__empty">No custom versions yet. The built-in Emerald Rozalia default theme is live.</p>
                @else
                    <div class="theme-simple__history">
                        @foreach($versions as $version)
                            <div class="theme-simple__history-row {{ $version->status === 'active' ? 'is-active' : '' }}">
                                <a href="{{ route('admin.settings.theme.index',['environment'=>$environment,'version'=>$version->uuid]) }}"><strong>v{{ $version->version }} · {{ $version->name }}</strong><span>{{ ucfirst(str_replace('_',' ',$version->status)) }} · {{ $version->created_at?->format('d M Y H:i') }}</span></a>
                                @if(in_array($version->status,['superseded','approved'],true))
                                    <form method="post" action="{{ route('admin.settings.theme.action',['theme'=>$version,'action'=>'rollback']) }}">@csrf<button type="submit">Restore</button></form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="theme-simple__card theme-simple__contract">
                <h2>What “global” means</h2>
                <p>The active public snapshot supplies the same theme tokens to the shared storefront shell, customer/auth layouts and the dedicated factory shell. cPanel appearance remains independent.</p>
                <a href="{{ route('admin.site-media.index') }}">Open Public Media Library <x-icon name="arrow-right" size="13" /></a>
            </section>
        </aside>
    </div>
</section>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-color-picker]').forEach((picker)=>{const text=picker.parentElement?.querySelector('[data-color-text]');picker.addEventListener('input',()=>{if(text)text.value=picker.value});text?.addEventListener('input',()=>{if(/^#[0-9a-f]{6}$/i.test(text.value))picker.value=text.value})});
</script>
@endpush
