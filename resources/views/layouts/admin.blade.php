<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Dashboard') - Emerald Rozalia cPanel</title>
    <link rel="stylesheet" href="/css/app.css?v=20260905-dashboard-reference-v5">
    @stack('styles')
</head>
<body class="admin-body @if(request()->routeIs('admin.videos.*'))admin-videos-body @endif @if(request()->routeIs('admin.dashboard'))admin-dashboard-body @endif @if(request()->routeIs('admin.pages') || request()->routeIs('admin.pages.create') || request()->routeIs('admin.pages.edit'))admin-pages-body @endif @if(request()->routeIs('admin.seo.*'))seo-admin-body @endif @if(request()->routeIs('admin.collections.*'))admin-collections-body @endif @if(request()->routeIs('admin.settings.*'))admin-settings-body @endif @if(request()->routeIs('admin.reports.*'))admin-reports-body @endif @if(request()->routeIs('admin.sales-reports.*'))admin-sales-reports-body @endif @if(request()->routeIs('admin.order-master') || request()->routeIs('admin.order-master.*'))admin-orders-body @endif @if(request()->routeIs('admin.banners.*'))admin-banners-body @endif">
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
                        ['route'=>'admin.spins.index','label'=>'360° Product View','icon'=>'refresh','active'=>'admin/resource/360-product-view*'],
                        ['slug'=>'virtual-try-on','label'=>'Virtual Try-On','icon'=>'heart','active'=>'admin/resource/virtual-try-on*'],
                        ['slug'=>'categories','label'=>'Categories','icon'=>'package','active'=>'admin/resource/categories*'],
                        ['slug'=>'collections','label'=>'Collections','icon'=>'clover','active'=>'admin/resource/collections*'],
                        ['slug'=>'variants','label'=>'Variants','icon'=>'users','active'=>'admin/resource/variants*'],
                    ],
                ],
                ['route'=>'admin.banners.index','label'=>'Banners / Sliders','icon'=>'image','active'=>'admin/resource/banners-sliders*'],
                ['route'=>'admin.pages','label'=>'Pages','icon'=>'file-text','active'=>'admin/pages*'],
                ['route'=>'admin.seo.dashboard','label'=>'SEO & Content','icon'=>'briefcase','active'=>'admin/seo*'],
                ['slug'=>'reviews-ratings','label'=>'Reviews & Ratings','icon'=>'star','active'=>'admin/resource/reviews-ratings*'],
            ],
        ],
        [
            'label'=>'ONLINE SALES',
            'items'=>[
                [
                    'route'=>'admin.customers.index','label'=>'Customers','icon'=>'users','active'=>'admin/customers*',
                    'children'=>[
                        ['route'=>'admin.customers.index','label'=>'Customer Management','active'=>'admin/customers*'],
                        ['route'=>'admin.customer-groups.index','label'=>'Customer Groups','active'=>'admin/customer-groups*'],
                        ['route'=>'admin.customer-segments.index','label'=>'Customer Segments','active'=>'admin/customer-segments*'],
                    ],
                ],
                ['slug'=>'cart-checkout','label'=>'Cart & Checkout','icon'=>'shopping-bag','active'=>'admin/resource/cart-checkout*'],
                ['slug'=>'payments','label'=>'Payments','icon'=>'credit-card','active'=>'admin/resource/payments*'],
                ['slug'=>'discounts-coupons','label'=>'Discounts & Coupons','icon'=>'star','active'=>'admin/resource/discounts-coupons*'],
                ['route'=>'admin.sales-reports.dashboard','label'=>'Sales Reports','icon'=>'chart','active'=>'admin/resource/sales-reports*'],
            ],
        ],
        [
            'label'=>'ORDER MANAGEMENT (6 CATEGORIES)',
            'items'=>[
                ['route'=>'admin.order-master.overview','label'=>'Order Master Overview','icon'=>'shopping-bag','active'=>'admin/order-master*'],
                ...$orderItems,
            ],
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
        <details class="admin-nav-group" data-admin-nav-group="{{\Illuminate\Support\Str::slug($group['label'])}}" open>
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
        <details class="admin-nav-group" data-admin-nav-group="reports" open>
            <summary><span>REPORTS</span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
            <div class="admin-nav-items">
                <a class="{{request()->is('admin/resource/franchise-management*')?'active':''}}" href="{{route('admin.resource','franchise-management')}}"><span class="admin-nav-item-label"><x-icon name="briefcase" size="14" /><span>Franchise Reports</span></span></a>
                <a class="{{request()->is('admin/resource/franchise-retail-stores*')?'active':''}}" href="{{route('admin.resource','franchise-retail-stores')}}"><span class="admin-nav-item-label"><x-icon name="shopping-bag" size="14" /><span>Franchise Retail Store Reports</span></span></a>
                <a class="{{request()->routeIs('admin.reports.order')?'active':''}}" href="{{route('admin.reports.order')}}"><span class="admin-nav-item-label"><x-icon name="shopping-bag" size="14" /><span>Order Reports</span></span></a>
                <a class="{{request()->routeIs('admin.sales-reports.*')?'active':''}}" href="{{route('admin.sales-reports.dashboard')}}"><span class="admin-nav-item-label"><x-icon name="chart" size="14" /><span>Product &amp; Sales Reports</span></span></a>
                <a class="{{request()->routeIs('admin.reports.customer')?'active':''}}" href="{{route('admin.reports.customer')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>Customer Reports</span></span></a>
                <a class="{{request()->routeIs('admin.reports.communication')?'active':''}}" href="{{route('admin.reports.communication')}}"><span class="admin-nav-item-label"><x-icon name="message" size="14" /><span>Communication Reports</span></span></a>
                <a class="{{request()->is('admin/resource/website-products*')?'active':''}}" href="{{route('admin.resource','website-products')}}"><span class="admin-nav-item-label"><x-icon name="globe" size="14" /><span>Website Analytics</span></span></a>
            </div>
        </details>
        <details class="admin-nav-group" data-admin-nav-group="users-roles" open>
            <summary><span>USERS &amp; ROLES</span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
            <div class="admin-nav-items">
                <a class="{{request()->routeIs('admin.user-system.users')?'active':''}}" href="{{route('admin.user-system.users')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>Users Management</span></span></a>
                <a class="{{request()->routeIs('admin.user-system.roles')?'active':''}}" href="{{route('admin.user-system.roles')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>Roles Management</span></span></a>
                <a class="{{request()->routeIs('admin.user-system.roles-permissions')?'active':''}}" href="{{route('admin.user-system.roles-permissions')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>User Roles &amp; Permissions</span></span></a>
                <a class="{{request()->routeIs('admin.user-system.assignments')?'active':''}}" href="{{route('admin.user-system.assignments')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>Role Assignments</span></span></a>
                <a class="{{request()->routeIs('admin.user-system.permission-groups')?'active':''}}" href="{{route('admin.user-system.permission-groups')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>Permission Groups</span></span></a>
                <a class="{{request()->routeIs('admin.user-system.matrix')?'active':''}}" href="{{route('admin.user-system.matrix')}}"><span class="admin-nav-item-label"><x-icon name="users" size="14" /><span>Permission Matrix</span></span></a>
                <a class="{{request()->routeIs('admin.user-system.activity')?'active':''}}" href="{{route('admin.user-system.activity')}}"><span class="admin-nav-item-label"><x-icon name="file-text" size="14" /><span>Activity &amp; Security Log</span></span></a>
            </div>
        </details>
        <details class="admin-nav-group" data-admin-nav-group="settings" open>
            <summary><span>SETTINGS</span><x-icon name="chevron-right" size="12" class="admin-group-chevron" /></summary>
            <div class="admin-nav-items admin-settings-nav-items">
                @php
                    $settingsNav = [
                        ['slug' => null, 'label' => 'Settings', 'icon' => 'settings', 'active' => request()->routeIs('admin.settings.overview')],
                        ['slug' => 'audit-logs', 'label' => 'Audit & Logs', 'icon' => 'file-text'],
                        ['slug' => 'integrations', 'label' => 'Integrations', 'icon' => 'refresh'],
                        ['slug' => 'backup-recovery', 'label' => 'Data Management', 'icon' => 'download'],
                    ];
                @endphp
                @foreach($settingsNav as $item)
                    @php $active = $item['slug'] === null ? $item['active'] : request()->routeIs('admin.settings.page') && request()->route('section') === $item['slug']; @endphp
                    <a class="{{$active?'active':''}}" href="{{$item['slug'] === null ? route('admin.settings.overview') : route('admin.settings.page', $item['slug'])}}"><span class="admin-nav-item-label"><x-icon name="{{$item['icon']}}" size="14" /><span>{{$item['label']}}</span></span></a>
                @endforeach
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
            @if(request()->routeIs('admin.seo.*'))
            <form class="admin-search" method="get" action="{{route('admin.seo.dashboard')}}"><input type="hidden" name="tab" value="{{request('tab','overview')}}"><x-icon name="search" /><input type="search" name="q" value="{{request('q')}}" placeholder="Search SEO, pages, meta, keywords..." aria-label="Search SEO, pages, meta, keywords"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></form>
        @elseif(request()->routeIs('admin.banners.*'))
            <form class="admin-search" method="get" action="{{route('admin.banners.index')}}"><input type="hidden" name="tab" value="{{request('tab','all')}}"><x-icon name="search" /><input type="search" name="q" value="{{request('q')}}" placeholder="Search banners, sliders..." aria-label="Search banners, sliders"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></form>
        @elseif(request()->routeIs('admin.reports.order') || request()->routeIs('admin.reports.communication') || request()->routeIs('admin.reports.customer'))
            <form class="admin-search" method="get" action="{{url()->current()}}"><input type="hidden" name="tab" value="{{request('tab','overview')}}"><input type="hidden" name="from" value="{{request('from','2025-04-01')}}"><input type="hidden" name="to" value="{{request('to','2025-05-01')}}"><x-icon name="search" /><input type="search" name="q" value="{{request('q')}}" placeholder="Search report data..." aria-label="Search report data"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></form>
        @else
            <form class="admin-search" method="get" action="{{ route('admin.search') }}"><x-icon name="search" /><input type="search" name="q" value="{{ request()->routeIs('admin.search') ? request('q') : '' }}" placeholder="Search anything..." aria-label="Search anything"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></form>
        @endif
            <div class="admin-actions"><span aria-label="Notifications"><x-icon name="bell" /></span><span aria-label="Messages"><x-icon name="message" /></span><span aria-label="Help"><x-icon name="help" /></span><span class="admin-user"><x-icon name="user" /><span class="admin-user-name">{{auth()->user()->name ?? 'Admin User'}}</span></span></div>
        </header>
    @endif
    <main class="admin-main">
        @if(session('success'))<div class="flash success">{{session('success')}}</div>@endif
        @if($errors->any())<div class="flash error">{{implode(' ',$errors->all())}}</div>@endif
        @yield('content')
    </main>
    <footer class="admin-footer">
        <div><img src="/assets/brand/emerald-rozalia-wordmark.png" alt="Emerald Rozalia Limited"><span>© {{ now()->year }} Emerald Rozalia Limited. All rights reserved.</span></div>
        <div class="admin-footer-contact">
            @if(config('app.brand_contact.whatsapp'))<span>☎ {{ config('app.brand_contact.whatsapp') }}</span>@endif
            @if(config('app.brand_contact.email'))<span>✉ {{ config('app.brand_contact.email') }}</span>@endif
            @if(config('app.brand_contact.website'))<span>◉ {{ config('app.brand_contact.website') }}</span>@endif
            @if(config('app.brand_contact.location'))<span>⌖ {{ config('app.brand_contact.location') }}</span>@endif
        </div>
    </footer>
</div>
<script src="/js/app.js"></script>
@stack('scripts')
</body>
</html>
