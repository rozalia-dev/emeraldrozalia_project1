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
    $groups=[
        [
            'label'=>'WEBSITE & PRODUCTS',
            'items'=>[
                [
                    'slug'=>'products',
                    'label'=>'Products',
                    'icon'=>'package',
                    'active'=>'admin/resource/products*',
                    'children'=>[
                        ['slug'=>'product-manager','label'=>'Product Manager','icon'=>'settings','active'=>'admin/resource/product-manager*'],
                        ['slug'=>'add-product','label'=>'Add Product','icon'=>'plus','active'=>'admin/resource/add-product*'],
                        ['route'=>'admin.bulk-upload','label'=>'Bulk Product Upload','icon'=>'upload','active'=>'admin/bulk-product-upload*'],
                        ['route'=>'admin.media.index','label'=>'Product Media Manager','icon'=>'camera','active'=>'admin/resource/media-manager*'],
                        ['slug'=>'images','label'=>'Images','icon'=>'camera','active'=>'admin/resource/images*'],
                        ['slug'=>'videos','label'=>'Videos','icon'=>'file-text','active'=>'admin/resource/videos*'],
                        ['slug'=>'360-product-view','label'=>'360° Product View','icon'=>'refresh','active'=>'admin/resource/360-product-view*'],
                        ['slug'=>'virtual-try-on','label'=>'Virtual Try-On','icon'=>'heart','active'=>'admin/resource/virtual-try-on*'],
                        ['slug'=>'categories','label'=>'Categories','icon'=>'package','active'=>'admin/resource/categories*'],
                        ['slug'=>'collections','label'=>'Collections','icon'=>'clover','active'=>'admin/resource/collections*'],
                        ['slug'=>'variants','label'=>'Variants','icon'=>'users','active'=>'admin/resource/variants*'],
                    ],
                ],
                ['slug'=>'banners-sliders','label'=>'Banners & Sliders','icon'=>'image','active'=>'admin/resource/banners-sliders*'],
                ['route'=>'admin.pages','label'=>'Page Manager','icon'=>'file-text','active'=>'admin/pages*'],
                ['slug'=>'seo-content','label'=>'SEO & Content','icon'=>'briefcase','active'=>'admin/resource/seo-content*'],
                ['slug'=>'reviews-ratings','label'=>'Reviews & Ratings','icon'=>'star','active'=>'admin/resource/reviews-ratings*'],
            ],
        ],
        [
            'label'=>'ONLINE SALES',
            'items'=>[
                ['slug'=>'online-sales','label'=>'Orders (6 Categories)','icon'=>'shopping-bag','active'=>'admin/resource/online-sales*'],
                ['slug'=>'customers','label'=>'Customers','icon'=>'users','active'=>'admin/resource/customers*'],
                ['slug'=>'cart-checkout','label'=>'Cart & Checkout','icon'=>'shopping-bag','active'=>'admin/resource/cart-checkout*'],
                ['slug'=>'payments','label'=>'Payments','icon'=>'credit-card','active'=>'admin/resource/payments*'],
                ['slug'=>'discounts-coupons','label'=>'Discounts & Coupons','icon'=>'star','active'=>'admin/resource/discounts-coupons*'],
                ['slug'=>'sales-reports','label'=>'Sales Reports','icon'=>'file-text','active'=>'admin/resource/sales-reports*'],
            ],
        ],
        [
            'label'=>'ORDER MANAGEMENT (6 CATEGORIES)',
            'items'=>[
                ['order'=>'online','label'=>'Online Orders','icon'=>'shopping-bag','active'=>'admin/orders/online*'],
                ['order'=>'corporate','label'=>'Corporate Orders','icon'=>'briefcase','active'=>'admin/orders/corporate*'],
                ['order'=>'bulk','label'=>'Bulk Orders','icon'=>'package','active'=>'admin/orders/bulk*'],
                ['order'=>'franchise','label'=>'Franchise Orders','icon'=>'users','active'=>'admin/orders/franchise*'],
                ['order'=>'franchise_retail','label'=>'Franchise Retail Orders','icon'=>'shopping-bag','active'=>'admin/orders/franchise_retail*'],
                ['order'=>'buyer','label'=>'Buyer Orders','icon'=>'user','active'=>'admin/orders/buyer*'],
            ],
        ],
        [
            'label'=>'FRANCHISE MANAGEMENT',
            'items'=>[
                ['slug'=>'franchise-dashboard','label'=>'Franchise Dashboard','icon'=>'home','active'=>'admin/resource/franchise-dashboard*'],
                ['slug'=>'franchise-applications','label'=>'Applications & Leads','icon'=>'file-text','active'=>'admin/resource/franchise-applications*'],
                ['slug'=>'franchise-territories','label'=>'Territories','icon'=>'globe','active'=>'admin/resource/franchise-territories*'],
                ['slug'=>'franchise-agreements','label'=>'Agreements','icon'=>'briefcase','active'=>'admin/resource/franchise-agreements*'],
                ['slug'=>'franchisees','label'=>'Franchisees','icon'=>'users','active'=>'admin/resource/franchisees*'],
                ['slug'=>'franchise-retail-stores','label'=>'Franchise Retail Stores','icon'=>'shopping-bag','active'=>'admin/resource/franchise-retail-stores*'],
                ['slug'=>'training-documents','label'=>'Training & Documents','icon'=>'file-text','active'=>'admin/resource/training-documents*'],
                ['slug'=>'marketing-assets','label'=>'Marketing Assets','icon'=>'camera','active'=>'admin/resource/marketing-assets*'],
                ['slug'=>'performance-targets','label'=>'Performance & Targets','icon'=>'star','active'=>'admin/resource/performance-targets*'],
                ['slug'=>'renewals','label'=>'Renewals','icon'=>'refresh','active'=>'admin/resource/renewals*'],
            ],
        ],
        [
            'label'=>'COMMUNICATION CENTER',
            'items'=>[
                ['slug'=>'communication-center','label'=>'Communication Center','icon'=>'message','active'=>'admin/resource/communication-center*'],
                ['slug'=>'inbox','label'=>'Inbox','icon'=>'mail','active'=>'admin/resource/inbox*'],
                ['slug'=>'chat-24-7','label'=>'Chat 24/7','icon'=>'message','active'=>'admin/resource/chat-24-7*'],
                ['slug'=>'whatsapp','label'=>'WhatsApp','icon'=>'message','active'=>'admin/resource/whatsapp*'],
                ['slug'=>'email','label'=>'Email','icon'=>'mail','active'=>'admin/resource/email*'],
                ['slug'=>'email-templates','label'=>'Email Templates','icon'=>'file-text','active'=>'admin/resource/email-templates*'],
                ['slug'=>'approval-center','label'=>'Approval Center','icon'=>'check','active'=>'admin/resource/approval-center*'],
                ['slug'=>'action-follow-ups','label'=>'Action / Follow-ups','icon'=>'clock','active'=>'admin/resource/action-follow-ups*'],
                ['slug'=>'alerts-notifications','label'=>'Alerts & Notifications','icon'=>'bell','active'=>'admin/resource/alerts-notifications*'],
                ['slug'=>'communication-reports','label'=>'Communication Reports','icon'=>'file-text','active'=>'admin/resource/communication-reports*'],
                ['slug'=>'communication-history','label'=>'Communication History (Log)','icon'=>'file-text','active'=>'admin/resource/communication-history*'],
            ],
        ],
    ];
    $href=function(array $item){return isset($item['order'])?route('admin.order-master',$item['order']):(isset($item['route'])?route($item['route']):route('admin.resource',$item['slug']));};
