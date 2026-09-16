@extends('layouts.admin')

@section('title', 'Email Dashboard')

@push('styles')
<link rel="stylesheet" href="/css/communication-center-reference.css?v=20260913-b20-1">
<style>
.email-mailbox{--ink:#17231c;--muted:#66746c;--line:#e1e8e3;--soft:#f6f8f6;--green:#0c6b45;display:grid;gap:18px}.email-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.email-head h1{font-size:28px;margin:0;color:var(--ink)}.email-head p{margin:6px 0 0;color:var(--muted)}.email-health{display:flex;gap:8px;flex-wrap:wrap}.email-pill{border:1px solid var(--line);background:#fff;border-radius:999px;padding:8px 12px;font-size:12px;font-weight:700}.email-pill.ok{color:#087044;background:#effaf4}.email-pill.warn{color:#955d00;background:#fff7e7}.email-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.email-kpi{background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px}.email-kpi small{display:block;color:var(--muted);font-weight:700}.email-kpi strong{display:block;margin-top:5px;font-size:22px;color:var(--ink)}.email-shell{display:grid;grid-template-columns:190px minmax(280px,360px) minmax(380px,1fr);min-height:640px;background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}.email-folders{border-right:1px solid var(--line);padding:16px;background:#fbfcfb}.email-compose-btn{display:block;width:100%;background:var(--green);color:#fff!important;text-align:center;padding:11px 12px;border-radius:9px;font-weight:800;text-decoration:none;margin-bottom:14px}.email-folder{display:flex;justify-content:space-between;align-items:center;padding:10px 9px;border-radius:8px;color:#34443a;text-decoration:none;font-weight:650;margin:2px 0}.email-folder.active{background:#eaf5ef;color:#075b3b}.email-folder b{font-size:11px;background:#eef1ef;padding:2px 7px;border-radius:10px}.email-list{border-right:1px solid var(--line);min-width:0}.email-search{padding:12px;border-bottom:1px solid var(--line)}.email-search form{display:flex;gap:6px}.email-search input,.email-form input,.email-form textarea{width:100%;border:1px solid #cfd8d1;border-radius:8px;padding:9px 10px;background:#fff}.email-search button,.email-button{border:0;border-radius:8px;background:#223c2d;color:#fff;padding:9px 13px;font-weight:750;cursor:pointer}.email-row{display:block;padding:13px 14px;border-bottom:1px solid #edf1ee;text-decoration:none;color:inherit}.email-row:hover,.email-row.active{background:#f1f7f3}.email-row-top{display:flex;justify-content:space-between;gap:8px}.email-row strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.email-row small{color:var(--muted);white-space:nowrap}.email-row p{margin:4px 0 0;color:#526158;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.email-empty{padding:36px 18px;text-align:center;color:var(--muted)}.email-pane{padding:20px;min-width:0}.email-pane-head{display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);padding-bottom:14px}.email-pane h2{margin:0 0 5px;font-size:20px}.email-meta{color:var(--muted);font-size:13px}.email-actions{display:flex;gap:7px;flex-wrap:wrap}.email-actions form{display:inline}.email-action{border:1px solid #cad4cd;background:#fff;border-radius:7px;padding:7px 10px;font-weight:700;cursor:pointer}.email-action.danger{color:#9a2525}.email-message{margin:15px 0;border:1px solid var(--line);border-radius:10px;padding:13px}.email-message.outbound{background:#f5faf7}.email-message-head{display:flex;justify-content:space-between;gap:12px;font-size:12px;color:var(--muted);margin-bottom:8px}.email-message-body{white-space:pre-wrap;line-height:1.55;color:#27362d}.email-status{font-size:11px;font-weight:800;text-transform:uppercase;border-radius:99px;padding:3px 7px;background:#eef1ef}.email-form{display:grid;gap:10px}.email-form label{font-size:12px;font-weight:800;color:#415047}.email-form textarea{min-height:130px;resize:vertical}.email-form-actions{display:flex;justify-content:flex-end;gap:8px}.email-card{border:1px solid var(--line);background:#fff;border-radius:12px;padding:16px}.email-card h3{margin:0 0 12px}.email-setup{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.email-dl{display:grid;grid-template-columns:150px 1fr;gap:8px;font-size:13px}.email-dl dt{font-weight:800;color:#4a594f}.email-dl dd{margin:0;overflow-wrap:anywhere}.email-table{width:100%;border-collapse:collapse;font-size:13px}.email-table th,.email-table td{text-align:left;padding:9px;border-bottom:1px solid var(--line);vertical-align:top}.email-table th{color:#526158}.email-wide{grid-column:2 / 4;padding:20px}.email-alert{padding:11px 13px;border-radius:9px;background:#effaf4;color:#075d3c;border:1px solid #caead8}.email-errors{padding:11px 13px;border-radius:9px;background:#fff0f0;color:#912020;border:1px solid #f0caca}.email-compose-panel{border:1px solid var(--line);border-radius:12px;background:#fff;padding:0 16px}.email-compose-panel summary{cursor:pointer;font-weight:800;padding:13px 0;color:var(--green)}.email-compose-panel .email-form{padding:0 0 16px}@media(max-width:1100px){.email-kpis{grid-template-columns:repeat(3,1fr)}.email-shell{grid-template-columns:170px 1fr}.email-pane{grid-column:1 / -1;border-top:1px solid var(--line)}.email-wide{grid-column:1 / -1}}@media(max-width:760px){.email-head{display:block}.email-health{margin-top:10px}.email-kpis{grid-template-columns:repeat(2,1fr)}.email-shell{display:block}.email-folders{display:flex;gap:5px;overflow:auto;border-right:0;border-bottom:1px solid var(--line)}.email-compose-btn{width:auto;white-space:nowrap;margin:0}.email-folder{white-space:nowrap}.email-list{border-right:0}.email-setup{grid-template-columns:1fr}.email-dl{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $folderLabels = ['inbox'=>'Inbox','sent'=>'Sent','drafts'=>'Drafts','trash'=>'Trash','logs'=>'Email Logs','setup'=>'Mail Setup'];
    $folderCount = fn($key) => $counts[$key] ?? null;
    $draftBody = $selected ? (string) data_get($selected->metadata, 'draft_body', '') : '';
@endphp
<div class="email-mailbox">
    <div class="email-head">
        <div><div style="font-size:12px;color:#758178;margin-bottom:6px">Project 1 Control Panel › Communication Center › Email</div><h1>Email Dashboard</h1><p>Send, receive, draft, track and manage Emerald Rozalia customer email from one mailbox.</p></div>
        <div class="email-health">
            <span class="email-pill {{ $mailStatus['outgoing_configured'] ? 'ok':'warn' }}">Outgoing: {{ $mailStatus['outgoing_configured'] ? strtoupper($mailStatus['mailer']).' ready' : 'not configured' }}</span>
            <span class="email-pill {{ $mailStatus['incoming_configured'] ? 'ok':'warn' }}">Incoming: {{ $mailStatus['incoming_configured'] ? 'webhook ready' : 'needs webhook secret' }}</span>
        </div>
    </div>

    @if(session('success'))<div class="email-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="email-errors">{{ $errors->first() }}</div>@endif

    <div class="email-kpis">
        <a class="email-kpi" href="?folder=inbox"><small>Inbox</small><strong>{{ number_format($counts['inbox']) }}</strong></a>
        <a class="email-kpi" href="?folder=sent"><small>Sent Threads</small><strong>{{ number_format($counts['sent']) }}</strong></a>
        <a class="email-kpi" href="?folder=drafts"><small>Drafts</small><strong>{{ number_format($counts['drafts']) }}</strong></a>
        <a class="email-kpi" href="?folder=trash"><small>Trash</small><strong>{{ number_format($counts['trash']) }}</strong></a>
        <a class="email-kpi" href="?folder=logs"><small>Failed Delivery</small><strong>{{ number_format($counts['failed']) }}</strong></a>
    </div>

    <details class="email-compose-panel" {{ request('compose') ? 'open' : '' }}>
        <summary>+ COMPOSE NEW EMAIL</summary>
        <form class="email-form" method="POST" action="{{ route('admin.email-mailbox.compose') }}">
            @csrf
            <div><label for="compose-to">To</label><input id="compose-to" type="email" name="to" value="{{ old('to') }}" required placeholder="customer@example.com"></div>
            <div><label for="compose-subject">Subject</label><input id="compose-subject" name="subject" value="{{ old('subject') }}" required maxlength="255"></div>
            <div><label for="compose-body">Message</label><textarea id="compose-body" name="body" required>{{ old('body') }}</textarea></div>
            <div class="email-form-actions"><button class="email-action" name="action" value="draft">Save Draft</button><button class="email-button" name="action" value="send">Send Email</button></div>
        </form>
    </details>

    <section class="email-shell">
        <nav class="email-folders" aria-label="Mailbox folders">
            <a class="email-compose-btn" href="?folder={{ $folder }}&compose=1">Compose</a>
            @foreach($folderLabels as $key=>$label)
                <a class="email-folder {{ $folder === $key ? 'active':'' }}" href="?folder={{ $key }}"><span>{{ $label }}</span>@if(in_array($key,['inbox','sent','drafts','trash']))<b>{{ $folderCount($key) }}</b>@endif</a>
            @endforeach
            <a class="email-folder" href="/admin/resource/approval-center"><span>Approval Required</span></a>
            <a class="email-folder" href="/admin/resource/email-templates">Email Templates</a>
        </nav>

        @if(in_array($folder,['logs','setup']))
            <div class="email-wide">
                @if($folder === 'setup')
                    <div class="email-setup">
                        <div class="email-card"><h3>Outgoing Mail</h3><dl class="email-dl"><dt>Mailer</dt><dd>{{ strtoupper($mailStatus['mailer']) }}</dd><dt>SMTP Host</dt><dd>{{ $mailStatus['host'] }}</dd><dt>Port</dt><dd>{{ $mailStatus['port'] }}</dd><dt>From</dt><dd>{{ $mailStatus['from_name'] }} &lt;{{ $mailStatus['from_address'] }}&gt;</dd><dt>Queue</dt><dd>{{ $mailStatus['queue'] }}</dd><dt>Status</dt><dd>{{ $mailStatus['outgoing_configured'] ? 'Configured':'Set MAIL_* values in production .env' }}</dd></dl></div>
                        <div class="email-card"><h3>Incoming / Delivery Sync</h3><dl class="email-dl"><dt>Webhook</dt><dd>{{ $mailStatus['incoming_webhook'] }}</dd><dt>Authentication</dt><dd>HMAC SHA-256 in X-Communication-Signature</dd><dt>Status</dt><dd>{{ $mailStatus['incoming_configured'] ? 'Configured':'Set COMMUNICATION_EMAIL_WEBHOOK_SECRET' }}</dd><dt>Purpose</dt><dd>Receives inbound email plus provider delivery/failure events.</dd></dl></div>
                        <div class="email-card" style="grid-column:1/-1"><h3>Send SMTP Test</h3><form class="email-form" method="POST" action="{{ route('admin.email-mailbox.test') }}">@csrf<div><label>Test recipient</label><input type="email" name="email" required placeholder="you@example.com"></div><div class="email-form-actions"><button class="email-button">Send Test Email</button></div></form></div>
                    </div>
                @else
                    <div class="email-card"><h3>Transactional Email Log</h3><p class="email-meta">Customer verification, password reset and SMTP tests are recorded here without storing passwords, tokens or email bodies.</p><div style="overflow:auto"><table class="email-table"><thead><tr><th>Time</th><th>Type</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Error</th></tr></thead><tbody>@forelse($emailLogs as $log)<tr><td>{{ optional($log->created_at)->format('d M Y H:i') }}</td><td>{{ $log->kind }}</td><td>{{ $log->recipient }}</td><td>{{ $log->subject ?: '—' }}</td><td><span class="email-status">{{ $log->status }}</span></td><td>{{ $log->failure_message ?: '—' }}</td></tr>@empty<tr><td colspan="6">No transactional email activity recorded yet.</td></tr>@endforelse</tbody></table></div></div>
                    <div class="email-card" style="margin-top:14px"><h3>Mailbox Delivery Log</h3><div style="overflow:auto"><table class="email-table"><thead><tr><th>Time</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Error</th></tr></thead><tbody>@forelse($deliveryLog as $message)<tr><td>{{ optional($message->created_at)->format('d M Y H:i') }}</td><td>{{ $message->conversation?->contact }}</td><td>{{ $message->conversation?->subject }}</td><td><span class="email-status">{{ $message->delivery_status }}</span></td><td>{{ $message->delivery_attempts }}</td><td>{{ $message->failure_message ?: '—' }}</td></tr>@empty<tr><td colspan="6">No outgoing mailbox deliveries yet.</td></tr>@endforelse</tbody></table></div></div>
                @endif
            </div>
        @else
            <div class="email-list">
                <div class="email-search"><form method="GET"><input type="hidden" name="folder" value="{{ $folder }}"><input name="q" value="{{ $search }}" placeholder="Search email"><button>Search</button></form></div>
                @forelse($conversations as $mail)
                    <a class="email-row {{ $selected?->id === $mail->id ? 'active':'' }}" href="?folder={{ $folder }}&conversation={{ $mail->uuid }}{{ $search ? '&q='.urlencode($search):'' }}"><div class="email-row-top"><strong>{{ $mail->contact ?: 'Unknown sender' }}</strong><small>{{ optional($mail->updated_at)->format('d M H:i') }}</small></div><p>{{ $mail->subject ?: '(no subject)' }}</p></a>
                @empty<div class="email-empty">No email in {{ strtolower($folderLabels[$folder]) }}.</div>@endforelse
                @if($conversations && $conversations->hasPages())<div style="padding:12px">{{ $conversations->links() }}</div>@endif
            </div>

            <article class="email-pane">
                @if($selected)
                    <div class="email-pane-head"><div><h2>{{ $selected->subject ?: '(no subject)' }}</h2><div class="email-meta">{{ $selected->contact }} · {{ strtoupper($selected->status) }} · {{ $selected->uuid }}</div></div><div class="email-actions">
                        @if($folder === 'trash')
                            <form method="POST" action="{{ route('admin.email-mailbox.restore',$selected->uuid) }}">@csrf<button class="email-action">Restore</button></form>
                            <form method="POST" action="{{ route('admin.email-mailbox.destroy',$selected->uuid) }}" onsubmit="return confirm('Permanently delete this email thread?')">@csrf @method('DELETE')<button class="email-action danger">Delete Permanently</button></form>
                        @else
                            <form method="POST" action="{{ route('admin.email-mailbox.trash',$selected) }}">@csrf<button class="email-action danger">Move to Trash</button></form>
                        @endif
                    </div>
                    @if($selected->status === 'draft' && $folder !== 'trash')
                        <form class="email-form" method="POST" action="{{ route('admin.email-mailbox.draft.update',$selected) }}" style="margin-top:16px">@csrf @method('PATCH')<div><label>To</label><input type="email" name="to" value="{{ old('to',$selected->contact) }}" required></div><div><label>Subject</label><input name="subject" value="{{ old('subject',$selected->subject) }}" required></div><div><label>Message</label><textarea name="body" required>{{ old('body',$draftBody) }}</textarea></div><div class="email-form-actions"><button class="email-action">Update Draft</button><button class="email-button" formaction="{{ route('admin.email-mailbox.draft.send',$selected) }}" formmethod="POST" name="_method" value="POST">Send Draft</button></div></form>
                    @else
                        @forelse($selected->messages as $message)<div class="email-message {{ $message->direction }}"><div class="email-message-head"><span>{{ strtoupper($message->direction) }} · {{ $message->user?->name ?: ($message->direction === 'inbound' ? $selected->contact : 'Emerald Rozalia') }}</span><span>{{ optional($message->sent_at ?: $message->created_at)->format('d M Y H:i') }} · <b>{{ $message->delivery_status }}</b></span></div><div class="email-message-body">{{ $message->body }}</div>@if($message->failure_message)<div class="email-errors" style="margin-top:8px">{{ $message->failure_message }}</div>@endif</div>@empty<div class="email-empty">This thread has no stored messages.</div>@endforelse
                        @if($folder !== 'trash')<form class="email-form" method="POST" action="{{ route('admin.email-mailbox.reply',$selected) }}" style="margin-top:18px">@csrf<label>Reply to {{ $selected->contact }}</label><textarea name="body" required placeholder="Write your reply..."></textarea><div class="email-form-actions"><button class="email-button">Send Reply</button></div></form>@endif
                    @endif
                @else<div class="email-empty">Select an email to read it here.</div>@endif
            </article>
        @endif
    </section>
</div>
@endsection
