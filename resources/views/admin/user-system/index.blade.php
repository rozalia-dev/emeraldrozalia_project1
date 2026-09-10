@extends('layouts.admin')
@section('title', $title)
@push('styles')
<link rel="stylesheet" href="/css/user-system-admin.css?v=20260910-4">
@endpush
@push('scripts')
<script src="/js/user-system-admin.js?v=20260910-4"></script>
@endpush

@section('content')
<div class="us-page">
    <div class="us-breadcrumb">
        <span>Project 1 Control Panel (cPanel)</span><span>›</span><span>Users &amp; System</span><span>›</span><span>Users &amp; Roles</span><span>›</span><b>{{ $title }}</b>
    </div>
    <section class="us-titlebar">
        <div class="us-title-left">
            <span class="us-title-icon"><x-icon name="users" size="20" /></span>
            <div><h1>{{ $title }}</h1><p>{{ $subtitle }}</p></div>
        </div>
    </section>
    @isset($metrics)
        <div class="us-metrics">
            @foreach ($metrics as $metric)
                <article class="us-metric">
                    <span class="us-metric-icon tone-{{ $metric['tone'] }}"><x-icon name="users" size="18" /></span>
                    <div><small>{{ $metric['label'] }}</small><strong>{{ number_format((float) $metric['value']) }}</strong><em>{{ $metric['sub'] }}</em></div>
                </article>
            @endforeach
        </div>
    @endisset
    @include('admin.user-system.pages.'.$page)
</div>
@endsection
