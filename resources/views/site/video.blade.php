@extends('layouts.site')
@section('title', data_get($video->metadata,'seo_title') ?: $video->title)
@section('content')
<section style="max-width:1080px;margin:32px auto;padding:24px">
    <a href="{{ route('product',$video->product) }}">← {{ $video->product->name }}</a>
    <h1>{{ $video->title }}</h1>
    <p>{{ data_get($video->metadata,'description') }}</p>
    <x-video-player :video="$video" />
</section>
@endsection
