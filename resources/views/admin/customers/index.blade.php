@extends('layouts.admin')
@section('title','Customer Management')
@push('styles')<link rel="stylesheet" href="/css/customers-admin.css?v=20260910-customer-v1">@endpush

@php
$money=static fn($v)=>'€'.number_format((float)$v,2);
$initials=static function($name){return collect(preg_split('/\s+/',trim((string)$name)))->filter()->take(2)->map(fn($x)=>mb_strtoupper(mb_substr($x,0,1)))->implode('');};
$typeMeta=['online'=>['Online Orders','green'],'corporate'=>['Corporate Orders','blue'],'bulk'=>['Bulk Orders','purple'],'franchise'=>['Franchise Orders','orange'],'franchise_retail'=>['Franchise Retail Orders','teal'],'buyer'=>['Buyer Orders','red']];
@endphp

@section('content')
<div class="crm-page" data-customer-dashboard>
  <header class="crm-title-row">
    <div><h1>Customer Management</h1><p>Manage customer information, account status, orders, communication and more.</p></div>
    <div class="crm-title-actions">
      <form action="{{ route('admin.customers.import') }}" method="post" enctype="multipart/form-data">@csrf<input hidden type="file" name="file" accept=".csv,text/csv" data-import-file id="customer-import"><label class="crm-btn" for="customer-import"><x-icon name="upload" size="14"/> Import Customers</label></form>
      <a class="crm-btn" href="{{ route('admin.customers.export',request()->query()) }}"><x-icon name="download" size="14"/> Export Customers</a>
      <button class="crm-btn crm-btn--green" type="button" data-crm-open="customer-create"><x-icon name="plus" size="14"/> Add New Customer</button>
      <div class="crm-date"><x-icon name="calendar" size="24"/><div><span>Today</span><small>{{ now()->format('l, j F Y') }}</small><strong>{{ now()->format('g:i A') }}</strong></div></div>
    </div>
  </header>

  <section class="crm-kpis" aria-label="Customer summary">
    <article class="crm-kpi"><i class="crm-kpi-icon"><x-icon name="users" size="20"/></i><div><span>Total Customers</span><strong>{{ number_format($stats['total']) }}</strong><small><b>↑ {{ $stats['total'] ? number_format(min(99,($stats['new']/$stats['total'])*100),1) : 0 }}%</b> vs last 30 days</small></div></article>
    <article class="crm-kpi crm-kpi--blue"><i class="crm-kpi-icon"><x-icon name="user" size="20"/></i><div><span>Active Customers</span><strong>{{ number_format($stats['active']) }}</strong><small>{{ $stats['total'] ? number_format(($stats['active']/$stats['total'])*100,1) : 0 }}% of total</small></div></article>
    <article class="crm-kpi crm-kpi--purple"><i class="crm-kpi-icon"><x-icon name="users" size="20"/></i><div><span>New Customers (30 Days)</span><strong>{{ number_format($stats['new']) }}</strong><small><b>↑</b> recently registered</small></div></article>
    <article class="crm-kpi crm-kpi--orange"><i class="crm-kpi-icon">★</i><div><span>VIP / Loyalty Members</span><strong>{{ number_format($stats['vip']) }}</strong><small>{{ $stats['total'] ? number_format(($stats['vip']/$stats['total'])*100,1) : 0 }}% of total</small></div></article>
    <article class="crm-kpi crm-kpi--teal"><i class="crm-kpi-icon"><x-icon name="package" size="20"/></i><div><span>Total Orders (All Customers)</span><strong>{{ number_format($stats['orders']) }}</strong><small><b>↑</b> all order categories</small></div></article>
    <article class="crm-kpi"><i class="crm-kpi-icon">€</i><div><span>Total Spent (All Customers)</span><strong>{{ $money($stats['spent']) }}</strong><small><b>↑</b> lifetime spend</small></div></article>
    <article class="crm-kpi crm-kpi--red"><i class="crm-kpi-icon">€</i><div><span>Average Order Value</span><strong>{{ $money($stats['aov']) }}</strong><small><b>↑</b> all customers</small></div></article>
  </section>

  <form class="crm-filter" method="get" action="{{ route('admin.customers.index') }}">
    <label class="crm-search"><x-icon name="search" size="14"/><input name="q" value="{{ request('q') }}" placeholder="Search by name, email, phone, company or UUID..."></label>
    <button class="crm-btn" type="submit"><x-icon name="filter" size="13"/> Filters</button>
    <select name="group" onchange="this.form.submit()"><option value="">All Customer Groups</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((int)request('group')===$group->id)>{{ $group->name }}</option>@endforeach</select>
    <select name="segment" onchange="this.form.submit()"><option value="">All Segments</option>@foreach($segments as $segment)<option value="{{ $segment->id }}" @selected((int)request('segment')===$segment->id)>{{ $segment->name }}</option>@endforeach</select>
    <select name="status" onchange="this.form.submit()"><option value="">All Account Status</option>@foreach(['active','inactive','blocked','restricted'] as $s)<option @selected(request('status')===$s) value="{{ $s }}">{{ str($s)->headline() }}</option>@endforeach</select>
    <select name="country" onchange="this.form.submit()"><option value="">All Countries</option>@foreach($countries as $country)<option @selected(request('country')===$country)>{{ $country }}</option>@endforeach</select>
    <a class="crm-reset" href="{{ route('admin.customers.index') }}">↻ Reset</a>
  </form>

  <div class="crm-workspace">
    <main>
      <section class="crm-card crm-table-card">
        <div class="crm-table-wrap"><table class="crm-table"><thead><tr><th><input type="checkbox" data-select-all></th><th>CUSTOMER</th><th>EMAIL / PHONE</th><th>CUSTOMER GROUP</th><th>ACCOUNT STATUS</th><th>TOTAL ORDERS</th><th>TOTAL SPENT</th><th>LAST ORDER</th><th>ACTIONS</th></tr></thead><tbody>
        @forelse($customers as $customer)
          @php $profile=$customer->customerProfile;$primary=$profile?->primaryGroup ?? $customer->customerGroups->first(); $status=$profile?->account_status ?? 'active'; @endphp
          <tr class="{{ $selected?->id===$customer->id?'is-selected':'' }}">
            <td><input type="checkbox" data-row-check></td>
            <td><a class="crm-person" href="{{ request()->fullUrlWithQuery(['selected'=>$customer->id]) }}"><span class="crm-avatar">{{ $initials($customer->name) }}</span><span><strong>{{ $customer->name }}</strong><small>UUID: {{ Str::limit($profile?->uuid ?? $customer->public_uuid,18) }}</small></span></a></td>
            <td><span class="crm-name">{{ $customer->email }}</span><small class="crm-cell-sub">{{ $customer->phone ?: 'No phone' }}</small></td>
            <td><span class="crm-badge {{ in_array($primary?->type,['corporate','bulk'])?'crm-badge--blue':'' }}">{{ $primary?->name ?? 'Unassigned' }}</span></td>
            <td><span class="crm-badge {{ $status==='active'?'':'crm-badge--red' }}">{{ str($status)->headline() }}</span></td>
            <td>{{ number_format($customer->orders_count) }}</td><td>{{ $money($customer->orders_sum_total) }}</td><td>{{ $customer->orders_max_created_at ? \Illuminate\Support\Carbon::parse($customer->orders_max_created_at)->format('d M Y') : '—' }}</td>
            <td><div class="crm-actions"><a class="crm-action" title="View" href="{{ request()->fullUrlWithQuery(['selected'=>$customer->id]) }}"><x-icon name="eye" size="13"/></a><button class="crm-action" type="button" title="Edit" data-crm-open="customer-edit" @if($selected?->id!==$customer->id) onclick="location.href='{{ request()->fullUrlWithQuery(['selected'=>$customer->id]) }}'" @endif><x-icon name="pencil" size="13"/></button><a class="crm-action" title="Orders" href="{{ route('admin.order-master.overview',['q'=>$customer->email]) }}"><x-icon name="dots" size="13"/></a></div></td>
          </tr>
        @empty<tr><td colspan="9" class="crm-empty">No customers match these filters.</td></tr>@endforelse
        </tbody></table></div>
        <div class="crm-pagination"><span>Showing {{ number_format($customers->firstItem()??0) }} to {{ number_format($customers->lastItem()??0) }} of {{ number_format($customers->total()) }} customers</span>{{ $customers->links() }}<form class="crm-per-page" method="get">@foreach(request()->except(['per_page','page']) as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach<label>Rows per page <select name="per_page" onchange="this.form.submit()">@foreach([10,20,50,100] as $n)<option @selected((int)request('per_page',10)===$n)>{{ $n }}</option>@endforeach</select></label></form></div>
      </section>

      <section class="crm-bottom">
        <article class="crm-card crm-report"><h3>Order Category Distribution</h3><div class="crm-donut-layout"><div class="crm-donut"><strong>{{ number_format($profileData['orders']->count()) }}</strong><span>Total Orders</span></div><div class="crm-legend">@foreach($typeMeta as $key=>$meta)@php $row=$profileData['order_types']->firstWhere('order_type',$key); @endphp<div><i style="--dot:var(--crm-{{ $meta[1] }})"></i><span>{{ $meta[0] }}</span><b>{{ number_format($row->count??0) }}</b></div>@endforeach</div></div></article>
        <article class="crm-card crm-report"><h3>Spending Overview</h3><div class="crm-summary-grid"><div><span>This Month</span><strong>{{ $money($profileData['orders']->where('created_at','>=',now()->startOfMonth())->sum('total')) }}</strong></div><div><span>Last 30 Days</span><strong>{{ $money($profileData['orders']->where('created_at','>=',now()->subDays(30))->sum('total')) }}</strong></div><div><span>All Time</span><strong>{{ $money($profileData['orders']->sum('total')) }}</strong></div></div><div class="crm-linechart"><svg viewBox="0 0 300 100" preserveAspectRatio="none"><polyline fill="none" stroke="#087b3d" stroke-width="3" points="0,88 55,72 105,74 155,54 210,62 300,28"/></svg><div class="labels"><span>Nov</span><span>Dec</span><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span></div></div></article>
        <article class="crm-card crm-report"><h3>Top Purchased Categories</h3><div class="crm-bars">@forelse($profileData['top_categories'] as $cat)<div class="crm-bar"><span>{{ $cat->name }}</span><i><b style="width:{{ min(100,$cat->quantity*8) }}%"></b></i><strong>{{ number_format($cat->quantity) }}</strong></div>@empty<p class="crm-note">No purchase category data yet.</p>@endforelse</div><a class="crm-report-link" href="{{ route('admin.resource','customer-order-reports') }}">View All Purchase Analytics →</a></article>
        <article class="crm-card crm-report"><h3>Communication History</h3><div class="crm-mini-list">@forelse($profileData['communications'] as $item)<div class="crm-mini-row"><i></i><span>{{ str($item->channel)->headline() }} — {{ $item->subject ?: 'Customer conversation' }}</span><time>{{ $item->created_at?->format('d M Y g:i A') }}</time></div>@empty<p class="crm-note">No communication history yet.</p>@endforelse</div>@if($selected)<a class="crm-report-link" href="{{ route('admin.resource','communication-center',['q'=>$selected->email]) }}">View All Communication →</a>@endif</article>
      </section>
    </main>

    <aside class="crm-side">
      <section class="crm-card crm-side-card">
        <h2>Customer Profile</h2>
        @if($selected)
          <div class="crm-profile-head"><span class="crm-avatar">{{ $initials($selected->name) }}</span><div><h3>{{ $selected->name }}</h3><span class="crm-badge">{{ str($selected->customerProfile?->account_status??'active')->headline() }}</span> @if($selected->customerProfile?->is_vip)<span class="crm-badge crm-badge--gold">VIP Customer</span>@endif</div></div>
          <div class="crm-profile-meta"><a href="mailto:{{ $selected->email }}">✉ {{ $selected->email }}</a><span>☎ {{ $selected->phone?:'No phone' }}</span></div><div class="crm-profile-meta"><span>Customer Since: {{ $selected->created_at?->format('d M Y') }}</span><span>UUID: {{ Str::limit($selected->customerProfile?->uuid??$selected->public_uuid,22) }} <button class="crm-action" type="button" data-copy="{{ $selected->customerProfile?->uuid??$selected->public_uuid }}">⧉</button></span></div>
          <div class="crm-mini-tabs" data-crm-tabs><button class="is-active" data-crm-tab="overview">Overview</button><button data-crm-tab="orders">Orders</button><button data-crm-tab="details">Details</button><button data-crm-tab="addresses">Addresses</button><button data-crm-tab="history">History</button><button data-crm-tab="notes">Notes</button></div>
          <div class="crm-tab-panel is-active" data-crm-panel="overview"><h3 class="crm-panel-title">Order Summary (All Categories)</h3><div class="crm-order-types">@foreach($typeMeta as $key=>$meta)@php $row=$profileData['order_types']->firstWhere('order_type',$key);@endphp<div class="crm-order-type"><i>●</i><span><strong>{{ $meta[0] }}</strong><b>{{ number_format($row->count??0) }}</b><span>{{ $money($row->total??0) }}</span></span></div>@endforeach</div><div class="crm-summary-grid"><div><span>Total Orders</span><strong>{{ number_format($profileData['orders']->count()) }}</strong></div><div><span>Total Spent</span><strong>{{ $money($profileData['orders']->sum('total')) }}</strong></div><div><span>Average Order Value</span><strong>{{ $money($profileData['orders']->count()?$profileData['orders']->avg('total'):0) }}</strong></div></div><h3 class="crm-panel-title">Loyalty & Rewards</h3><div class="crm-summary-grid"><div><span>Points Balance</span><strong>{{ number_format($profileData['reward_points']) }}</strong></div><div><span>Tier</span><strong>{{ $selected->customerProfile?->is_vip?'VIP':'Standard' }}</strong></div><div><span>Rewards</span><strong>{{ number_format($selected->rewards->count()) }}</strong></div></div></div>
          <div class="crm-tab-panel" data-crm-panel="orders"><div class="crm-mini-list">@forelse($profileData['orders']->take(8) as $order)<div class="crm-mini-row"><i></i><span>{{ $order->number }} · {{ str($order->order_type)->headline() }} · {{ $money($order->total) }}</span><time>{{ $order->created_at?->format('d M Y') }}</time></div>@empty<p class="crm-note">No orders yet.</p>@endforelse</div></div>
          <div class="crm-tab-panel" data-crm-panel="details"><dl class="crm-dl"><div><dt>Email</dt><dd>{{ $selected->email }}</dd></div><div><dt>Phone</dt><dd>{{ $selected->phone?:'—' }}</dd></div><div><dt>Group</dt><dd>{{ $selected->customerProfile?->primaryGroup?->name??'Unassigned' }}</dd></div><div><dt>Country</dt><dd>{{ $selected->customerProfile?->country??'—' }}</dd></div><div><dt>Language</dt><dd>{{ strtoupper($selected->customerProfile?->preferred_language??'en') }}</dd></div></dl></div>
          <div class="crm-tab-panel" data-crm-panel="addresses"><div class="crm-mini-list">@forelse($selected->addresses as $address)<div class="crm-mini-row"><i></i><span>{{ $address->label }} — {{ $address->line1 }}, {{ $address->city }}, {{ $address->country }}</span><time>{{ $address->is_default?'Default':'' }}</time></div>@empty<p class="crm-note">No saved addresses.</p>@endforelse</div></div>
          <div class="crm-tab-panel" data-crm-panel="history"><div class="crm-mini-list">@forelse($profileData['activities'] as $log)<div class="crm-mini-row"><i></i><span>{{ str($log->action)->replace('.',' ')->headline() }}</span><time>{{ $log->created_at?->format('d M Y g:i A') }}</time></div>@empty<p class="crm-note">No audit activity yet.</p>@endforelse</div></div>
          <div class="crm-tab-panel" data-crm-panel="notes"><p>{{ $selected->customerProfile?->notes ?: 'No customer notes yet.' }}</p></div>
          <button class="crm-btn crm-side-action" type="button" data-crm-open="customer-edit">View / Edit Full Profile →</button>
        @else<p class="crm-note">Select a customer to view the profile.</p>@endif
      </section>

      @if($selected)
      <section class="crm-card crm-side-card"><h2>Account Summary</h2><dl class="crm-dl"><div><dt>Account Status</dt><dd>{{ str($selected->customerProfile?->account_status??'active')->headline() }}</dd></div><div><dt>Customer Group</dt><dd>{{ $selected->customerProfile?->primaryGroup?->name??'Unassigned' }}</dd></div><div><dt>Customer Segment</dt><dd>{{ $selected->customerSegments->pluck('name')->implode(', ')?:'—' }}</dd></div><div><dt>Preferred Language</dt><dd>{{ strtoupper($selected->customerProfile?->preferred_language??'en') }}</dd></div><div><dt>Marketing Consent</dt><dd>{{ $selected->customerProfile?->marketing_consent?'Opt-in':'Opt-out' }}</dd></div><div><dt>Email Verified</dt><dd>{{ $selected->email_verified_at?'✓':'—' }}</dd></div><div><dt>Country</dt><dd>{{ $selected->customerProfile?->country??'—' }}</dd></div><div><dt>Timezone</dt><dd>{{ $selected->customerProfile?->timezone??'Europe/Dublin' }}</dd></div></dl></section>
      <section class="crm-card crm-side-card"><h2>Quick Actions</h2><div class="crm-quick"><a href="{{ route('admin.order-master.overview',['q'=>$selected->email]) }}">＋ Add New Order</a><a href="{{ route('admin.resource','returns-refunds') }}">↩ Add Return / Refund</a><a href="{{ route('admin.resource','communication-center',['q'=>$selected->email]) }}">✉ Send Email</a><a href="{{ route('admin.resource','chat-24-7',['q'=>$selected->email]) }}">◉ Start Chat (24/7)</a><a href="{{ route('admin.resource','whatsapp',['q'=>$selected->phone]) }}">◉ Send WhatsApp Message</a><button type="button" data-crm-open="customer-edit">✎ Add Note / Edit Customer</button></div></section>
      <section class="crm-card crm-side-card"><h2>Data & Compliance</h2><dl class="crm-dl"><div><dt>GDPR Consent</dt><dd>{{ $selected->customerProfile?->marketing_consent?'Granted':'Not granted' }}</dd></div><div><dt>Consent Date</dt><dd>{{ $selected->customerProfile?->consent_at?->format('d M Y')??'—' }}</dd></div><div><dt>Data Retention</dt><dd>Enabled</dd></div></dl><div class="crm-tags" style="margin-top:10px">@foreach($selected->customerProfile?->tags??[] as $tag)<span class="crm-tag">{{ $tag }}</span>@endforeach</div></section>
      <section class="crm-card crm-side-card"><h2>Returns & Refunds</h2><dl class="crm-dl"><div><dt>Return Requests</dt><dd>{{ $profileData['returns']->count() }}</dd></div><div><dt>Approved Returns</dt><dd>{{ $profileData['returns']->where('status','approved')->count() }}</dd></div><div><dt>Refunds Issued</dt><dd>{{ $profileData['returns']->where('type','refund')->count() }}</dd></div></dl><a class="crm-report-link" href="{{ route('admin.resource','returns-refunds') }}">View All Returns →</a></section>
      @endif
    </aside>
  </div>

  <div class="crm-modal" data-crm-modal="customer-create" hidden><div class="crm-modal-card"><div class="crm-modal-head"><h3>Add New Customer</h3><button class="crm-modal-close" data-crm-close type="button">×</button></div><form class="crm-form" method="post" action="{{ route('admin.customers.store') }}">@csrf@include('admin.customers.partials.customer-fields',['editing'=>null])<div class="crm-form-actions"><button class="crm-btn" type="button" data-crm-close>Cancel</button><button class="crm-btn crm-btn--green" type="submit">Create Customer</button></div></form></div></div>
  @if($selected)<div class="crm-modal" data-crm-modal="customer-edit" hidden><div class="crm-modal-card"><div class="crm-modal-head"><h3>Edit Customer</h3><button class="crm-modal-close" data-crm-close type="button">×</button></div><form class="crm-form" method="post" action="{{ route('admin.customers.update',$selected) }}">@csrf @method('PATCH') @include('admin.customers.partials.customer-fields',['editing'=>$selected])<div class="crm-form-actions"><button class="crm-btn" type="button" data-crm-close>Cancel</button><button class="crm-btn crm-btn--green" type="submit">Save Customer</button></div></form></div></div>@endif
</div>
@endsection
@push('scripts')<script src="/js/customers-admin.js?v=20260910-customer-v1"></script>@endpush
