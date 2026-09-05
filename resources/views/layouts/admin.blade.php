<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Dashboard') - Emerald Rozalia cPanel</title>
    <link rel="stylesheet" href="/css/app.css?v=20260905-dashboard-reference-v5">
</head>
<body class="admin-body @if(request()->routeIs('admin.dashboard'))admin-dashboard-body @endif @if(request()->routeIs('admin.pages') || request()->routeIs('admin.pages.create') || request()->routeIs('admin.pages.edit'))admin-pages-body @endif">
@php
    $orderItems=[
        ['order'=>'online','label'=>'Online Orders','icon'=>'shopping-bag','active'=>'admin/orders/online*','marker'=>'blue'],
        ['order'=>'corporate','label'=>'Corporate Orders','icon'=>'briefcase','active'=>'admin/orders/corporate*','marker'=>'purple'],
        ['order'=>'bulk','label'=>'Bulk Orders','icon'=>'package','active'=>'admin/orders/bulk*','marker'=>'orange'],
        ['order'=>'franchise','label'=>'Franchise Orders','icon'=>'users','active'=>'admin/orders/franchise*','marker'=>'green'],
        ['order'=>'franchise_retail','label'=>'Franchise Retail Orders','icon'=>'shopping-bag','active'=>'admin/orders/franchise_retail*','marker'=>'teal'],
        ['order'=>'buyer','label'=>'Buyer Orders','icon'=>'user','active'=>'admin/orders/buyer*','marker'=>'yellow'],
    ];
    $orderCategoryCount=count($orderItems);
    $sidebarCounts=[
        'applications'=>\App\Models\FranchiseApplication::whereIn('status',['new','pending'])->count(),
        'communications'=>\App\Models\Conversation::whereIn('status',['new','open','pending'])->count(),
        'approvals'=>\Illuminate\Support\Facades\DB::table('approvals')->where('status','pending')->count(),
        'followups'=>\App\Models\Conversation::whereNotNull('follow_up_at')->whereIn('status',['new','open','pending'])->count(),
        'alerts'=>\App\Models\Inquiry::where('status','new')->count(),
    ];
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
                        ['route'=>'admin.add-product','label'=>'Add Product','icon'=>'plus','active'=>'admin/resource/add-product*'],
                        ['route'=>'admin.bulk-upload','label'=>'Bulk Product Upload','icon'=>'upload','active'=>'admin/bulk-product-upload*'],
                        ['route'=>'admin.media.index','label'=>'Product Media Manager','icon'=>'camera','active'=>'admin/resource/media-manager*'],
                        ['route'=>'admin.images.index','label'=>'Images','icon'=>'camera','active'=>'admin/resource/images*'],
                        ['slug'=>'videos','label'=>'Videos','icon'=>'file-text','active'=>'admin/resource/videos*'],
                        ['slug'=>'360-product-view','label'=>'360° Product View','icon'=>'refresh','active'=>'admin/resource/360-product-view*'],
                        ['slug'=>'virtual-try-on','label'=>'Virtual Try-On','icon'=>'heart','active'=>'admin/resource/virtual-try-on*'],
                        ['slug'=>'categories','label'=>'Categories','icon'=>'package','active'=>'admin/resource/categories*'],
                        ['slug'=>'collections','label'=>'Collections','icon'=>'clover','active'=>'admin/resource/collections*'],
                        ['slug'=>'variants','label'=>'Variants','icon'=>'users','active'=>'admin/resource/variants*'],
                    ],
                ],
                ['slug'=>'banners-sliders','label'=>'Banners & Sliders','icon'=>'image','active'=>'admin/resource/banners-sliders*'],
                ['route'=>'admin.pages','label'=>'Pages','icon'=>'file-text','active'=>'admin/pages*'],
                ['slug'=>'seo-content','label'=>'SEO & Content','icon'=>'briefcase','active'=>'admin/resource/seo-content*'],
                ['slug'=>'reviews-ratings','label'=>'Reviews & Ratings','icon'=>'star','active'=>'admin/resource/reviews-ratings*'],
            ],
        ],
        [
            'label'=>'ONLINE SALES',
            'items'=>[
                ['slug'=>'online-sales','label'=>'Orders ('.$orderCategoryCount.' Categories)','icon'=>'shopping-bag','active'=>'admin/resource/online-sales*','chevron'=>true],
                ['slug'=>'customers','label'=>'Customers','icon'=>'users','active'=>'admin/resource/customers*'],
                ['slug'=>'cart-checkout','label'=>'Cart & Checkout','icon'=>'shopping-bag','active'=>'admin/resource/cart-checkout*'],
                ['slug'=>'payments','label'=>'Payments','icon'=>'credit-card','active'=>'admin/resource/payments*'],
                ['slug'=>'discounts-coupons','label'=>'Discounts & Coupons','icon'=>'star','active'=>'admin/resource/discounts-coupons*'],
                ['slug'=>'sales-reports','label'=>'Sales Reports','icon'=>'file-text','active'=>'admin/resource/sales-reports*'],
            ],
        ],
        [
            'label'=>'ORDER MANAGEMENT ('.$orderCategoryCount.' CATEGORIES)',
            'items'=>$orderItems,
        ],
        [
            'label'=>'FRANCHISE MANAGEMENT',
            'items'=>[
                ['slug'=>'franchise-dashboard','label'=>'Franchise Dashboard','icon'=>'home','active'=>'admin/resource/franchise-dashboard*'],
                ['slug'=>'franchise-applications','label'=>'Applications & Leads','icon'=>'file-text','active'=>'admin/resource/franchise-applications*','badge'=>['value'=>$sidebarCounts['applications'],'tone'=>'green']],
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
                ['slug'=>'communication-center','label'=>'Communication Center','icon'=>'message','active'=>'admin/resource/communication-center*','badge'=>['value'=>$sidebarCounts['communications'],'tone'=>'red']],
                ['slug'=>'inbox','label'=>'Inbox','icon'=>'mail','active'=>'admin/resource/inbox*'],
                ['slug'=>'chat-24-7','label'=>'Chat 24/7','icon'=>'message','active'=>'admin/resource/chat-24-7*'],
                ['slug'=>'whatsapp','label'=>'WhatsApp','icon'=>'message','active'=>'admin/resource/whatsapp*'],
                ['slug'=>'email','label'=>'Email','icon'=>'mail','active'=>'admin/resource/email*'],
                ['slug'=>'email-templates','label'=>'Email Templates','icon'=>'file-text','active'=>'admin/resource/email-templates*'],
                ['slug'=>'approval-center','label'=>'Approval Center','icon'=>'check','active'=>'admin/resource/approval-center*','badge'=>['value'=>$sidebarCounts['approvals'],'tone'=>'orange']],
                ['slug'=>'action-follow-ups','label'=>'Action / Follow-ups','icon'=>'clock','active'=>'admin/resource/action-follow-ups*','badge'=>['value'=>$sidebarCounts['followups'],'tone'=>'orange']],
                ['slug'=>'alerts-notifications','label'=>'Alerts & Notifications','icon'=>'bell','active'=>'admin/resource/alerts-notifications*','badge'=>['value'=>$sidebarCounts['alerts'],'tone'=>'red']],
                ['slug'=>'communication-reports','label'=>'Communication Reports','icon'=>'file-text','active'=>'admin/resource/communication-reports*'],
                ['slug'=>'communication-history','label'=>'Communication History (Log)','icon'=>'file-text','active'=>'admin/resource/communication-history*'],
            ],
        ],
    ];
    $href=function(array $item){return isset($item['order'])?route('admin.order-master',$item['order']):(isset($item['route'])?route($item['route']):route('admin.resource',$item['slug']));};
