<header class="admin-top admin-top--guide" data-cpanel-header>
    <button class="admin-menu-toggle" type="button" aria-label="Toggle navigation" aria-controls="admin-sidebar" aria-expanded="false" data-admin-nav-toggle><x-icon name="menu" size="18" /></button>
    <div class="admin-heading" aria-label="Control panel identity"><strong>Project 1 Control Panel</strong><span>Franchise Focused System</span></div>
    @if(request()->routeIs('admin.seo.*'))
        <form class="admin-search" method="get" action="{{ route('admin.seo.dashboard') }}"><input type="hidden" name="tab" value="{{ request('tab','overview') }}"><x-icon name="search" size="15" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Search SEO, pages, meta, keywords..." aria-label="Search SEO, pages, meta, keywords"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></form>
    @elseif(request()->routeIs('admin.banners.*'))
        <form class="admin-search" method="get" action="{{ route('admin.banners.index') }}"><input type="hidden" name="tab" value="{{ request('tab','all') }}"><x-icon name="search" size="15" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Search banners, sliders..." aria-label="Search banners, sliders"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></form>
    @elseif(request()->routeIs('admin.reports.order') || request()->routeIs('admin.reports.communication') || request()->routeIs('admin.reports.customer'))
        <form class="admin-search" method="get" action="{{ url()->current() }}"><input type="hidden" name="tab" value="{{ request('tab','overview') }}"><input type="hidden" name="from" value="{{ request('from','2025-04-01') }}"><input type="hidden" name="to" value="{{ request('to','2025-05-01') }}"><x-icon name="search" size="15" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Search report data..." aria-label="Search report data"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></form>
    @else
        <form class="admin-search" method="get" action="{{ route('admin.search') }}"><x-icon name="search" size="15" /><input type="search" name="q" value="{{ request()->routeIs('admin.search') ? request('q') : '' }}" placeholder="Search anything..." aria-label="Search anything"><button type="submit" aria-label="Search"><x-icon name="arrow-right" size="13" /></button></form>
    @endif
    <div class="admin-guide-actions" aria-label="cPanel tools">
        <a class="admin-guide-icon" href="{{ route('admin.resource','alerts-notifications') }}" aria-label="Alerts and notifications" title="Alerts and notifications" @if(($sidebarCounts['alerts'] ?? 0)>0) data-badge="{{ min(99,$sidebarCounts['alerts']) }}" @endif><x-icon name="bell" size="16" /></a>
        <a class="admin-guide-icon" href="{{ route('admin.resource','inbox') }}" aria-label="Communication inbox" title="Communication inbox" @if(($sidebarCounts['communications'] ?? 0)>0) data-badge="{{ min(99,$sidebarCounts['communications']) }}" @endif><x-icon name="message" size="16" /></a>
        <a class="admin-guide-icon" href="{{ url('/') }}" target="_blank" rel="noopener" aria-label="Open public website" title="Open public website"><x-icon name="globe" size="16" /></a>
        <details class="admin-profile" data-admin-profile>
            <summary aria-label="Open profile menu">
                <span class="admin-profile__avatar"><x-icon name="user" size="15" /></span>
                <span class="admin-profile__identity"><strong>{{ auth()->user()->name ?? 'Admin User' }}</strong><small>{{ auth()->user()->department ?: auth()->user()->email }}</small></span>
                <x-icon name="chevron-right" size="12" class="admin-profile__chevron" />
            </summary>
            <div class="admin-profile__menu" role="menu">
                <div class="admin-profile__meta"><strong>{{ auth()->user()->name ?? 'Admin User' }}</strong><span>{{ auth()->user()->email }}</span></div>
                <a role="menuitem" href="{{ route('admin.dashboard') }}"><x-icon name="home" size="14" /> Dashboard</a>
                <a role="menuitem" href="{{ route('admin.settings.theme.index') }}"><x-icon name="palette" size="14" /> Theme &amp; Appearance</a>
                <a role="menuitem" href="{{ url('/') }}" target="_blank" rel="noopener"><x-icon name="globe" size="14" /> View public website</a>
                <form class="admin-profile__logout" method="post" action="{{ route('logout') }}">
                    @csrf
                    <button role="menuitem" type="submit"><x-icon name="log-out" size="14" /> Logout</button>
                </form>
            </div>
        </details>
    </div>
</header>