@endphp
<aside class="admin-sidebar">
    <a href="{{route('admin.dashboard')}}" class="admin-logo"><span class="home-brand-crop" role="img" aria-label="Emerald Rozalia Limited"></span></a>
    <small>PROJECT 1 CONTROL PANEL</small>
    <a class="admin-nav-home {{request()->routeIs('admin.dashboard')?'active':''}}" href="{{route('admin.dashboard')}}"><x-icon name="home" /> Dashboard</a>
    @foreach($groups as $group)
        <details class="admin-nav-group" open>
            <summary><span>{{$group['label']}}</span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
            <div class="admin-nav-items">
                @foreach($group['items'] as $item)
                    @if(isset($item['children']))
                        <details class="admin-nav-subgroup" open>
                            <summary class="{{request()->is($item['active'])?'active':''}}"><span class="admin-nav-parent-label"><x-icon name="{{$item['icon']}}" size="14" /><span>{{$item['label']}}</span></span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
                            <div class="admin-nav-subitems">
                                @foreach($item['children'] as $child)
                                    <a class="{{request()->is($child['active'])?'active':''}}" href="{{$href($child)}}"><x-icon name="{{$child['icon']}}" size="14" /><span>{{$child['label']}}</span></a>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <a class="{{request()->is($item['active'])?'active':''}}" href="{{$href($item)}}"><x-icon name="{{$item['icon']}}" size="14" /><span>{{$item['label']}}</span></a>
                    @endif
                @endforeach
            </div>
        </details>
    @endforeach
    <nav class="admin-nav-utility" aria-label="Administration">
        <a class="admin-nav-home {{request()->is('admin/resource/reports*')?'active':''}}" href="{{route('admin.resource','reports')}}"><x-icon name="file-text" size="14" /> <span>REPORTS</span></a>
        <a class="admin-nav-home {{request()->is('admin/resource/users-roles*')?'active':''}}" href="{{route('admin.resource','users-roles')}}"><x-icon name="users" size="14" /> <span>USERS &amp; ROLES</span></a>
        <a class="admin-nav-home {{request()->is('admin/resource/settings*')?'active':''}}" href="{{route('admin.resource','settings')}}"><x-icon name="settings" size="14" /> <span>SETTINGS</span></a>
    </nav>
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