@endphp
<aside id="admin-sidebar" class="admin-sidebar">
    <a href="{{route('admin.dashboard')}}" class="admin-logo"><img class="admin-logo-image" src="{{asset('assets/logo/logo_two_line.png')}}" alt="Emerald Rozalia Limited"></a>
    <a class="admin-nav-home {{request()->routeIs('admin.dashboard')?'active':''}}" href="{{route('admin.dashboard')}}"><x-icon name="home" /> Dashboard</a>
    @foreach($groups as $group)
        <details class="admin-nav-group" open>
            <summary><span>{{$group['label']}}</span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
            <div class="admin-nav-items">
                @foreach($group['items'] as $item)
                    @if(isset($item['children']))
                        @php $subgroupOpen=request()->is($item['active']) || collect($item['children'])->contains(fn($child)=>isset($child['active']) && request()->is($child['active'])); @endphp
                        <details class="admin-nav-subgroup" @if($subgroupOpen) open @endif>
                            <summary class="{{request()->is($item['active'])?'active':''}}"><span class="admin-nav-parent-label"><x-icon name="{{$item['icon']}}" size="14" /><span>{{$item['label']}}</span></span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
                            <div class="admin-nav-subitems">
                                @foreach($item['children'] as $child)
                                    <a class="{{request()->is($child['active'])?'active':''}}" href="{{$href($child)}}"><span>{{$child['label']}}</span>@if(isset($child['badge']))<span class="admin-nav-badge admin-nav-badge--{{$child['badge']['tone']}}">{{number_format($child['badge']['value'])}}</span>@endif</a>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <a class="{{request()->is($item['active'])?'active':''}}" href="{{$href($item)}}">
                            <span class="admin-nav-item-label"><x-icon name="{{$item['icon']}}" size="14" /><span>{{$item['label']}}</span></span>
                            @if(isset($item['marker']))<i class="admin-nav-order-dot admin-nav-order-dot--{{$item['marker']}}"></i>@endif
                            @if(isset($item['badge']))<span class="admin-nav-badge admin-nav-badge--{{$item['badge']['tone']}}">{{number_format($item['badge']['value'])}}</span>@endif
                            @if(isset($item['chevron']))<x-icon name="chevron-right" size="10" class="admin-group-chevron" />@endif
                        </a>
                    @endif
                @endforeach
            </div>
        </details>
    @endforeach
    <nav class="admin-nav-utility" aria-label="Administration">
        <details class="admin-nav-group" open>
            <summary><span>REPORTS</span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
            <div class="admin-nav-items">
                <a class="{{request()->is('admin/resource/sales-reports*')?'active':''}}" href="{{route('admin.resource','sales-reports')}}"><span class="admin-nav-item-label"><x-icon name="file-text" size="14" /><span>Sales Report</span></span></a>
                <a class="{{request()->is('admin/resource/performance-targets*')?'active':''}}" href="{{route('admin.resource','performance-targets')}}"><span class="admin-nav-item-label"><x-icon name="file-text" size="14" /><span>Franchise Performance</span></span></a>
                <a class="{{request()->is('admin/resource/customer-order-reports*')?'active':''}}" href="{{route('admin.resource','customer-order-reports')}}"><span class="admin-nav-item-label"><x-icon name="file-text" size="14" /><span>Customer &amp; Order Reports</span></span></a>
            </div>
        </details>
        <details class="admin-nav-group" open>
            <summary><span>USERS &amp; ROLES</span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
            <div class="admin-nav-items">
                <a class="{{request()->is('admin/resource/users*')?'active':''}}" href="{{route('admin.resource','users')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>Users</span></span></a>
                <a class="{{request()->is('admin/resource/roles*')?'active':''}}" href="{{route('admin.resource','roles')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>Roles</span></span></a>
                <a class="{{request()->is('admin/resource/permissions*')?'active':''}}" href="{{route('admin.resource','permissions')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>Permissions</span></span></a>
            </div>
        </details>
        <details class="admin-nav-group" open>
            <summary><span>SETTINGS</span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
            <div class="admin-nav-items">
                <a class="{{request()->is('admin/resource/company-profile*')?'active':''}}" href="{{route('admin.resource','company-profile')}}"><span class="admin-nav-item-label"><x-icon name="settings" size="14" /><span>Company Profile</span></span></a>
                <a class="{{request()->is('admin/resource/language*')?'active':''}}" href="{{route('admin.resource','language')}}"><span class="admin-nav-item-label"><x-icon name="settings" size="14" /><span>Language</span></span></a>
                <a class="{{request()->is('admin/resource/currency*')?'active':''}}" href="{{route('admin.resource','currency')}}"><span class="admin-nav-item-label"><x-icon name="settings" size="14" /><span>Currency</span></span></a>
                <a class="{{request()->is('admin/resource/payment-settings*')?'active':''}}" href="{{route('admin.resource','payment-settings')}}"><span class="admin-nav-item-label"><x-icon name="settings" size="14" /><span>Payment Settings</span></span></a>
                <a class="{{request()->is('admin/resource/notifications*')?'active':''}}" href="{{route('admin.resource','notifications')}}"><span class="admin-nav-item-label"><x-icon name="settings" size="14" /><span>Notifications</span></span></a>
                <a class="{{request()->is('admin/resource/branding*')?'active':''}}" href="{{route('admin.resource','branding')}}"><span class="admin-nav-item-label"><x-icon name="settings" size="14" /><span>Branding</span></span></a>
                <a class="{{request()->is('admin/resource/security*')?'active':''}}" href="{{route('admin.resource','security')}}"><span class="admin-nav-item-label"><x-icon name="settings" size="14" /><span>Security</span></span></a>
                <a class="{{request()->is('admin/resource/audit-logs*')?'active':''}}" href="{{route('admin.resource','audit-logs')}}"><span class="admin-nav-item-label"><x-icon name="settings" size="14" /><span>Audit Logs</span></span></a>
            </div>
        </details>
    </nav>
    <footer class="admin-sidebar-footer"><span>&copy; {{now()->year}} Emerald Rozalia Ltd.</span><span>All rights reserved.</span></footer>
