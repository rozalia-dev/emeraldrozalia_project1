<!doctype html>
@php($factorySettings = app(\App\Services\PublishedSiteSettings::class)->forCompany())
@php($factoryTheme = data_get($factorySettings, 'theme', []))
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" data-public-theme-source="{{ data_get($factorySettings, 'theme_meta.source', 'default-theme-fallback') }}" data-public-theme-version="{{ data_get($factorySettings, 'theme_meta.version', 0) }}" data-public-theme-contract="global">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Emerald Rozalia')</title>
    <meta name="description" content="Emerald Rozalia — how our Limerick-made hats and caps are crafted.">
    <link rel="stylesheet" href="/css/app.css?v=20260904-interactions">
    <link rel="stylesheet" href="/css/theme-runtime.css?v=20260915-global-theme">
    <link rel="stylesheet" href="/css/factory.css?v=20260914-public-media-contract">
</head>
<body class="factory-page-body factory-reference-body" style="--site-brand-primary: {{ data_get($factoryTheme, 'colors.primary', '#075b2f') }}; --site-brand-secondary: {{ data_get($factoryTheme, 'colors.secondary', '#0b1711') }}; --site-brand-accent: {{ data_get($factoryTheme, 'colors.accent', '#7fbd42') }}; --site-surface: {{ data_get($factoryTheme, 'colors.surface', '#ffffff') }}; --site-surface-muted: {{ data_get($factoryTheme, 'colors.surface_muted', '#f3f6f2') }}; --site-text: {{ data_get($factoryTheme, 'colors.text', '#0b1711') }}; --site-text-muted: {{ data_get($factoryTheme, 'colors.text_muted', '#5c6c62') }}; --site-border: {{ data_get($factoryTheme, 'colors.border', '#d9e3db') }}; --site-focus: {{ data_get($factoryTheme, 'colors.focus', '#7fbd42') }}; --site-success: {{ data_get($factoryTheme, 'colors.success', '#16784a') }}; --site-warning: {{ data_get($factoryTheme, 'colors.warning', '#b7791f') }}; --site-danger: {{ data_get($factoryTheme, 'colors.danger', '#b42318') }}; --site-info: {{ data_get($factoryTheme, 'colors.info', '#2676cc') }}; --site-font-family: {{ data_get($factoryTheme, 'typography.font_family', 'Inter, Arial, sans-serif') }}; --site-heading-family: {{ data_get($factoryTheme, 'typography.heading_family', 'Georgia, serif') }}; --site-heading-weight: {{ data_get($factoryTheme, 'typography.heading_weight', 700) }}; --site-base-size: {{ data_get($factoryTheme, 'typography.base_size', '16px') }}; --site-section-y: {{ data_get($factoryTheme, 'spacing.section_y', '64px') }}; --site-container-max: {{ data_get($factoryTheme, 'spacing.container_max', '1280px') }}; --site-radius: {{ data_get($factoryTheme, 'spacing.radius', '12px') }}; --site-button-radius: {{ data_get($factoryTheme, 'controls.button_radius', '10px') }}; --site-input-radius: {{ data_get($factoryTheme, 'controls.input_radius', '8px') }}; --site-button-height: {{ data_get($factoryTheme, 'controls.button_height', '44px') }}; --site-motion-duration: {{ data_get($factoryTheme, 'motion.duration_ms', 180) }}ms; --site-motion-easing: {{ data_get($factoryTheme, 'motion.easing', 'ease-out') }};">
@if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash error">{{ implode(' ',$errors->all()) }}</div>@endif
<main>@yield('content')</main>
<script src="/js/app.js"></script>
</body>
</html>
