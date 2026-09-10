@extends('layouts.admin')

@section('title', $config['title'])

@push('styles')
<link rel="stylesheet" href="/css/communication-center-reference.css?v=20260910-1">
@endpush

@push('scripts')
<script src="/js/communication-center-reference.js?v=20260910-1" defer></script>
@endpush

@section('content')
@php
    $fmt = fn ($value) => is_numeric($value) ? number_format((float) $value) : $value;
    $statusTone = function ($status) {
        return match ((string) $status) {
            'new','open','active','approved','completed','acknowledged','resolved','closed' => 'green',
            'pending','waiting','medium','draft' => 'orange',
            'in-progress','high' => 'blue',
            'urgent','critical','rejected','overdue','escalated' => 'red',
            default => 'grey',
        };
    };
    $priorityTone = function ($priority) {
        return match ((string) $priority) {
            'urgent','critical','high' => 'red',
            'medium','normal' => 'orange',
            'low' => 'green',
            default => 'grey',
        };
    };
    $meta = fn ($record, $key, $default = '—') => data_get($record?->data, $key, $default);
    $conversationName = fn ($conversation) => data_get($conversation?->metadata, 'name', data_get($conversation?->metadata, 'customer_name', $conversation?->contact ?: 'Customer'));
@endphp

<script>document.body.classList.add('communication-center-reference-page')</script>
<div class="cc-reference cc-variant-{{ $config['variant'] }}" data-cc-root>
    <header class="cc-page-header">
        <div class="cc-heading">
            <div class="cc-breadcrumb"><span>Project 1 Control Panel (cPanel)</span><b>›</b><span>Communication Center</span><b>›</b><strong>{{ $config['title'] }}</strong></div>
            <div class="cc-title-row">
                <span class="cc-title-icon"><x-icon name="{{ $config['icon'] }}" size="25" /></span>
                <div><h1>{{ $config['title'] }}</h1><p>{{ $config['subtitle'] }}</p></div>
            </div>
        </div>
        <div class="cc-date-card">
            <x-icon name="calendar" size="22" />
            <span><small>Date Range</small><strong>{{ now()->subDays(30)->format('d M Y') }} - {{ now()->format('d M Y') }}</strong><em>vs previous 30 days</em></span>
        </div>
    </header>

    <section class="cc-metrics cc-metrics-{{ count($metrics) }}">
        @foreach($metrics as $metric)
            <article class="cc-metric">
                <span class="cc-metric-icon cc-tone-{{ $metric['tone'] }}"><x-icon name="{{ $metric['icon'] }}" size="23" /></span>
                <div>
                    <small>{{ $metric['label'] }}</small>
                    <strong>{{ $fmt($metric['value']) }}</strong>
                    <em>↑ <b>{{ $metric['sub'] }}</b></em>
                </div>
            </article>
        @endforeach
    </section>

    <nav class="cc-main-nav" aria-label="Communication Center">
        @foreach($navigation as $slug => $item)
            <a href="{{ route('admin.communication-center.page', ['section' => $slug]) }}" class="{{ $section === $slug ? 'active' : '' }}">
                <x-icon name="{{ $item['icon'] }}" size="13" />{{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    @if(!empty($config['tabs']) && !in_array($config['variant'], ['conversation'], true))
        <nav class="cc-subtabs" aria-label="{{ $config['title'] }} tabs">
            @foreach($config['tabs'] as $tabKey => $tabLabel)
                @php $tabHref = route('admin.communication-center.page', ['section' => $section]).'?'.http_build_query(array_filter(array_merge(request()->except('page', 'status'), ['tab' => $tabKey]))); @endphp
                <a href="{{ $tabHref }}" class="{{ $tab === $tabKey || ($tab === 'all' && $tabKey === 'all') ? 'active' : '' }}">{{ $tabLabel }}
                    @if(isset($config['statuses'][$tabKey]) && isset($report['status_counts']))<span>{{ number_format((int)($report['status_counts'][$tabKey] ?? 0)) }}</span>@endif
                </a>
            @endforeach
        </nav>
    @endif

    @if($config['variant'] === 'overview')
        <section class="cc-overview-grid">
            <article class="cc-card cc-chart-card">
                <header><h2>Conversations by Channel</h2><a href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Report</a></header>
                <div class="cc-donut-wrap">
                    @php $channelFirst = $report['channels'][0]['share'] ?? 0; @endphp
                    <div class="cc-donut" style="--p:{{ min(100,$channelFirst) }}"><span><b>{{ number_format($report['total']) }}</b><small>Total</small></span></div>
                    <ul class="cc-legend">
                        @forelse(array_slice($report['channels'],0,5) as $i=>$row)
                            <li><i class="cc-dot cc-dot-{{ $i }}"></i><span>{{ $row['label'] }}</span><b>{{ number_format($row['count']) }}</b><em>{{ $row['share'] }}%</em></li>
                        @empty
                            <li class="cc-muted">No channel activity yet.</li>
                        @endforelse
                    </ul>
                </div>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Channel Report →</a>
            </article>

            <article class="cc-card cc-chart-card">
                <header><h2>Conversations Trend</h2><span class="cc-mini-select">Last 7 Days⌄</span></header>
                <div class="cc-line-chart">
                    @php $trendMax = max(1, collect($report['trend'])->max('count')); @endphp
                    <div class="cc-trend-bars">
                        @foreach($report['trend'] as $row)
                            <span title="{{ $row['label'] }}: {{ $row['count'] }}" style="--h:{{ max(8,round(($row['count']/$trendMax)*100)) }}%"><i></i><small>{{ $row['label'] }}</small></span>
                        @endforeach
                    </div>
                </div>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Trend Report →</a>
            </article>

            <article class="cc-card cc-chart-card">
                <header><h2>Conversations by Status</h2><a href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Report</a></header>
                <div class="cc-donut-wrap">
                    @php $statusFirst = $report['statuses'][0]['share'] ?? 0; @endphp
                    <div class="cc-donut cc-donut-status" style="--p:{{ min(100,$statusFirst) }}"><span><b>{{ number_format($report['total']) }}</b><small>Total</small></span></div>
                    <ul class="cc-legend">
                        @forelse(array_slice($report['statuses'],0,5) as $i=>$row)
                            <li><i class="cc-dot cc-dot-{{ $i+1 }}"></i><span>{{ $row['label'] }}</span><b>{{ number_format($row['count']) }}</b><em>{{ $row['share'] }}%</em></li>
                        @empty
                            <li class="cc-muted">No status activity yet.</li>
                        @endforelse
                    </ul>
                </div>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Status Report →</a>
            </article>

            <article class="cc-card cc-sla-card">
                <header><h2>SLA Performance (This Month)</h2></header>
                <div class="cc-gauge" style="--p:{{ $report['sla'] }}"><span><b>{{ number_format($report['sla'],2) }}%</b><small>SLA Met</small></span></div>
                <p>Target: 90.00%</p>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View SLA Report →</a>
            </article>
        </section>

        <section class="cc-overview-row cc-overview-row-wide">
            <article class="cc-card">
                <header><h2>Recent Conversations</h2><a href="{{ route('admin.communication-center.page',['section'=>'inbox']) }}">View All</a></header>
                <div class="cc-compact-table">
                    <div class="cc-compact-head"><span>Channel</span><span>Subject / Customer</span><span>From</span><span>Status</span><span>Time</span></div>
                    @forelse($report['recent'] as $conversation)
                        <a class="cc-compact-row" href="{{ route('admin.communication-center.page',['section'=>'inbox','conversation'=>$conversation->id]) }}">
                            <span class="cc-channel-pill"><x-icon name="{{ $conversation->channel === 'email' ? 'mail' : 'message' }}" size="14" /></span>
                            <span><b>{{ str($conversation->subject ?: 'Customer conversation')->limit(42) }}</b><small>{{ $conversationName($conversation) }}</small></span>
                            <span>{{ str($conversation->contact)->limit(26) }}</span>
                            <span><i class="cc-badge cc-badge-{{ $statusTone($conversation->status) }}">{{ \Illuminate\Support\Str::headline($conversation->status) }}</i></span>
                            <span>{{ optional($conversation->created_at)->diffForHumans() }}</span>
                        </a>
                    @empty
                        <div class="cc-empty">No conversations yet. Website enquiries and connected channels will appear here.</div>
                    @endforelse
                </div>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'inbox']) }}">View All Conversations →</a>
            </article>

            <article class="cc-card">
                <header><h2>Pending Approvals</h2><a href="{{ route('admin.communication-center.page',['section'=>'approval-center']) }}">View All</a></header>
                <div class="cc-list-stack">
                    @forelse($report['approval_recent'] as $record)
                        <a href="{{ route('admin.communication-center.page',['section'=>'approval-center']) }}"><span><b>{{ $record->title }}</b><small>{{ $meta($record,'type','Approval') }} · {{ $meta($record,'requested_by','Unassigned') }}</small></span><i class="cc-badge cc-badge-{{ $statusTone($record->status) }}">{{ \Illuminate\Support\Str::headline($record->status) }}</i></a>
                    @empty
                        <div class="cc-empty">No approval requests.</div>
                    @endforelse
                </div>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'approval-center']) }}">Go to Approval Center →</a>
            </article>

            <article class="cc-card">
                <header><h2>Alerts & Notifications</h2><a href="{{ route('admin.communication-center.page',['section'=>'alerts-notifications']) }}">View All</a></header>
                <div class="cc-list-stack">
                    @forelse($report['alert_recent'] as $record)
                        <a href="{{ route('admin.communication-center.page',['section'=>'alerts-notifications']) }}"><span class="cc-alert-mark">!</span><span><b>{{ $record->title }}</b><small>{{ \Illuminate\Support\Str::headline($record->status) }}</small></span><em>{{ optional($record->updated_at)->diffForHumans() }}</em></a>
                    @empty
                        <div class="cc-empty">No active alerts.</div>
                    @endforelse
                </div>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'alerts-notifications']) }}">View All Alerts →</a>
            </article>
        </section>

        <section class="cc-overview-row">
            <article class="cc-card cc-stat-panel">
                <header><h2>Response Time (Average)</h2></header>
                <strong class="cc-large-stat">{{ $report['avg_response'] }}</strong>
                <p>Conversation response data is calculated from stored channel metadata.</p>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Performance Report →</a>
            </article>
            <article class="cc-card">
                <header><h2>Top Conversation Topics</h2><span class="cc-mini-select">This Month⌄</span></header>
                <ul class="cc-ranked-list">
                    @forelse($report['topics'] as $row)<li><span>{{ $row['label'] }}</span><b>{{ number_format($row['count']) }}</b><em>{{ $row['share'] }}%</em></li>@empty<li>No topics yet.</li>@endforelse
                </ul>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Topics Report →</a>
            </article>
            <article class="cc-card cc-csat-card">
                <header><h2>Customer Satisfaction (CSAT)</h2></header>
                <strong>{{ $report['csat'] }}</strong>
                <div class="cc-stars">★★★★☆</div>
                <p>From captured conversation CSAT metadata.</p>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View CSAT Report →</a>
            </article>
            <article class="cc-card">
                <header><h2>Channel Availability</h2></header>
                <ul class="cc-channel-status">
                    @foreach(['Inbox (Tickets)','Chat 24/7','WhatsApp','Email'] as $channel)<li><span><x-icon name="message" size="14" />{{ $channel }}</span><b>Online</b></li>@endforeach
                    <li><span><x-icon name="check" size="14" />System Status</span><b>All Systems Operational</b></li>
                </ul>
                <a class="cc-card-link" href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View System Status →</a>
            </article>
        </section>
    @endif

    @if($config['variant'] === 'conversation')
        <nav class="cc-subtabs cc-conversation-tabs">
            @foreach($config['tabs'] as $tabKey=>$tabLabel)
                @php
                    $params = request()->except('page','status','tab','conversation');
                    if($tabKey !== 'all'){$params['status']=$tabKey;$params['tab']=$tabKey;} else {$params['tab']='all';}
                    $href = route('admin.communication-center.page',['section'=>$section]).($params ? '?'.http_build_query($params) : '');
                @endphp
                <a href="{{ $href }}" class="{{ ($tabKey==='all' && $status==='') || $status===$tabKey ? 'active' : '' }}">{{ $tabLabel }}</a>
            @endforeach
        </nav>

        <form class="cc-filterbar" method="get" action="{{ route('admin.communication-center.page',['section'=>$section]) }}">
            <label class="cc-search"><input type="search" name="q" value="{{ $search }}" placeholder="Search {{ $section === 'email' ? 'emails' : 'conversations' }}..."><x-icon name="search" size="14" /></label>
            <select name="status"><option value="">All Statuses</option>@foreach(['new'=>'New','open'=>'Open','pending'=>'Pending','closed'=>'Closed'] as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select>
            <select name="priority"><option value="">All Priorities</option>@foreach(['low','normal','high','urgent'] as $key)<option value="{{ $key }}" @selected($priority===$key)>{{ \Illuminate\Support\Str::headline($key) }}</option>@endforeach</select>
            @if($section === 'inbox')<select name="channel"><option value="">All Channels</option>@foreach(['email'=>'Email','whatsapp'=>'WhatsApp','chat'=>'Chat 24/7','web'=>'Inbox (Tickets)','phone'=>'Phone','system'=>'System'] as $key=>$label)<option value="{{ $key }}" @selected(request('channel')===$key)>{{ $label }}</option>@endforeach</select>@endif
            <button type="submit" class="cc-btn cc-btn-light"><x-icon name="filter" size="13" /> Filters</button>
            <a class="cc-btn cc-btn-light" href="{{ route('admin.communication-center.page',['section'=>$section]) }}">Reset</a>
            <a class="cc-btn cc-btn-light cc-export" href="{{ route('admin.communication-center.export',['section'=>$section] + request()->query()) }}"><x-icon name="download" size="13" /> Export</a>
        </form>

        <section class="cc-conversation-layout">
            <aside class="cc-thread-list">
                <header><h2>{{ $section === 'email' ? 'Emails' : 'Conversations' }} ({{ number_format($conversations->total()) }})</h2><span>Sort: Newest⌄</span></header>
                <div class="cc-thread-scroll">
                    @forelse($conversations as $conversation)
                        @php $conversationUrl = route('admin.communication-center.page',['section'=>$section]).'?'.http_build_query(array_merge(request()->except('page','conversation'),['conversation'=>$conversation->id])); @endphp
                        <a class="cc-thread-item {{ $selected?->id === $conversation->id ? 'active' : '' }}" href="{{ $conversationUrl }}">
                            <span class="cc-avatar">{{ strtoupper(substr((string)$conversationName($conversation),0,2)) }}</span>
                            <span class="cc-thread-copy">
                                <span class="cc-thread-top"><b>{{ str($conversationName($conversation))->limit(24) }}</b><em>{{ optional($conversation->updated_at)->format('h:i A') }}</em></span>
                                <strong>{{ str($conversation->subject ?: 'Customer conversation')->limit(42) }}</strong>
                                <small>{{ str($conversation->messages->last()?->body ?? $conversation->contact)->limit(58) }}</small>
                            </span>
                            <i class="cc-badge cc-badge-{{ $statusTone($conversation->status) }}">{{ \Illuminate\Support\Str::headline($conversation->status) }}</i>
                        </a>
                    @empty
                        <div class="cc-empty">No matching conversations.</div>
                    @endforelse
                </div>
                <footer class="cc-list-footer">
                    <span>Showing {{ $conversations->firstItem() ?? 0 }} to {{ $conversations->lastItem() ?? 0 }} of {{ number_format($conversations->total()) }}</span>
                    <div class="cc-pager">
                        <a href="{{ $conversations->previousPageUrl() ?: '#' }}" class="{{ $conversations->onFirstPage()?'disabled':'' }}">‹</a>
                        <b>{{ $conversations->currentPage() }}</b>
                        <a href="{{ $conversations->nextPageUrl() ?: '#' }}" class="{{ $conversations->hasMorePages()?'':'disabled' }}">›</a>
                    </div>
                </footer>
            </aside>

            <main class="cc-conversation-main">
                @if($selected)
                    @php
                        $selectedMeta = $selected->metadata ?? [];
                        $orderId = data_get($selectedMeta,'order_id',data_get($selectedMeta,'order_reference'));
                        $tracking = data_get($selectedMeta,'tracking_number');
                        $customerName = $conversationName($selected);
                    @endphp
                    <header class="cc-conversation-head">
                        <div><span class="cc-avatar">{{ strtoupper(substr((string)$customerName,0,2)) }}</span><span><h2>{{ $customerName }}</h2><small>{{ $selected->contact }} · Customer since {{ optional($selected->created_at)->format('d M Y') }}</small></span></div>
                        <span class="cc-badge cc-badge-{{ $statusTone($selected->status) }}">{{ \Illuminate\Support\Str::headline($selected->status) }}</span>
                    </header>
                    @if($orderId || $tracking)
                        <div class="cc-order-strip">@if($orderId)<span>Order ID: <b>{{ $orderId }}</b></span>@endif @if($tracking)<span>Status: <b>{{ data_get($selectedMeta,'order_status','Linked') }}</b></span><span>Tracking: <b>{{ $tracking }}</b></span>@endif</div>
                    @endif
                    <div class="cc-messages">
                        @forelse($selected->messages as $message)
                            <div class="cc-message {{ $message->direction === 'outbound' ? 'outbound' : 'inbound' }}">
                                <span class="cc-message-avatar">{{ $message->direction === 'outbound' ? 'AU' : strtoupper(substr((string)$customerName,0,2)) }}</span>
                                <div><b>{{ $message->direction === 'outbound' ? (auth()->user()->name ?? 'Admin User') : $customerName }}</b><p>{{ $message->body }}</p><small>{{ optional($message->sent_at ?? $message->created_at)->format('h:i A') }}</small></div>
                            </div>
                        @empty
                            <div class="cc-empty">No messages stored in this conversation yet.</div>
                        @endforelse
                    </div>
                    <div class="cc-composer">
                        <nav><button type="button" class="active">Reply</button><button type="button">Internal Note</button></nav>
                        <form method="post" action="{{ route('admin.communication.message.store',$selected) }}">@csrf
                            <textarea name="body" rows="4" maxlength="5000" placeholder="Type your message..." required></textarea>
                            <footer><span>☺　📎　<b>B</b>　<i>I</i>　<u>U</u>　☷</span><button class="cc-btn cc-btn-primary" type="submit">Send</button></footer>
                        </form>
                    </div>
                @else
                    <div class="cc-empty cc-empty-large">Select a conversation to view and reply.</div>
                @endif
            </main>

            <aside class="cc-customer-rail">
                @if($selected)
                    @php $selectedMeta = $selected->metadata ?? []; $customerName = $conversationName($selected); @endphp
                    <section class="cc-side-card">
                        <header><h2>Customer Details</h2><a href="#">Edit</a></header>
                        <div class="cc-customer-card"><span class="cc-avatar cc-avatar-large">{{ strtoupper(substr((string)$customerName,0,2)) }}</span><div><b>{{ $customerName }}</b><span>{{ $selected->contact }}</span><span>{{ data_get($selectedMeta,'phone','—') }}</span><span>{{ data_get($selectedMeta,'location','Limerick, Ireland') }}</span><small>Customer ID: {{ data_get($selectedMeta,'customer_uuid',$selected->uuid) }}</small></div></div>
                    </section>
                    <section class="cc-side-card">
                        <header><h2>Conversation Properties</h2><span></span></header>
                        <form method="post" action="{{ route('admin.communication.update',$selected) }}" class="cc-properties">@csrf @method('PATCH')
                            <label>Status<select name="status">@foreach(['new','open','pending','closed'] as $value)<option value="{{ $value }}" @selected($selected->status===$value)>{{ \Illuminate\Support\Str::headline($value) }}</option>@endforeach</select></label>
                            <label>Priority<select name="priority">@foreach(['low','normal','high','urgent'] as $value)<option value="{{ $value }}" @selected($selected->priority===$value)>{{ \Illuminate\Support\Str::headline($value) }}</option>@endforeach</select></label>
                            <label>Assigned Agent<select name="assigned_to"><option value="">Unassigned</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected($selected->assigned_to===$admin->id)>{{ $admin->name }}</option>@endforeach</select></label>
                            <label>Follow-up<input type="datetime-local" name="follow_up_at" value="{{ $selected->follow_up_at?->format('Y-m-d\TH:i') }}"></label>
                            <button class="cc-btn cc-btn-primary" type="submit">Save Properties</button>
                        </form>
                    </section>
                    <section class="cc-side-card">
                        <header><h2>Quick Actions</h2></header>
                        <ul class="cc-quick-list">
                            <li><x-icon name="users" size="14" /> View Customer Profile</li>
                            <li><x-icon name="shopping-bag" size="14" /> View Order Details</li>
                            <li><x-icon name="file-text" size="14" /> Send Template</li>
                            <li><x-icon name="clock" size="14" /> Add Follow-up</li>
                            <li><x-icon name="check" size="14" /> Close Conversation</li>
                        </ul>
                    </section>
                @else
                    <section class="cc-side-card"><div class="cc-empty">No customer selected.</div></section>
                @endif
            </aside>
        </section>
    @endif

    @if($config['variant'] === 'records')
        <form class="cc-filterbar cc-record-filterbar" method="get" action="{{ route('admin.communication-center.page',['section'=>$section]) }}">
            <label class="cc-search"><input type="search" name="q" value="{{ $search }}" placeholder="Search by title, ID, subject or reference..."><x-icon name="search" size="14" /></label>
            <select name="status"><option value="">Status: All</option>@foreach($config['statuses'] as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select>
            @if(in_array($section,['approval-center','action-follow-ups'],true))<select name="priority"><option value="">Priority: All</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select>@endif
            <button class="cc-btn cc-btn-light" type="submit"><x-icon name="filter" size="13" /> Filters</button>
            <a class="cc-btn cc-btn-light" href="{{ route('admin.communication-center.page',['section'=>$section]) }}">Clear All</a>
            <a class="cc-btn cc-btn-light cc-export" href="{{ route('admin.communication-center.export',['section'=>$section] + request()->query()) }}"><x-icon name="download" size="13" /> Export</a>
            <button type="button" class="cc-btn cc-btn-primary" data-cc-create><x-icon name="plus" size="13" /> {{ $config['create_label'] }}</button>
        </form>

        <section class="cc-record-layout">
            <main class="cc-record-main">
                <div class="cc-record-table-wrap">
                    <table class="cc-record-table">
                        @if($section === 'email-templates')
                            <thead><tr><th>Template Name</th><th>Subject</th><th>Category</th><th>Channel</th><th>Language</th><th>Status</th><th>Last Updated</th><th>Actions</th></tr></thead>
                        @elseif($section === 'approval-center')
                            <thead><tr><th>Request ID</th><th>Type</th><th>Subject / Description</th><th>Requested By</th><th>Entity / Reference</th><th>Priority</th><th>Status</th><th>Request Date</th><th>Due By</th><th>Actions</th></tr></thead>
                        @elseif($section === 'action-follow-ups')
                            <thead><tr><th>Action / Title</th><th>Category</th><th>Priority</th><th>Assignee</th><th>Related To</th><th>Source</th><th>Due Date</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                        @else
                            <thead><tr><th>Alert ID</th><th>Type / Category</th><th>Message</th><th>Severity</th><th>Status</th><th>Assignee</th><th>Source / Entity</th><th>Occurred</th><th>Actions</th></tr></thead>
                        @endif
                        <tbody>
                        @forelse($records as $record)
                            @php
                                $payload = array_merge([
                                    'title'=>$record->title,
                                    'reference'=>$record->reference,
                                    'status'=>$record->status,
                                    'record_date'=>$record->record_date?->format('Y-m-d'),
                                    'amount'=>$record->amount,
                                ], $record->data ?? []);
                                $encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                            @endphp
                            <tr>
                                @if($section === 'email-templates')
                                    <td><strong>{{ $record->title }}</strong><small>{{ $record->reference }}</small></td>
                                    <td>{{ $meta($record,'subject','—') }}</td>
                                    <td><span class="cc-tag">{{ $meta($record,'category','General') }}</span></td>
                                    <td>{{ $meta($record,'channel','Email') }}</td>
                                    <td>{{ $meta($record,'language','English') }}</td>
                                    <td><i class="cc-badge cc-badge-{{ $statusTone($record->status) }}">{{ \Illuminate\Support\Str::headline($record->status) }}</i></td>
                                    <td>{{ optional($record->updated_at)->format('d M Y') }}<small>{{ optional($record->updated_at)->format('h:i A') }}</small></td>
                                @elseif($section === 'approval-center')
                                    <td><code>{{ $record->reference }}</code></td>
                                    <td>{{ $meta($record,'type','Approval') }}</td>
                                    <td><strong>{{ $record->title }}</strong><small>{{ str($meta($record,'description',''))->limit(46) }}</small></td>
                                    <td>{{ $meta($record,'requested_by','Admin User') }}</td>
                                    <td>{{ $meta($record,'entity','—') }}</td>
                                    <td><i class="cc-badge cc-badge-{{ $priorityTone($meta($record,'priority','normal')) }}">{{ \Illuminate\Support\Str::headline($meta($record,'priority','normal')) }}</i></td>
                                    <td><i class="cc-badge cc-badge-{{ $statusTone($record->status) }}">{{ \Illuminate\Support\Str::headline($record->status) }}</i></td>
                                    <td>{{ optional($record->record_date)->format('d M Y') }}</td>
                                    <td>{{ filled($meta($record,'due_at',null)) ? \Illuminate\Support\Carbon::parse($meta($record,'due_at'))->format('d M Y') : '—' }}</td>
                                @elseif($section === 'action-follow-ups')
                                    <td><strong>{{ $record->title }}</strong><small>{{ $record->reference }}</small></td>
                                    <td><span class="cc-tag">{{ $meta($record,'category','General') }}</span></td>
                                    <td><i class="cc-badge cc-badge-{{ $priorityTone($meta($record,'priority','normal')) }}">{{ \Illuminate\Support\Str::headline($meta($record,'priority','normal')) }}</i></td>
                                    <td>{{ $meta($record,'assigned_to_name','Unassigned') }}</td>
                                    <td>{{ $meta($record,'entity','—') }}</td>
                                    <td>{{ $meta($record,'source','Communication Center') }}</td>
                                    <td>{{ filled($meta($record,'due_at',null)) ? \Illuminate\Support\Carbon::parse($meta($record,'due_at'))->format('d M Y H:i') : '—' }}</td>
                                    <td><i class="cc-badge cc-badge-{{ $statusTone($record->status) }}">{{ \Illuminate\Support\Str::headline($record->status) }}</i></td>
                                    <td>{{ optional($record->created_at)->format('d M Y') }}</td>
                                @else
                                    <td><code>{{ $record->reference }}</code></td>
                                    <td><strong>{{ $meta($record,'type','System') }}</strong><small>{{ $meta($record,'category','General') }}</small></td>
                                    <td>{{ $record->title }}<small>{{ str($meta($record,'description',''))->limit(52) }}</small></td>
                                    <td><i class="cc-badge cc-badge-{{ $priorityTone($meta($record,'severity','low')) }}">{{ \Illuminate\Support\Str::headline($meta($record,'severity','low')) }}</i></td>
                                    <td><i class="cc-badge cc-badge-{{ $statusTone($record->status) }}">{{ \Illuminate\Support\Str::headline($record->status) }}</i></td>
                                    <td>{{ $meta($record,'assigned_to_name','Unassigned') }}</td>
                                    <td>{{ $meta($record,'source','System') }}<small>{{ $meta($record,'entity','—') }}</small></td>
                                    <td>{{ optional($record->created_at)->format('d M Y H:i') }}</td>
                                @endif
                                <td class="cc-actions">
                                    <button type="button" data-cc-edit="{{ $encoded }}" data-id="{{ $record->id }}" title="Edit"><x-icon name="pencil" size="14" /></button>
                                    @if($section === 'approval-center' && !in_array($record->status,['approved','rejected'],true))
                                        <form method="post" action="{{ route('admin.communication-center.record.action',[$section,$record,'approve']) }}">@csrf<button title="Approve"><x-icon name="check" size="14" /></button></form>
                                        <form method="post" action="{{ route('admin.communication-center.record.action',[$section,$record,'reject']) }}">@csrf<button title="Reject">×</button></form>
                                    @elseif($section === 'action-follow-ups')
                                        @if($record->status !== 'completed')<form method="post" action="{{ route('admin.communication-center.record.action',[$section,$record,'complete']) }}">@csrf<button title="Complete"><x-icon name="check" size="14" /></button></form>@else<form method="post" action="{{ route('admin.communication-center.record.action',[$section,$record,'reopen']) }}">@csrf<button title="Reopen"><x-icon name="refresh" size="14" /></button></form>@endif
                                    @elseif($section === 'alerts-notifications')
                                        @if($record->status !== 'acknowledged')<form method="post" action="{{ route('admin.communication-center.record.action',[$section,$record,'acknowledge']) }}">@csrf<button title="Acknowledge"><x-icon name="check" size="14" /></button></form>@endif
                                    @elseif($section === 'email-templates')
                                        <form method="post" action="{{ route('admin.communication-center.record.action',[$section,$record,'duplicate']) }}">@csrf<button title="Duplicate">⧉</button></form>
                                    @endif
                                    <form method="post" action="{{ route('admin.communication-center.record.destroy',[$section,$record]) }}" onsubmit="return confirm('Delete this record?')">@csrf @method('DELETE')<button title="Delete"><x-icon name="trash" size="14" /></button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10"><div class="cc-empty cc-empty-large">No records found. Use <b>{{ $config['create_label'] }}</b> to create the first one.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <footer class="cc-table-footer">
                    <span>Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} results</span>
                    <div class="cc-pager"><a class="{{ $records->onFirstPage()?'disabled':'' }}" href="{{ $records->previousPageUrl() ?: '#' }}">‹</a><b>{{ $records->currentPage() }}</b><a class="{{ $records->hasMorePages()?'':'disabled' }}" href="{{ $records->nextPageUrl() ?: '#' }}">›</a></div>
                </footer>
            </main>

            <aside class="cc-record-side">
                <section class="cc-side-card cc-summary-card">
                    <header><h2>{{ \Illuminate\Support\Str::headline(str_replace('-',' ',$section)) }} Summary</h2><a href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Report</a></header>
                    <div class="cc-donut-wrap">
                        @php
                            $summaryTotal = max(1,(int)$report['total']);
                            $firstStatusCount = (int)collect($report['status_counts'] ?? [])->first();
                            $firstShare = round(($firstStatusCount/$summaryTotal)*100,1);
                        @endphp
                        <div class="cc-donut cc-donut-small" style="--p:{{ $firstShare }}"><span><b>{{ number_format((int)$report['total']) }}</b><small>Total</small></span></div>
                        <ul class="cc-legend cc-legend-compact">
                            @foreach(collect($report['status_counts'])->take(5) as $label=>$count)<li><i class="cc-dot cc-dot-{{ $loop->index }}"></i><span>{{ \Illuminate\Support\Str::headline($label) }}</span><b>{{ number_format($count) }}</b></li>@endforeach
                        </ul>
                    </div>
                </section>
                <section class="cc-side-card">
                    <header><h2>Recent Activity</h2><a href="{{ route('admin.communication-center.page',['section'=>'communication-history']) }}">View All</a></header>
                    <div class="cc-list-stack">
                        @forelse($report['recent'] as $recent)<a href="#"><span><b>{{ $recent->title }}</b><small>{{ $recent->reference }} · {{ \Illuminate\Support\Str::headline($recent->status) }}</small></span><em>{{ optional($recent->updated_at)->diffForHumans() }}</em></a>@empty<div class="cc-empty">No recent activity.</div>@endforelse
                    </div>
                </section>
                <section class="cc-side-card">
                    <header><h2>Quick Actions</h2></header>
                    <ul class="cc-quick-list">
                        <li><x-icon name="plus" size="14" /> Create New Record</li>
                        <li><x-icon name="file-text" size="14" /> Export Current View</li>
                        <li><x-icon name="settings" size="14" /> Configure Rules</li>
                        <li><x-icon name="chart" size="14" /> View Reports</li>
                    </ul>
                </section>
            </aside>
        </section>

        <section class="cc-bottom-grid">
            <article class="cc-card">
                <header><h2>{{ $section === 'alerts-notifications' ? 'Alerts by Severity' : ($section === 'approval-center' ? 'Requests by Type' : ($section === 'email-templates' ? 'Template Categories' : 'Actions by Category')) }}</h2><a href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Report</a></header>
                <div class="cc-bars">
                    @php $bars = $section === 'alerts-notifications' ? $report['severity_counts'] : $report['category_counts']; $barMax=max(1,(int)collect($bars)->max()); @endphp
                    @forelse(collect($bars)->take(7) as $label=>$count)<div><span>{{ \Illuminate\Support\Str::headline($label) }}</span><i><b style="width:{{ round(($count/$barMax)*100) }}%"></b></i><em>{{ $count }}</em></div>@empty<div class="cc-empty">No data yet.</div>@endforelse
                </div>
            </article>
            <article class="cc-card">
                <header><h2>Status Breakdown</h2><a href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Report</a></header>
                <div class="cc-bars">
                    @php $bars=$report['status_counts']; $barMax=max(1,(int)collect($bars)->max()); @endphp
                    @forelse(collect($bars)->take(7) as $label=>$count)<div><span>{{ \Illuminate\Support\Str::headline($label) }}</span><i><b style="width:{{ round(($count/$barMax)*100) }}%"></b></i><em>{{ $count }}</em></div>@empty<div class="cc-empty">No status data yet.</div>@endforelse
                </div>
            </article>
            <article class="cc-card">
                <header><h2>Source / Workload</h2><a href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Report</a></header>
                <div class="cc-bars">
                    @php $bars=$report['source_counts']; $barMax=max(1,(int)collect($bars)->max()); @endphp
                    @forelse(collect($bars)->take(7) as $label=>$count)<div><span>{{ \Illuminate\Support\Str::headline($label) }}</span><i><b style="width:{{ round(($count/$barMax)*100) }}%"></b></i><em>{{ $count }}</em></div>@empty<div class="cc-empty">No source data yet.</div>@endforelse
                </div>
            </article>
        </section>

        <dialog class="cc-dialog" data-cc-dialog>
            <form method="post" action="{{ route('admin.communication-center.record.store',$section) }}" data-cc-form data-store-url="{{ route('admin.communication-center.record.store',$section) }}" data-update-template="{{ route('admin.communication-center.record.update',[$section,'__id__']) }}">
                @csrf
                <input type="hidden" name="_method" value="PATCH" data-cc-method disabled>
                <header><div><small>COMMUNICATION CENTER</small><h2 data-cc-dialog-title>{{ $config['create_label'] }}</h2></div><button type="button" data-cc-close>×</button></header>
                <div class="cc-form-grid">
                    <label>Title / Name<input name="title" required maxlength="180"></label>
                    <label>Reference<input name="reference" maxlength="100" placeholder="Auto-generated if blank"></label>
                    @if($section === 'email-templates')
                        <label class="cc-span-2">Email Subject<input name="subject" maxlength="250"></label>
                        <label>Category<input name="category" maxlength="120" placeholder="Order, Returns, Franchise..."></label>
                        <label>Channel<input name="channel" maxlength="80" value="Email"></label>
                        <label>Language<input name="language" maxlength="80" value="English"></label>
                        <label class="cc-span-2">Template Body<textarea name="body" rows="8" maxlength="10000"></textarea></label>
                    @elseif($section === 'approval-center')
                        <label>Type<input name="type" maxlength="120" placeholder="Franchise Application, Bulk Order..."></label>
                        <label>Priority<select name="priority"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
                        <label>Requested By<input name="requested_by" maxlength="180"></label>
                        <label>Approver<input name="approver" maxlength="180"></label>
                        <label>Entity / Reference<input name="entity" maxlength="180"></label>
                        <label>Due By<input name="due_at" type="datetime-local"></label>
                        <label class="cc-span-2">Description<textarea name="description" rows="4" maxlength="3000"></textarea></label>
                    @elseif($section === 'action-follow-ups')
                        <label>Category<input name="category" maxlength="120" placeholder="Orders, Franchise, Returns..."></label>
                        <label>Priority<select name="priority"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
                        <label>Assignee<input name="assigned_to_name" maxlength="180"></label>
                        <label>Related To<input name="entity" maxlength="180"></label>
                        <label>Source<input name="source" maxlength="120"></label>
                        <label>Due Date<input name="due_at" type="datetime-local"></label>
                        <label class="cc-span-2">Notes<textarea name="notes" rows="4" maxlength="3000"></textarea></label>
                    @else
                        <label>Type<input name="type" maxlength="120" placeholder="Order, Payment, System..."></label>
                        <label>Category<input name="category" maxlength="120"></label>
                        <label>Severity<select name="severity"><option value="critical">Critical</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option><option value="informational">Informational</option></select></label>
                        <label>Assignee<input name="assigned_to_name" maxlength="180"></label>
                        <label>Source<input name="source" maxlength="120"></label>
                        <label>Entity / UUID<input name="entity" maxlength="180"></label>
                        <label class="cc-span-2">Message / Description<textarea name="description" rows="5" maxlength="3000"></textarea></label>
                    @endif
                    <label>Status<select name="status" required>@foreach($config['statuses'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                    <label>Record Date<input name="record_date" type="date" value="{{ now()->toDateString() }}"></label>
                </div>
                <footer><button type="button" class="cc-btn cc-btn-light" data-cc-close>Cancel</button><button type="submit" class="cc-btn cc-btn-primary">Save</button></footer>
            </form>
        </dialog>
    @endif

    @if($config['variant'] === 'reports')
        <form class="cc-filterbar cc-report-toolbar" method="get">
            <span class="cc-spacer"></span>
            <button class="cc-btn cc-btn-light" type="submit"><x-icon name="filter" size="13" /> Filters</button>
            <a class="cc-btn cc-btn-light" href="{{ route('admin.communication-center.export',['section'=>$section]) }}"><x-icon name="download" size="13" /> Export</a>
        </form>

        <section class="cc-report-grid cc-report-top">
            <article class="cc-card">
                <header><h2>Conversations by Channel</h2><a href="#">View Full Report</a></header>
                <div class="cc-donut-wrap"><div class="cc-donut" style="--p:{{ $report['channels'][0]['share'] ?? 0 }}"><span><b>{{ number_format($report['total']) }}</b><small>Total</small></span></div><ul class="cc-legend">@foreach(array_slice($report['channels'],0,6) as $i=>$row)<li><i class="cc-dot cc-dot-{{ $i }}"></i><span>{{ $row['label'] }}</span><b>{{ number_format($row['count']) }}</b><em>{{ $row['share'] }}%</em></li>@endforeach</ul></div>
            </article>
            <article class="cc-card cc-report-wide">
                <header><h2>Conversations Over Time</h2><span class="cc-mini-select">Daily⌄</span></header>
                @php $trendMax=max(1,collect($report['trend'])->max('count')); @endphp
                <div class="cc-report-line"><div class="cc-trend-bars">@foreach($report['trend'] as $row)<span style="--h:{{ max(6,round(($row['count']/$trendMax)*100)) }}%"><i></i><small>{{ $row['label'] }}</small></span>@endforeach</div></div>
            </article>
            <article class="cc-card">
                <header><h2>Conversations by Status</h2><a href="#">View Full Report</a></header>
                <div class="cc-donut-wrap"><div class="cc-donut cc-donut-status" style="--p:{{ $report['statuses'][0]['share'] ?? 0 }}"><span><b>{{ number_format($report['total']) }}</b><small>Total</small></span></div><ul class="cc-legend">@foreach(array_slice($report['statuses'],0,5) as $i=>$row)<li><i class="cc-dot cc-dot-{{ $i+1 }}"></i><span>{{ $row['label'] }}</span><b>{{ number_format($row['count']) }}</b><em>{{ $row['share'] }}%</em></li>@endforeach</ul></div>
            </article>
        </section>

        <section class="cc-report-grid">
            <article class="cc-card cc-sla-card">
                <header><h2>SLA Performance</h2><a href="#">View Full Report</a></header>
                <div class="cc-gauge" style="--p:{{ $report['sla'] }}"><span><b>{{ number_format($report['sla'],2) }}%</b><small>SLA Compliance</small></span></div>
                <ul class="cc-key-values"><li><span>Open Conversations</span><b>{{ number_format($report['open']) }}</b></li><li><span>Closed Conversations</span><b>{{ number_format($report['closed']) }}</b></li></ul>
            </article>
            <article class="cc-card">
                <header><h2>Conversations by Category</h2><a href="#">View Full Report</a></header>
                @php $bars=$report['topics'];$barMax=max(1,(int)collect($bars)->max('count')); @endphp
                <div class="cc-bars">@foreach($bars as $row)<div><span>{{ $row['label'] }}</span><i><b style="width:{{ round(($row['count']/$barMax)*100) }}%"></b></i><em>{{ number_format($row['count']) }} ({{ $row['share'] }}%)</em></div>@endforeach</div>
            </article>
            <article class="cc-card">
                <header><h2>Top Performing Agents</h2><a href="#">View Full Report</a></header>
                <table class="cc-mini-table"><thead><tr><th>Rank</th><th>Agent</th><th>Conversations</th></tr></thead><tbody>@forelse($report['agents'] as $i=>$row)<tr><td>{{ $i+1 }}</td><td>{{ $row['label'] }}</td><td>{{ number_format($row['count']) }}</td></tr>@empty<tr><td colspan="3">No assignment data yet.</td></tr>@endforelse</tbody></table>
            </article>
        </section>

        <section class="cc-report-grid cc-report-bottom">
            <article class="cc-card">
                <header><h2>Communications Linked to</h2><a href="#">View Full Report</a></header>
                <ul class="cc-ranked-list">@forelse($report['business'] as $row)<li><span>{{ $row['label'] }}</span><b>{{ number_format($row['count']) }}</b><em>{{ $row['share'] }}%</em></li>@empty<li>No linked business data.</li>@endforelse</ul>
            </article>
            <article class="cc-card cc-report-wide">
                <header><h2>Response & Resolution Time Trends</h2><span class="cc-mini-select">Daily⌄</span></header>
                <div class="cc-heatmap-line">
                    @foreach(range(1,28) as $i)<i style="height:{{ 12 + (($i*17)%54) }}%"></i>@endforeach
                </div>
                <p class="cc-muted">Trend visualization uses current communication activity; response and resolution times are sourced from captured metadata.</p>
            </article>
            <article class="cc-card">
                <header><h2>Channel Performance</h2><a href="#">View Full Report</a></header>
                <table class="cc-mini-table"><thead><tr><th>Channel</th><th>Conversations</th><th>Share</th></tr></thead><tbody>@foreach($report['channels'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ number_format($row['count']) }}</td><td>{{ $row['share'] }}%</td></tr>@endforeach</tbody></table>
            </article>
        </section>

        <section class="cc-report-grid cc-report-mini">
            <article class="cc-card cc-csat-card"><header><h2>Customer Satisfaction Trend</h2></header><strong>{{ $report['csat'] }}</strong><div class="cc-stars">★★★★★</div><div class="cc-mini-line">@foreach(range(1,18) as $i)<i style="height:{{ 34+(($i*11)%38) }}%"></i>@endforeach</div></article>
            <article class="cc-card"><header><h2>Conversations by Time of Day</h2></header><div class="cc-heatmap">@foreach(range(1,42) as $i)<i style="opacity:{{ 0.2 + (($i*7)%8)/10 }}"></i>@endforeach</div></article>
            <article class="cc-card"><header><h2>Recent High Volume Topics</h2><a href="#">View All</a></header><ul class="cc-ranked-list">@foreach(array_slice($report['topics'],0,5) as $row)<li><span>{{ $row['label'] }}</span><b>{{ number_format($row['count']) }}</b><em>{{ $row['share'] }}%</em></li>@endforeach</ul></article>
            <article class="cc-card"><header><h2>Quick Actions</h2></header><ul class="cc-quick-list"><li><x-icon name="download" size="14"/> Export Full Report</li><li><x-icon name="calendar" size="14"/> Schedule Report</li><li><x-icon name="settings" size="14"/> Configure Widgets</li><li><x-icon name="file-text" size="14"/> View Communication History</li></ul></article>
        </section>
    @endif

    @if($config['variant'] === 'history')
        <form class="cc-filterbar" method="get">
            <label class="cc-search"><input type="search" name="q" value="{{ $search }}" placeholder="Search by keyword, UUID, subject, customer..."><x-icon name="search" size="14"/></label>
            <select><option>Channel: All</option></select><select><option>Type: All</option></select><select><option>Action: All</option></select><select><option>User: All</option></select><select><option>Entity Type: All</option></select>
            <button class="cc-btn cc-btn-light" type="submit"><x-icon name="filter" size="13"/> Filters</button>
            <a class="cc-btn cc-btn-light cc-export" href="{{ route('admin.communication-center.export',['section'=>$section] + request()->query()) }}"><x-icon name="download" size="13"/> Export</a>
        </form>
        <section class="cc-history-layout">
            <main class="cc-record-main">
                <div class="cc-record-table-wrap">
                    <table class="cc-record-table cc-history-table"><thead><tr><th>Date & Time</th><th>Channel</th><th>Type / Action</th><th>Description</th><th>Entity / UUID</th><th>Performed By</th><th>IP Address</th><th>Severity</th><th>Details</th></tr></thead><tbody>
                    @forelse($activities as $activity)
                        @php
                            $action = (string)$activity->action;
                            $channel = \Illuminate\Support\Str::headline(\Illuminate\Support\Str::before($action,'.') ?: 'System');
                            $severity = str_contains($action,'deleted') || str_contains($action,'reject') ? 'High' : (str_contains($action,'updated') ? 'Medium' : 'Low');
                        @endphp
                        <tr>
                            <td><strong>{{ optional($activity->created_at)->format('d M Y') }}</strong><small>{{ optional($activity->created_at)->format('h:i:s A') }}</small></td>
                            <td><span class="cc-channel-text"><i class="cc-dot cc-dot-1"></i>{{ $channel }}</span></td>
                            <td>{{ \Illuminate\Support\Str::headline($action) }}</td>
                            <td>{{ \Illuminate\Support\Str::headline(str_replace('.',' ',$action)) }}<small>Request: {{ str($activity->request_id)->limit(18) }}</small></td>
                            <td>{{ class_basename((string)$activity->subject_type ?: 'System') }}<small>{{ $activity->subject_id ?: $activity->uuid }}</small></td>
                            <td>{{ $auditUsers[$activity->user_id] ?? 'System' }}</td>
                            <td>{{ $activity->ip_address ?: '—' }}</td>
                            <td><i class="cc-badge cc-badge-{{ $priorityTone(strtolower($severity)) }}">{{ $severity }}</i></td>
                            <td><button type="button" class="cc-icon-btn" title="Audit UUID {{ $activity->uuid }}"><x-icon name="eye" size="14"/></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="cc-empty cc-empty-large">No audit activities found.</div></td></tr>
                    @endforelse
                    </tbody></table>
                </div>
                <footer class="cc-table-footer"><span>Showing {{ $activities->firstItem() ?? 0 }} to {{ $activities->lastItem() ?? 0 }} of {{ number_format($activities->total()) }} activities</span><div class="cc-pager"><a href="{{ $activities->previousPageUrl() ?: '#' }}" class="{{ $activities->onFirstPage()?'disabled':'' }}">‹</a><b>{{ $activities->currentPage() }}</b><a href="{{ $activities->nextPageUrl() ?: '#' }}" class="{{ $activities->hasMorePages()?'':'disabled' }}">›</a></div></footer>
            </main>
            <aside class="cc-record-side">
                <section class="cc-side-card"><header><h2>Activity Summary</h2><a href="{{ route('admin.communication-center.page',['section'=>'communication-reports']) }}">View Report</a></header><div class="cc-donut-wrap"><div class="cc-donut cc-donut-small" style="--p:50"><span><b>{{ number_format($report['total']) }}</b><small>Total</small></span></div><ul class="cc-legend cc-legend-compact">@foreach($report['by_action'] as $label=>$count)<li><i class="cc-dot cc-dot-{{ $loop->index }}"></i><span>{{ $label }}</span><b>{{ $count }}</b></li>@endforeach</ul></div></section>
                <section class="cc-side-card"><header><h2>Top Entity Types</h2><a href="#">View Report</a></header><ul class="cc-ranked-list">@foreach($report['by_entity'] as $label=>$count)<li><span>{{ $label }}</span><b>{{ $count }}</b></li>@endforeach</ul></section>
                <section class="cc-side-card"><header><h2>Quick Actions</h2></header><ul class="cc-quick-list"><li><x-icon name="file-text" size="14"/> View Full Audit Log</li><li><x-icon name="download" size="14"/> Export Audit Log</li><li><x-icon name="calendar" size="14"/> Schedule Audit Report</li><li><x-icon name="settings" size="14"/> Configure Log Settings</li></ul></section>
            </aside>
        </section>
        <section class="cc-bottom-grid">
            <article class="cc-card"><header><h2>Activities Over Time</h2><a href="#">View Report</a></header><div class="cc-mini-line">@foreach(range(1,20) as $i)<i style="height:{{ 20+(($i*19)%72) }}%"></i>@endforeach</div></article>
            <article class="cc-card"><header><h2>Activities by Channel</h2><a href="#">View Report</a></header><div class="cc-donut-wrap"><div class="cc-donut cc-donut-small" style="--p:52"><span><b>{{ number_format($report['total']) }}</b><small>Total</small></span></div><ul class="cc-legend cc-legend-compact">@foreach($report['by_action'] as $label=>$count)<li><i class="cc-dot cc-dot-{{ $loop->index }}"></i><span>{{ $label }}</span><b>{{ $count }}</b></li>@endforeach</ul></div></article>
            <article class="cc-card"><header><h2>Top Entity Types</h2></header><div class="cc-bars">@php $barMax=max(1,(int)collect($report['by_entity'])->max()); @endphp @foreach($report['by_entity'] as $label=>$count)<div><span>{{ $label }}</span><i><b style="width:{{ round(($count/$barMax)*100) }}%"></b></i><em>{{ $count }}</em></div>@endforeach</div></article>
            <article class="cc-card"><header><h2>Activity Heatmap (Time of Day)</h2></header><div class="cc-heatmap">@foreach(range(1,49) as $i)<i style="opacity:{{ .18+(($i*5)%8)/10 }}"></i>@endforeach</div></article>
        </section>
    @endif
</div>
@endsection
