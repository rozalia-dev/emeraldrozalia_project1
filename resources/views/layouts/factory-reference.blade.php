<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Emerald Rozalia')</title>
    <meta name="description" content="Emerald Rozalia — how our Limerick-made hats and caps are crafted.">
    <link rel="stylesheet" href="/css/app.css?v=20260904-how-we-work-reference">
</head>
<body class="factory-reference-body">
@if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash error">{{ implode(' ',$errors->all()) }}</div>@endif
<main>@yield('content')</main>
<script src="/js/app.js"></script>
</body>
</html>
