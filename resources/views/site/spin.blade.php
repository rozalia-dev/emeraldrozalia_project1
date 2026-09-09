@extends('layouts.site')
@section('title',$spin->seo['title'] ?? $spin->title)
@section('content')
<link rel="stylesheet" href="/css/spins.css">
<section class="sv-page"><a href="{{ route('product',$spin->product->slug) }}">← {{ $spin->product->name }}</a>
<h1>{{ $spin->title }}</h1>
@if(!$spin->isPublic())<p>This is a private administrator preview. It is not publicly published.</p>@endif
<x-spin-viewer :spin="$spin" />
</section>
<script src="/js/spin-viewer.js" defer></script>
@endsection
