<!doctype html>
@php($factorySettings = app(\App\Services\PublishedSiteSettings::class)->forCompany())
@php($factoryTheme = data_get($factorySettings, 'theme', []))
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" data-public-theme-source="{{ data_get($factorySettings, 'theme_meta.source', 'default-theme-fallback') }}" data-public-theme-version="{{ data_get($factorySettings, 'theme_meta.version', 0) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Emerald Rozalia')</title>
    <meta name="description" content="Emerald Rozalia — how our Limerick-made hats and caps are crafted.">
    <link rel="stylesheet" href="/css/app.css?v=20260904-interactions">
    <link rel="stylesheet" href="/css/theme-runtime.css?v=20260913-batch17-typography">
</head>
<body class="factory-reference-body" style="--site-font-family: {{ data_get($factoryTheme, 'typography.font_family', 'Inter, Arial, sans-serif') }}; --site-heading-family: {{ data_get($factoryTheme, 'typography.heading_family', 'Georgia, serif') }}; --site-heading-weight: {{ data_get($factoryTheme, 'typography.heading_weight', 700) }}; --site-base-size: {{ data_get($factoryTheme, 'typography.base_size', '16px') }}; --site-input-radius: {{ data_get($factoryTheme, 'controls.input_radius', '8px') }}; --site-button-radius: {{ data_get($factoryTheme, 'controls.button_radius', '10px') }}; --site-button-height: {{ data_get($factoryTheme, 'controls.button_height', '44px') }}; --site-motion-duration: {{ data_get($factoryTheme, 'motion.duration_ms', 180) }}ms; --site-motion-easing: {{ data_get($factoryTheme, 'motion.easing', 'ease-out') }}; --site-focus: {{ data_get($factoryTheme, 'colors.focus', '#7fbd42') }};">
@if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash error">{{ implode(' ',$errors->all()) }}</div>@endif
<main>@yield('content')</main>
<script src="/js/app.js"></script>
</body>
</html>
