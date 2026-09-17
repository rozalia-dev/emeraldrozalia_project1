@php
    $tokens = (array) data_get($cpanelThemeSnapshot ?? [], 'tokens', []);
    $meta = (array) data_get($cpanelThemeSnapshot ?? [], 'meta', []);
    $source = (string) data_get($meta, 'source', 'default-cpanel-theme-fallback');
    $isActiveTheme = $source === 'active-cpanel-theme-version';
    $token = fn (string $path, mixed $fallback) => data_get($tokens, $path, $fallback);
@endphp
<style id="cpanel-theme-runtime" data-cpanel-theme-source="{{$source}}" data-cpanel-theme-version="{{data_get($meta, 'version', 0)}}">
@if($isActiveTheme)
:root{
    --cpanel-sidebar-start:{{$token('colors.sidebar_start', '#052617')}};
    --cpanel-sidebar-end:{{$token('colors.sidebar_end', '#02170e')}};
    --cpanel-sidebar-text:{{$token('colors.sidebar_text', '#ffffff')}};
    --cpanel-topbar:{{$token('colors.topbar', '#063020')}};
    --cpanel-topbar-text:{{$token('colors.topbar_text', '#ffffff')}};
    --cpanel-accent:{{$token('colors.accent', '#7fbd42')}};
    --cpanel-background:{{$token('colors.background', '#f3f5f1')}};
    --cpanel-surface:{{$token('colors.surface', '#ffffff')}};
    --cpanel-text:{{$token('colors.text', '#15221b')}};
    --cpanel-muted:{{$token('colors.muted', '#647168')}};
    --cpanel-border:{{$token('colors.border', '#d8dfda')}};
    --cpanel-focus:{{$token('colors.focus', '#7fbd42')}};
    --cpanel-success:{{$token('colors.success', '#16784a')}};
    --cpanel-warning:{{$token('colors.warning', '#b7791f')}};
    --cpanel-danger:{{$token('colors.danger', '#b42318')}};
    --cpanel-info:{{$token('colors.info', '#2676cc')}};
    --cpanel-font:{{$token('typography.font_family', 'Inter, Arial, sans-serif')}};
    --cpanel-heading-font:{{$token('typography.heading_family', 'Inter, Arial, sans-serif')}};
    --cpanel-base-size:{{$token('typography.base_size', '14px')}};
    --cpanel-radius:{{$token('spacing.radius', '8px')}};
    --cpanel-sidebar-width:{{$token('spacing.sidebar_width', '245px')}};
    --cpanel-button-radius:{{$token('controls.button_radius', '6px')}};
    --cpanel-input-radius:{{$token('controls.input_radius', '6px')}};
}
.admin-body{background:var(--cpanel-background);color:var(--cpanel-text);font-family:var(--cpanel-font);font-size:var(--cpanel-base-size)}
.admin-sidebar{width:var(--cpanel-sidebar-width);background:linear-gradient(180deg,var(--cpanel-sidebar-start),var(--cpanel-sidebar-end));color:var(--cpanel-sidebar-text)}
.admin-shell{margin-left:var(--cpanel-sidebar-width)}
.admin-sidebar a,.admin-sidebar summary{color:var(--cpanel-sidebar-text);border-radius:var(--cpanel-input-radius)}
.admin-sidebar a:hover,.admin-sidebar summary:hover{background:color-mix(in srgb,var(--cpanel-accent) 20%,transparent);color:var(--cpanel-sidebar-text)}
.admin-sidebar a.active,.admin-sidebar details a.active{background:var(--cpanel-accent);color:var(--cpanel-sidebar-end)}
.admin-sidebar small,.admin-sidebar-footer{color:var(--cpanel-sidebar-text);opacity:.76}
.admin-top{background:var(--cpanel-topbar);color:var(--cpanel-topbar-text);border-color:var(--cpanel-border)}
.admin-top .admin-actions,.admin-top .admin-actions span,.admin-top .admin-user{color:var(--cpanel-topbar-text)}
.admin-main{background:var(--cpanel-background);color:var(--cpanel-text)}
.admin-footer{background:var(--cpanel-surface);color:var(--cpanel-muted);border-color:var(--cpanel-border)}
.admin-body .panel,.admin-body .admin-card,.admin-body .theme-card,.admin-body .settings-card{background:var(--cpanel-surface);border-color:var(--cpanel-border);border-radius:var(--cpanel-radius);color:var(--cpanel-text)}
.admin-body h1,.admin-body h2,.admin-body h3,.admin-body h4{font-family:var(--cpanel-heading-font);color:var(--cpanel-text)}
.admin-body p,.admin-body small,.admin-body .muted{color:var(--cpanel-muted)}
.admin-body input,.admin-body select,.admin-body textarea{border-color:var(--cpanel-border);border-radius:var(--cpanel-input-radius);background:var(--cpanel-surface);color:var(--cpanel-text)}
.admin-body input:focus,.admin-body select:focus,.admin-body textarea:focus,.admin-body button:focus-visible,.admin-body a:focus-visible{outline-color:var(--cpanel-focus)}
.admin-body .btn,.admin-body button{border-radius:var(--cpanel-button-radius)}
.admin-body .flash.success{background:var(--cpanel-success);color:#fff}
.admin-body .flash.error{background:var(--cpanel-danger);color:#fff}
@media(max-width:980px){.admin-shell{margin-left:0}}
@media(max-width:700px){.admin-sidebar{width:100%}}
@endif
</style>

@if($isActiveTheme)
<script id="cpanel-branding-runtime">
document.addEventListener('DOMContentLoaded', () => {
    const logo = document.querySelector('.admin-logo-image');
    if (!logo) return;
    logo.src = @json(asset(ltrim((string) $token('branding.logo_path', '/assets/logo/logo_two_line.png'), '/')));
    logo.alt = @json((string) $token('branding.logo_alt', 'Emerald Rozalia Limited'));
});
</script>
@endif
