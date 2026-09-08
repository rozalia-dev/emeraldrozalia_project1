@extends('layouts.admin')

@section('title', 'Search')

@section('content')
<div class="admin-title">
    <div>
        <small>ADMIN / SEARCH</small>
        <h1>Search everything</h1>
        <p class="admin-page-description">Search products, orders, franchise activity, retail stores and Communication Center records.</p>
    </div>
</div>

<section class="panel admin-search-page">
    <form class="admin-global-search-form" method="get" action="{{ route('admin.search') }}">
        <label for="admin-search-query">Search the control panel</label>
        <div>
            <input id="admin-search-query" type="search" name="q" value="{{ $q }}" placeholder="Name, SKU, order number, email or reference" autofocus>
            <button class="btn" type="submit">SEARCH</button>
        </div>
    </form>
</section>

@if($q === '')
    <section class="panel admin-search-empty"><strong>Start with a name, reference, SKU, order number or email.</strong><span>Your results stay within the authenticated Project 1 cPanel.</span></section>
@elseif($results === [])
    <section class="panel admin-search-empty"><strong>No results for “{{ $q }}”.</strong><span>Try a shorter search term or a different reference.</span></section>
@else
    @foreach($results as $group => $items)
        <section class="panel admin-search-results">
            <div class="panel-heading"><h2>{{ $group }}</h2><span class="panel-caption">{{ count($items) }} result{{ count($items) === 1 ? '' : 's' }}</span></div>
            <div class="admin-search-result-grid">
                @foreach($items as $item)
                    <a class="admin-search-result" href="{{ $item['href'] }}">
                        <span><x-icon name="{{ $item['icon'] }}" size="18" /></span>
                        <strong>{{ $item['label'] }}</strong>
                        <small>{{ $item['detail'] }}</small>
                        <x-icon name="arrow-right" size="14" />
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
@endif
@endsection
