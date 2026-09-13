@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
@php
    $money=function($value){return '€'.number_format((float)$value,2);};
@endphp
<div class="dashboard-page" data-dashboard-page data-dashboard-selected-period="{{ $period }}">
    <header class="dashboard-welcome">
        <div class="dashboard-welcome-copy">
            <h1>Welcome back, Admin! <span aria-hidden="true">👋</span></h1>
            <p>Here's what's happening in your franchise business today.</p>
        </div>
        <time class="dashboard-date-card" datetime="{{now()->toDateString()}}">
            <x-icon name="calendar" size="24" />
            <span><b>{{now()->format('l, j F Y')}}</b><strong>{{now()->format('g:i A')}}</strong></span>
        </time>
    </header>

    <section class="dashboard-kpi-grid" aria-label="Business performance indicators">
        @foreach($dashboardKpis as $kpi)
            <a class="dashboard-kpi dashboard-tone-{{$kpi['tone']}}" href="{{$kpi['href']}}" data-dashboard-kpi>
                <span class="dashboard-kpi-icon"><x-icon name="{{$kpi['icon']}}" size="25" /></span>
                <span class="dashboard-kpi-body">
                    <span class="dashboard-kpi-label">{{$kpi['label']}} @if(isset($kpi['context']))<small>({{$kpi['context']}})</small>@endif</span>
                    <strong data-dashboard-animated-number data-dashboard-value="{{ $kpi['value'] }}" data-dashboard-format="{{ !empty($kpi['currency']) ? 'currency' : 'number' }}">@if(!empty($kpi['currency'])){{$money($kpi['value'])}}@else{{number_format($kpi['value'])}}@endif</strong>
                    <span class="dashboard-kpi-trend dashboard-trend-{{$kpi['trend']}}">@if($kpi['trend']==='up')<x-icon name="arrow-up" size="12" />@elseif($kpi['trend']==='down')<x-icon name="arrow-down" size="12" />@else<x-icon name="minus" size="12" />@endif {{$kpi['change']}} <small>{{$kpi['comparison']}}</small></span>
                </span>
            </a>
        @endforeach
    </section>

    <section class="dashboard-analytics-grid" aria-label="Business analytics">
        <article class="dashboard-card dashboard-pipeline-card">
            <header class="dashboard-card-heading"><h2>Franchise Pipeline Overview</h2><a href="{{route('admin.franchise.page',['section'=>'franchise-applications'])}}">View details <x-icon name="arrow-right" size="14" /></a></header>
            <div class="dashboard-pipeline-body">
                <div class="dashboard-funnel" aria-label="Franchise pipeline funnel">
                    @foreach($pipelineStages as $stage)<a class="dashboard-funnel-stage dashboard-tone-{{$stage['tone']}}" href="{{$stage['href']}}" style="width:{{$stage['width']}}%" aria-label="{{$stage['label']}}: {{number_format($stage['value'])}}" title="{{$stage['label']}}: {{number_format($stage['value'])}}"></a>@endforeach
                </div>
                <div class="dashboard-pipeline-legend">
                    @foreach($pipelineStages as $stage)<a href="{{$stage['href']}}"><i class="dashboard-stage-dot dashboard-tone-{{$stage['tone']}}"></i><span>{{$stage['label']}}</span><b>{{number_format($stage['value'])}}</b></a>@endforeach
                </div>
            </div>
            <p class="dashboard-card-footnote"><strong>Conversion Rate: {{number_format($conversionRate,1)}}%</strong></p>
        </article>

        <article class="dashboard-card dashboard-map-card">
            <header class="dashboard-card-heading"><h2>Franchise Retail Store Map</h2><a href="{{route('admin.franchise.page',['section'=>'franchise-retail-stores'])}}">Manage stores <x-icon name="arrow-right" size="14" /></a></header>
            <div class="dashboard-map-canvas" aria-label="Franchise retail store locations around the world">
                <svg viewBox="0 0 1000 420" aria-hidden="true" focusable="false">
                    <path d="M116 91 166 61l73 13 36 43-28 36-44 6-22 45-39-15-20-44-38-13zM264 221l55 17 36 55-14 73-38 24-27-37 9-48-39-35zM448 86l60-21 61 23 27 38-33 30-37-8-23 28-48-21-26-34zM505 186l60-17 53 35 23 56-26 23-56-12-17-43-49-17zM626 106l66-34 111 20 43 54-26 48-76 6-42-23-39 20-40-35zM706 252l61-8 66 36-19 44-70 13-49-34zM832 315l53-16 46 27-10 38-55 8-30-26z" />
                </svg>
                @forelse($storeMapPins as $pin)<a class="dashboard-map-pin" href="{{$pin['href']}}" style="left:{{$pin['x']}}%;top:{{$pin['y']}}%" aria-label="{{$pin['name']}} store location" title="{{$pin['name']}}"><span></span></a>@empty<p class="dashboard-map-empty">No geocoded store locations yet. Add latitude and longitude in store setup.</p>@endforelse
            </div>
            <div class="dashboard-map-regions">@forelse($mapRegions as $region)<a href="{{$region['href']}}"><small>{{$region['label']}}</small><b>{{number_format($region['value'])}} <em>Stores</em></b></a>@empty<span><small>No store regions</small><b>0 <em>Stores</em></b></span>@endforelse</div>
        </article>

        <article class="dashboard-card dashboard-performance-card">
            <header class="dashboard-card-heading"><h2>Franchise Performance <small data-dashboard-period-label>({{$performancePeriod}})</small></h2><form class="dashboard-period-form" method="get" action="{{route('admin.dashboard')}}"><label class="sr-only" for="dashboard-performance-period">Performance period</label><select id="dashboard-performance-period" name="period" data-dashboard-period aria-label="Performance period">@foreach($periodOptions as $value=>$label)<option value="{{$value}}" @selected($period===$value)>{{$label}}</option>@endforeach</select><button type="submit">Apply</button></form></header>
            <div class="dashboard-performance-body">
                <div class="dashboard-performance-donut" style="{{$performanceDonutStyle}}"><span>Total Sales<strong>{{$money($performanceTotal)}}</strong></span></div>
                <ul>@forelse($performanceSegments as $segment)<li><i class="dashboard-stage-dot dashboard-tone-{{$segment['tone']}}"></i><span>{{$segment['label']}}<b>{{$money($segment['value'])}} <small>({{$segment['share']}})</small></b></span></li>@empty<li class="dashboard-empty-inline">No paid orders in this period.</li>@endforelse</ul>
            </div>
        </article>

        <article class="dashboard-card dashboard-activities-card">
            <header class="dashboard-card-heading"><h2>Recent Activities</h2><a href="{{route('admin.resource','audit-logs')}}">View All <x-icon name="arrow-right" size="14" /></a></header>
            <div class="dashboard-activity-list">@forelse($recentActivities as $activity)<a class="dashboard-activity-row" href="{{$activity['href']}}"><span class="dashboard-activity-icon dashboard-tone-{{$activity['tone']}}"><x-icon name="{{$activity['icon']}}" size="15" /></span><span><strong>{{$activity['title']}}</strong><small>{{$activity['meta']}}</small></span></a>@empty<p class="dashboard-empty-state">No recent activity has been recorded yet.</p>@endforelse</div>
        </article>
    </section>

    <section class="dashboard-secondary-grid">
        <article class="dashboard-card dashboard-order-categories-card">
            <header class="dashboard-card-heading"><h2>Order Categories <small data-dashboard-period-label>({{$performancePeriod}})</small></h2><a href="{{route('admin.reports.order')}}">View Reports <x-icon name="arrow-right" size="14" /></a></header>
            <div class="dashboard-order-category-grid">@foreach($orderCategories as $category)<a class="dashboard-order-category dashboard-tone-{{$category['tone']}}" href="{{$category['href']}}"><span class="dashboard-order-category-icon"><x-icon name="{{$category['icon']}}" size="22" /></span><span class="dashboard-order-category-index">{{$loop->iteration}}</span><strong>{{$category['label']}}</strong><b data-dashboard-animated-number data-dashboard-value="{{$category['count']}}" data-dashboard-format="number">{{number_format($category['count'])}}</b><small class="dashboard-trend-{{$category['trend']}}">@if($category['trend']==='up')<x-icon name="arrow-up" size="12" />@elseif($category['trend']==='down')<x-icon name="arrow-down" size="12" />@else<x-icon name="minus" size="12" />@endif {{$category['change']}} <em>{{$category['comparison']}}</em></small><span class="dashboard-card-action">View Orders</span></a>@endforeach</div>
        </article>
        <article class="dashboard-card dashboard-communication-card">
            <header class="dashboard-card-heading"><h2>Communication Center Overview</h2><form class="dashboard-period-form" method="get" action="{{route('admin.dashboard')}}"><label class="sr-only" for="dashboard-communication-period">Communication period</label><select id="dashboard-communication-period" name="period" data-dashboard-period aria-label="Communication period">@foreach($periodOptions as $value=>$label)<option value="{{$value}}" @selected($period===$value)>{{$label}}</option>@endforeach</select><button type="submit">Apply</button></form></header>
            <div class="dashboard-communication-columns"><div>@foreach(array_slice($communicationOverview,0,5) as $item)<a class="dashboard-communication-row" href="{{$item['href']}}"><x-icon name="{{$item['icon']}}" size="15" /><span>{{$item['label']}}</span><b>{{number_format($item['count'])}}</b></a>@endforeach</div><div>@foreach(array_slice($communicationOverview,5) as $item)<a class="dashboard-communication-row dashboard-communication-row--metric" href="{{$item['href']}}"><x-icon name="{{$item['icon']}}" size="15" /><span>{{$item['label']}}</span><b>{{number_format($item['count'])}}</b>@if(isset($item['action']))<small>{{$item['action']}}</small>@endif</a>@endforeach</div></div>
            <a class="dashboard-open-communication" href="{{route('admin.communication-center.page.communication-center')}}">Open Communication Center <x-icon name="arrow-right" size="15" /></a>
        </article>
    </section>

    <section class="dashboard-card dashboard-flow-card">
        <header class="dashboard-card-heading"><h2>FRANCHISE BUSINESS FLOW - APPLICATION TO ACTIVE STORE</h2><span>Live workflow stages</span></header>
        <div class="dashboard-flow-track">@foreach($businessFlowSteps as $step)<a class="dashboard-flow-step" href="{{$step['href']}}"><span class="dashboard-flow-number">{{$loop->iteration}}</span><span class="dashboard-flow-icon"><x-icon name="{{$step['icon']}}" size="21" /></span><strong>{{$step['title']}}</strong><small>{{$step['detail']}}</small><b data-dashboard-animated-number data-dashboard-value="{{$step['value']}}" data-dashboard-format="number">{{number_format($step['value'])}}</b></a>@endforeach</div>
    </section>

    <section class="dashboard-card dashboard-reports-card">
        <header class="dashboard-card-heading"><h2>REPORTS - COMPLETE BUSINESS INSIGHTS</h2><a href="{{route('admin.resource','reports')}}">View All Reports <x-icon name="arrow-right" size="14" /></a></header>
        <div class="dashboard-report-grid">@foreach($reports as $report)<a class="dashboard-report-card" href="{{$report['href']}}"><span class="dashboard-report-icon"><x-icon name="{{$report['icon']}}" size="20" /></span><strong>{{$report['label']}}</strong><small>{{$report['description']}}</small><span>View Reports</span></a>@endforeach</div>
    </section>

    <footer class="dashboard-footer">
        <div class="dashboard-footer-value"><span><x-icon name="package" size="19" /></span><strong>Premium Quality<small>Crafted with excellence</small></strong></div>
        <div class="dashboard-footer-value"><span><x-icon name="globe" size="19" /></span><strong>Global Reach<small>Expanding worldwide</small></strong></div>
        <div class="dashboard-footer-value"><span><x-icon name="heart" size="19" /></span><strong>Trusted by Partners<small>Building lasting relationships</small></strong></div>
        <div class="dashboard-footer-value"><span><x-icon name="users" size="19" /></span><strong>Franchise Focused<small>Your growth is our mission</small></strong></div>
        <strong class="dashboard-footer-tagline">Together We Grow</strong>
    </footer>
</div>
@endsection