</aside>
<div class="admin-shell">
    @if(false && request()->routeIs('admin.pages'))
        <header class="admin-top pages-admin-top">
            <button class="admin-menu-toggle" type="button" aria-label="Toggle navigation" aria-controls="admin-sidebar" aria-expanded="false" data-admin-nav-toggle><x-icon name="menu" size="18" /></button>
            <div class="pages-top-breadcrumb"><strong>Project 1 Control Panel</strong><span>•</span><span>Website &amp; Products</span><span>•</span><b>Pages</b></div>
            <label class="pages-top-search"><span class="sr-only">Search pages</span><input type="search" placeholder="Search pages..." aria-label="Search pages"><x-icon name="search" size="15" /></label>
            <div class="pages-top-actions"><span class="pages-top-icon pages-notification"><x-icon name="bell" size="17" /><i>2</i></span><span class="pages-top-icon pages-notification"><x-icon name="message" size="17" /><i>5</i></span><span class="pages-top-icon pages-notification"><x-icon name="mail" size="17" /><i>3</i></span><span class="pages-top-icon"><x-icon name="help" size="17" /></span><span class="pages-top-user"><span class="pages-user-avatar"><x-icon name="user" size="17" /></span><span><strong>{{ auth()->user()->name ?? 'Admin User' }}</strong><small>Super Admin</small></span><x-icon name="chevron-right" size="14" /></span></div>
        </header>
    @else
        <header class="admin-top">
            <button class="admin-menu-toggle" type="button" aria-label="Toggle navigation" aria-controls="admin-sidebar" aria-expanded="false" data-admin-nav-toggle><x-icon name="menu" size="22" /></button>
            <div class="admin-heading"><strong>Project 1 Control Panel</strong><span>Franchise Focused System</span></div>
            <label class="admin-search"><x-icon name="search" /><input type="search" placeholder="Search anything..." aria-label="Search anything"></label>
            <div class="admin-actions"><span aria-label="Notifications"><x-icon name="bell" /></span><span aria-label="Messages"><x-icon name="message" /></span><span aria-label="Help"><x-icon name="help" /></span><span class="admin-user"><x-icon name="user" /><span class="admin-user-name">{{auth()->user()->name ?? 'Admin User'}}</span></span></div>
        </header>
    @endif
    <main class="admin-main">
        @if(session('success'))<div class="flash success">{{session('success')}}</div>@endif
        @if($errors->any())<div class="flash error">{{implode(' ',$errors->all())}}</div>@endif
        @yield('content')
    </main>
</div>
<script src="/js/app.js"></script>
</body>
</html>
