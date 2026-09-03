<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Dashboard') - Emerald Rozalia cPanel</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="admin-body">
@php
    $orderTypes=['order-online'=>'online','order-corporate'=>'corporate','order-bulk'=>'bulk','order-franchise'=>'franchise','order-franchise-retail'=>'franchise_retail','order-buyer'=>'buyer'];
    $groups=[
        'WEBSITE & PRODUCTS'=>['website-products'=>'Products','media-manager'=>'Product Media Manager','page-manager'=>'Page Manager'],
        'ONLINE SALES'=>['online-sales'=>'Online Sales','returns-refunds'=>'Returns & Refunds'],
        'ORDER MANAGEMENT'=>['order-online'=>'Online Orders','order-corporate'=>'Corporate Orders','order-bulk'=>'Bulk Orders','order-franchise'=>'Franchise Orders','order-franchise-retail'=>'Franchise Retail Orders','order-buyer'=>'Buyer Orders'],
        'FRANCHISE MANAGEMENT'=>['franchise-management'=>'Franchise Management'],
        'COMMUNICATION CENTRE'=>['communication-center'=>'Communication Centre'],
        'OPERATIONS'=>['reports'=>'Reports','users-roles'=>'Users & Roles','integrations'=>'Integrations','settings'=>'Settings','audit-logs'=>'Audit & Logs','automation'=>'Automation','backup-recovery'=>'Backup & Recovery','system-maintenance'=>'System Maintenance'],
    ];
    $href=function(string $slug)use($orderTypes){return isset($orderTypes[$slug])?route('admin.order-master',$orderTypes[$slug]):($slug==='page-manager'?route('admin.pages'):($slug==='integrations'?route('admin.integration-status'):route('admin.resource',$slug)));};
@endphp
<aside class="admin-sidebar">
    <a href="{{route('admin.dashboard')}}" class="admin-logo"><span class="home-brand-crop" role="img" aria-label="Emerald Rozalia Limited"></span></a>
    <small>PROJECT 1 CONTROL PANEL</small>
    <a class="admin-nav-home {{request()->routeIs('admin.dashboard')?'active':''}}" href="{{route('admin.dashboard')}}"><x-icon name="home" /> Dashboard</a>
    @foreach($groups as $label=>$items)
        <div class="admin-nav-group"><span>{{$label}}</span>
        @foreach($items as $slug=>$item)<a class="{{request()->is('admin/resource/'.$slug)||request()->is('admin/orders/*')&&isset($orderTypes[$slug])||($slug==='page-manager'&&request()->routeIs('admin.pages'))?'active':''}}" href="{{$href($slug)}}">{{$item}}</a>@endforeach
        </div>
    @endforeach
    <a class="admin-nav-home" href="{{route('admin.bulk-upload')}}"><x-icon name="upload" /> Bulk Product Upload</a>
</aside>
<div class="admin-shell">
    <header class="admin-top">
        <button class="admin-menu-toggle" type="button" aria-label="Toggle navigation" data-admin-nav-toggle><x-icon name="menu" size="22" /></button>
        <div class="admin-heading"><strong>Project 1 Control Panel</strong><span>Franchise Focused System</span></div>
        <label class="admin-search"><x-icon name="search" /><input type="search" placeholder="Search anything..." aria-label="Search anything"></label>
        <div class="admin-actions"><span aria-label="Notifications"><x-icon name="bell" /></span><span aria-label="Messages"><x-icon name="message" /></span><span aria-label="Help"><x-icon name="help" /></span><span class="admin-user"><x-icon name="user" /> {{auth()->user()->name ?? 'Admin User'}}</span></div>
    </header>
    <main class="admin-main">
        @if(session('success'))<div class="flash success">{{session('success')}}</div>@endif
        @if($errors->any())<div class="flash error">{{implode(' ',$errors->all())}}</div>@endif
        @yield('content')
    </main>
</div>
<script src="/js/app.js"></script>
</body>
</html>
